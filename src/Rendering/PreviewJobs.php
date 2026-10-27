<?php

namespace App\Rendering;

use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CatalogueWriter;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\PropsValidator;
use App\Entity\CatalogueItem;
use App\Entity\PreviewJob;
use App\Entity\User;
use App\Entity\ThemeRelease;
use Styleguide\Contracts\ThemeValidator;
use App\Message\RenderPreview;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PreviewJobs
{
    private LockFactory $locks;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CatalogueWriter $catalogue,
        private PackageValidator $packages,
        private PropsValidator $props,
        private DependencyResolver $dependencies,
        private MessageBusInterface $bus,
        private ValidatorInterface $validator,
        private ThemeValidator $themes,
        private \App\Composition\CompositionCompiler $compositions,
        private Connection $connection,
        #[Autowire('%kernel.project_dir%')] private string $projectDirectory,
    ) {
        // PostgreSQL advisory locks work across the web and consumer containers.
        $this->locks = new LockFactory(new DoctrineDbalPostgreSqlStore($connection));
    }

    public function create(PreviewInput $input, User $user): PreviewJob
    {
        $violations = $this->validator->validate($input);
        if (count($violations)) {
            throw new CatalogueException(422, 'Invalid preview input.');
        }
        $lock = $this->locks->createLock('preview-create-'.$user->getId());
        if (!$lock->acquire()) {
            throw new CatalogueException(429, 'Another preview request is being prepared. Retry shortly.');
        }
        try {
            $pending = $this->entityManager->createQuery('SELECT COUNT(j.id) FROM App\Entity\PreviewJob j WHERE j.owner = :owner AND j.status IN (:statuses) AND j.expiresAt > :now')
                ->setParameters(['owner' => $user, 'statuses' => ['queued', 'running'], 'now' => new \DateTimeImmutable()])->getSingleScalarResult();
            if ($pending >= 3) {
                throw new CatalogueException(429, 'At most three previews may be pending per account.');
            }
            $item = $this->entityManager->find(CatalogueItem::class, $input->itemId) ?? throw new CatalogueException(404, 'Catalogue item not found.');
            if ('theme' === $item->getKind()) throw new CatalogueException(422, 'Use the theme laboratory to preview theme tokens.');
            $draft = $this->catalogue->draft($item);
            $manifest = $input->manifestData ?? $draft->getManifestData();
            $sources = $input->sourceMap ?? $draft->getSourceMap();
            if (!$manifest instanceof \stdClass || !$sources instanceof \stdClass) {
                throw new CatalogueException(422, 'Manifest and source map must be JSON objects.');
            }
            $manifest = $this->packages->validate($manifest, $sources, true);
            $this->compositions->verify($manifest, $sources);
            if ($manifest->name !== $item->getName() || $manifest->kind !== $item->getKind()) {
                throw new CatalogueException(422, 'Preview identity does not match the catalogue item.');
            }
            $props = $input->props ?? $manifest->defaults;
            if (!$props instanceof \stdClass) {
                throw new CatalogueException(422, 'Preview props must be a JSON object.');
            }
            $schema = json_decode($sources->{$manifest->propsSchema}, false, 24, JSON_THROW_ON_ERROR);
            $this->props->validateValue($props, $schema);
            $closure = $this->dependencies->resolve($manifest, $sources);
            $preset = $input->preset ?? new RendererSettings();
            $theme = $input->themeData;
            if (null === $theme && null !== $preset->themeReleaseId) {
                $theme = ($this->entityManager->find(ThemeRelease::class, $preset->themeReleaseId) ?? throw new CatalogueException(404, 'Theme release not found.'))->getDocument();
            }
            $theme ??= $closure['theme'] ?? null;
            if (null !== $theme) {
                if (!$theme instanceof \stdClass) { throw new CatalogueException(422, 'Theme data must be an object.'); }
                $this->themes->validate($theme);
            }
            $closure['safelist'] = array_values(array_unique([...$closure['safelist'], $preset->textClass]));
            $payload = (object) [...$closure, 'props' => $props, 'channel' => $input->channel, 'preset' => $preset->toArray(), 'theme' => $theme];
            $policyDigest = hash_file('sha256', $this->projectDirectory.'/workers/render/composer.lock').hash_file('sha256', $this->projectDirectory.'/workers/build/package-lock.json').'sandbox-v2-local-fragments';
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR).$policyDigest);
            // Keep the latest 60 jobs, retiring terminal history rather than blocking live editing.
            $this->connection->executeStatement("UPDATE preview_job SET expires_at = CURRENT_TIMESTAMP WHERE id IN (SELECT id FROM preview_job WHERE owner_id = :owner AND status NOT IN ('queued','running') AND expires_at > CURRENT_TIMESTAMP ORDER BY created_at ASC,id ASC LIMIT GREATEST(0,(SELECT COUNT(*) FROM preview_job WHERE owner_id = :owner AND expires_at > CURRENT_TIMESTAMP)-59))",['owner'=>$user->getId()]);
            $job = new PreviewJob($user, $input->sequence, $hash, $payload);
            $this->entityManager->wrapInTransaction(function () use ($job): void {
                $this->entityManager->persist($job);
                $this->entityManager->flush();
                // The Doctrine queue uses this same connection: snapshot and enqueue commit together.
                $this->bus->dispatch(new RenderPreview($job->getId()));
            });

            return $job;
        } finally {
            $lock->release();
        }
    }
}
