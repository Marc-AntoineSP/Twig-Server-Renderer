<?php
namespace App\Composition;
use App\Catalogue\CatalogueException;
use App\Catalogue\Validation\PropsValidator;
use App\Entity\Release;
use App\Rendering\DependencyResolver;
use App\Rendering\ReleaseSourceReader;
use App\Repository\CatalogueRepository;
use App\Shared\CanonicalJson;
use Doctrine\ORM\EntityManagerInterface;
final readonly class CompositionCompiler
{
    public const EMPTY_TEMPLATE = "<section class=\"grid gap-4\">\n\n</section>\n";
    public function __construct(private CompositionValidator $validator, private CatalogueRepository $catalogue, private EntityManagerInterface $em, private ReleaseSourceReader $reader, private PropsValidator $props, private CanonicalJson $json, private DependencyResolver $resolver) {}
    public function materialize(\stdClass $manifest, \stdClass $sources, \stdClass $document, bool $verify = false): array
    {
        $this->validator->validate($document);
        if (!isset($manifest->kind, $manifest->entry, $manifest->name, $manifest->dependencies) || !is_array($manifest->dependencies)) throw new CatalogueException(422, 'Composition manifest is invalid.');
        if (!in_array($manifest->kind, ['block', 'layout'], true)) throw new CatalogueException(422, 'Only blocks and layouts have composition ownership.');
        $manifest = clone $manifest; $sources = clone $sources; $dependencies = []; $releases = [];
        $render = function (\stdClass $node) use (&$render, &$dependencies, &$releases, $manifest): string {
            if ('text' === $node->type) return '{{ '.TwigLiteral::encode($node->value).'|escape }}';
            if ('container' === $node->type) return '<'.$node->tag.' class="'.implode(' ', $node->classes).'">' ."\n".implode("\n", array_map($render, $node->children))."\n</".$node->tag.'>';
            if ($node->name === $manifest->name) throw new CatalogueException(422, 'A composition cannot reference itself.');
            if (isset($dependencies[$node->name]) && $dependencies[$node->name]->version !== $node->version) throw new CatalogueException(409, 'Conflicting exact versions for '.$node->name.'.');
            $key = $node->name.'@'.$node->version;
            if (!isset($releases[$key])) {
                $item = $this->catalogue->named($node->name);
                $release = $item ? $this->em->getRepository(Release::class)->findOneBy(['item' => $item, 'version' => $node->version]) : null;
                if (!$release || 'theme' === $item->getKind()) throw new CatalogueException(422, 'Unknown immutable reference '.$key.'.');
                $releases[$key] = [$release->getManifestData(), $this->reader->read($release)];
            }
            [$target, $files] = $releases[$key];
            $this->props->validateValue($node->props, json_decode($files->{$target->propsSchema}, false, 24, JSON_THROW_ON_ERROR), 'reference.'.$key);
            $dependencies[$node->name] = (object) ['name' => $node->name, 'version' => $node->version];
            return '{% include '.TwigLiteral::encode(substr($target->entry, 10)).' with '.TwigLiteral::encode($node->props).' only %}';
        };
        $twig = $render($document->root)."\n";
        if ($verify && (($sources->{$manifest->entry} ?? null) !== $twig || $this->json->encode($manifest->dependencies) !== $this->json->encode(array_values($dependencies)))) throw new CatalogueException(409, 'Composition owns generated Twig and dependencies. Edit the tree or duplicate and convert to source.');
        $manifest->dependencies = array_values($dependencies);
        $sources->{$manifest->entry} = $twig;
        $sources->{'meta/composition.json'} = $this->json->encode($document, true)."\n";
        $this->resolver->resolve($manifest, $sources);
        return [$manifest, $sources];
    }
    public function verify(\stdClass $manifest, \stdClass $sources): void
    {
        if (!isset($sources->{'meta/composition.json'})) return;
        try { $document = json_decode($sources->{'meta/composition.json'}, false, 64, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new CatalogueException(422, 'Invalid composition JSON.'); }
        if (!$document instanceof \stdClass) throw new CatalogueException(422, 'Composition must be an object.');
        $this->materialize($manifest, $sources, $document, true);
    }
}
