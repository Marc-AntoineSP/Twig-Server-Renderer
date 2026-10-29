<?php

namespace App\Rendering;

use App\Catalogue\CatalogueWriter;
use App\Entity\CatalogueItem;
use App\Entity\PreviewJob;
use App\Entity\Release;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CatalogueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Reads a captured source identity; never saves a draft or renderer preset. */
final readonly class CataloguePreviews
{
    public function __construct(private EntityManagerInterface $em, private CatalogueWriter $catalogue, private ReleaseSourceReader $sources, private PreviewJobs $jobs, private ValidatorInterface $validator) {}

    public function snapshot(CatalogueItem $item, ?string $releaseId, ?int $revision, bool $canEdit): array
    {
        if ('theme' === $item->getKind()) throw new CatalogueException(422, 'Use the theme detail showcase.');
        if (null !== $releaseId) {
            if (null !== $revision) throw new CatalogueException(422, 'Choose a release or a draft revision.');
            $release = $this->em->find(Release::class, $releaseId);
            if (!$release || $release->getItem()->getId() !== $item->getId()) throw new CatalogueException(404, 'Release does not belong to this item.');
            $manifest = $release->getManifestData();
            $sources = $this->sources->read($release);
            $identity = ['releaseId' => $release->getId(), 'version' => $release->getVersion(), 'archiveHash' => $release->getArchiveHash(), 'draftRevision' => null];
            $label = 'Published release '.$release->getVersion();
        } else {
            if (!$canEdit) throw new CatalogueException(403, 'Draft previews require editor access.');
            $draft = $this->catalogue->draft($item);
            if ($revision !== $draft->getRevision()) throw new CatalogueException(412, 'Draft revision changed. Reload this page before previewing.');
            $manifest = $draft->getManifestData();
            $sources = $draft->getSourceMap();
            $identity = ['releaseId' => null, 'version' => null, 'archiveHash' => null, 'draftRevision' => $revision];
            $label = 'Saved draft r'.$revision.' · '.$item->getName();
        }
        try {
            $examples = json_decode($sources->{'meta/examples.json'} ?? '[]', false, 24, JSON_THROW_ON_ERROR);
            $schema = json_decode($sources->{$manifest->propsSchema} ?? '{}', false, 24, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Broken saved drafts remain inspectable. Preview validation reports the error.
            $examples = []; $schema = new \stdClass();
        }
        if (!is_array($examples)) $examples = [];
        $examples = array_values(array_filter(array_slice($examples, 0, 20), static fn ($example) => $example instanceof \stdClass && is_string($example->name ?? null) && ($example->props ?? null) instanceof \stdClass));
        return compact('manifest', 'sources', 'identity', 'label', 'examples', 'schema');
    }

    public function create(CatalogueItem $item, CataloguePreviewInput $input, User $owner, bool $canEdit): PreviewJob
    {
        if (count($this->validator->validate($input))) throw new CatalogueException(422, 'Invalid catalogue preview selection.');
        $snapshot = $this->snapshot($item, $input->releaseId, $input->draftRevision, $canEdit);
        if ($input->example >= 0 && !isset($snapshot['examples'][$input->example])) throw new CatalogueException(422, 'Choose an example from this exact source version.');
        $props = $input->props ?? ($input->example >= 0 ? $snapshot['examples'][$input->example]->props : $snapshot['manifest']->defaults);
        return $this->jobs->create(new PreviewInput($item->getId(), $input->sequence, $input->channel, $snapshot['manifest'], $snapshot['sources'], $props, $input->preset), $owner, [...$snapshot['identity'], 'example' => $input->example]);
    }
}
