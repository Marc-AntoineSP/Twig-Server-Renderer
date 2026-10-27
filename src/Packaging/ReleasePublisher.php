<?php
namespace App\Packaging;
use App\Catalogue\CatalogueException;
use App\Catalogue\CatalogueWriter;
use App\Catalogue\Validation\PackageValidator;
use App\Entity\CatalogueItem;
use App\Entity\Release;
use App\Rendering\DependencyResolver;
use App\Rendering\PreviewRenderer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
final readonly class ReleasePublisher
{
    public function __construct(private CatalogueWriter $catalogue, private PackageValidator $validator, private DependencyResolver $dependencies, private PreviewRenderer $renderer, private ArchiveStore $archives, private EntityManagerInterface $em, private ValidatorInterface $inputs, private \App\Composition\CompositionCompiler $compositions) {}
    public function publish(CatalogueItem $item, PublishInput $input): Release
    {
        if (count($this->inputs->validate($input))) throw new CatalogueException(422, 'Stable version and expected revision are required.');
        $draft = $this->catalogue->draft($item);
        if ($draft->getRevision() !== $input->expectedRevision) throw new CatalogueException(412, 'Draft revision changed.');
        if ($this->em->getRepository(Release::class)->findOneBy(['item' => $item, 'version' => $input->version])) throw new CatalogueException(409, 'Release version already exists and is immutable.');
        $manifest = $draft->getManifestData(); $sources = $draft->getSourceMap();
        $this->compositions->verify($manifest, $sources);
        $manifest->version = $input->version;
        $sources->{'meta/LICENSE'} ??= 'Declared licence: '.$manifest->license."\nNo additional distribution rights are granted by the studio.\n";
        $sources->{'meta/README.md'} ??= '# '.$manifest->name."\n\n".$manifest->description."\n\nInclude `".substr($manifest->entry, 10)."` with the declared props only.\nInstall this package and its exact dependencies using the Symfony installer.\nTailwind/daisyUI sources must be compiled in the consumer. Browser JS is trusted there.\n";
        $manifest = $this->validator->validate($manifest, $sources, true);
        $closure = $this->dependencies->resolve($manifest, $sources);
        $examples = isset($sources->{'meta/examples.json'}) ? json_decode($sources->{'meta/examples.json'}, false, 24, JSON_THROW_ON_ERROR) : [];
        if (count($examples) > 20) throw new CatalogueException(422, 'Publication supports at most 20 examples.');
        foreach ([$manifest->defaults, ...array_map(static fn ($example) => $example->props, $examples)] as $props) {
            $result = $this->renderer->render([...$closure, 'props' => $props, 'channel' => bin2hex(random_bytes(16))]);
            if (!$result['ok']) throw new CatalogueException(422, 'Publication validation failed: '.$result['error']['message'], [], 'release_build_failed');
        }
        [$key, $hash] = $this->archives->put($manifest, $sources);
        $release = new Release($item, $input->version, $manifest, $hash, $key);
        try {
            $this->em->wrapInTransaction(function () use ($draft, $input, $release): void {
                $this->em->refresh($draft);
                $this->em->lock($draft, LockMode::OPTIMISTIC, $input->expectedRevision);
                $this->em->persist($release);
            });
        } catch (OptimisticLockException) { throw new CatalogueException(412, 'Draft changed during release validation.'); }
        catch (UniqueConstraintViolationException) { throw new CatalogueException(409, 'Release version already exists.'); }
        return $release;
    }
}
