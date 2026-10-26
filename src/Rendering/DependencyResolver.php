<?php

namespace App\Rendering;

use App\Catalogue\CatalogueException;
use App\Entity\Release;
use App\Repository\CatalogueRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DependencyResolver
{
    public function __construct(private CatalogueRepository $catalogue, private EntityManagerInterface $entityManager, private ReleaseSourceReader $releases)
    {
    }

    public function resolve(\stdClass $manifest, \stdClass $sources): array
    {
        $versions = [];
        $visiting = [];
        $resolved = [];
        $allSources = new \stdClass();
        $templates = new \stdClass();
        $controllers = [];
        $identifiers = [];
        $safelist = [];
        $bytes = 0;
        $visit = function (\stdClass $package, \stdClass $files, int $depth) use (&$visit, &$versions, &$visiting, &$resolved, $allSources, $templates, &$controllers, &$identifiers, &$safelist, &$bytes): void {
            if ($depth > 20 || count($versions) > 100) {
                throw new CatalogueException(422, 'Dependency graph exceeds depth/package limits.');
            }
            if (isset($visiting[$package->name])) {
                throw new CatalogueException(422, 'Dependency cycle detected at '.$package->name.'.');
            }
            if (isset($versions[$package->name]) && $versions[$package->name] !== $package->version) {
                throw new CatalogueException(409, 'Conflicting exact versions for '.$package->name.'.');
            }
            if (isset($resolved[$package->name])) {
                return;
            }
            $versions[$package->name] = $package->version;
            $visiting[$package->name] = true;
            foreach ($package->dependencies as $dependency) {
                $item = $this->catalogue->named($dependency->name);
                $release = $item ? $this->entityManager->getRepository(Release::class)->findOneBy(['item' => $item, 'version' => $dependency->version]) : null;
                if (!$release) {
                    throw new CatalogueException(422, 'Missing immutable dependency '.$dependency->name.'@'.$dependency->version.'.');
                }
                $visit($release->getManifestData(), $this->releases->read($release), $depth + 1);
            }
            foreach ($files as $path => $source) {
                // Metadata names are package-local and never compiler module paths.
                $key = str_starts_with($path, 'meta/') ? 'metadata/'.$package->name.'/'.substr($path, 5) : $path;
                if (property_exists($allSources, $key) || ($bytes += strlen($source)) > 2097152 || count((array) $allSources) >= 200) {
                    throw new CatalogueException(422, 'Dependency closure has colliding paths or exceeds source limits.');
                }
                $allSources->$key = $source;
                if (str_starts_with($path, 'templates/')) {
                    $templates->{substr($path, 10)} = $source;
                }
            }
            foreach ($package->controllers as $controller) {
                if (isset($identifiers[$controller->identifier])) {
                    throw new CatalogueException(409, 'Controller identifier collision.');
                }
                $identifiers[$controller->identifier] = true;
                $controllers[] = $controller;
            }
            $safelist = array_values(array_unique([...$safelist, ...$package->safelist]));
            unset($visiting[$package->name]);
            $resolved[$package->name] = true;
        };
        $visit($manifest, $sources, 0);

        return ['entry' => substr($manifest->entry, 10), 'templates' => $templates, 'sources' => $allSources, 'controllers' => $controllers, 'safelist' => $safelist, 'versions' => $versions];
    }
}
