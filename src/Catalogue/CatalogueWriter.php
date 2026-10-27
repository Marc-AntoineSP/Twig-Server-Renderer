<?php

namespace App\Catalogue;

use App\Catalogue\Validation\PackageValidator;
use App\Entity\CatalogueItem;
use App\Entity\Draft;
use App\Entity\User;
use App\Repository\CatalogueRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CatalogueWriter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CatalogueRepository $catalogue,
        private SourceFactory $sources,
        private PackageValidator $packages,
        private ValidatorInterface $validator,
        private \App\Composition\CompositionCompiler $compositions,
    ) {
    }

    public function create(CreateItemInput $input, ?User $user, ?\stdClass $manifest = null, ?\stdClass $sources = null): Draft
    {
        $this->validateInput($input);
        if ($this->catalogue->named($input->name)) {
            throw new CatalogueException(409, 'That catalogue name already exists.', ['name' => ['Choose a unique namespace/slug.']], 'duplicate_name');
        }
        if (null === $manifest || null === $sources) {
            [$manifest, $sources] = $this->sources->initial($input);
        }
        $manifest = clone $manifest;
        $manifest->title = $input->title;
        $manifest->description = $input->description;
        $manifest = $this->packages->validate($manifest, $sources, true);
        $this->compositions->verify($manifest, $sources);
        if ($manifest->name !== $input->name || $manifest->kind !== $input->kind) {
            throw new CatalogueException(422, 'Manifest identity does not match the catalogue item.');
        }
        [$namespace, $slug] = explode('/', $input->name, 2);
        $item = new CatalogueItem($namespace, $slug, $input->kind, $input->title, $input->description, $this->tags($input->tags));
        $draft = new Draft($item, $manifest, $sources, $user);
        try {
            $this->entityManager->wrapInTransaction(function () use ($item, $draft): void {
                $this->entityManager->persist($item);
                $this->entityManager->persist($draft);
            });
        } catch (UniqueConstraintViolationException) {
            throw new CatalogueException(409, 'That catalogue name already exists.', ['name' => ['Choose a unique namespace/slug.']], 'duplicate_name');
        }

        return $draft;
    }

    public function save(CatalogueItem $item, int $expectedRevision, DraftInput $input, User $user): Draft
    {
        $this->validateInput($input);
        $manifest = clone $input->manifestData;
        $manifest->title = $input->title;
        $manifest->description = $input->description;
        if (isset($this->draft($item)->getSourceMap()->{'meta/composition.json'}) && !isset($input->sourceMap->{'meta/composition.json'})) {
            throw new CatalogueException(409, 'Duplicate and convert to source before removing composition ownership.');
        }
        $manifest = $this->packages->validate($manifest, $input->sourceMap, true);
        $this->compositions->verify($manifest, $input->sourceMap);
        if ($manifest->name !== $item->getName() || $manifest->kind !== $item->getKind()) {
            throw new CatalogueException(422, 'Name and kind are immutable; create a new item to change them.');
        }
        try {
            return $this->entityManager->wrapInTransaction(function () use ($item, $expectedRevision, $input, $manifest, $user): Draft {
                $draft = $this->draft($item);
                $this->entityManager->lock($draft, LockMode::OPTIMISTIC, $expectedRevision);
                $item->reviseMetadata($input->title, $input->description, $this->tags($input->tags));
                $draft->replace($manifest, $input->sourceMap, $user);

                return $draft;
            });
        } catch (OptimisticLockException) {
            throw new CatalogueException(412, 'This draft changed since you opened it. Your changes were not saved. Reload the latest revision and merge your changes.', [], 'revision_mismatch');
        }
    }

    public function draft(CatalogueItem $item): Draft
    {
        return $this->entityManager->find(Draft::class, $item->getId()) ?? throw new CatalogueException(404, 'Draft not found.');
    }

    private function validateInput(object $input): void
    {
        $errors = $this->validator->validate($input);
        if (count($errors) > 0) {
            $fields = [];
            foreach ($errors as $error) {
                $fields[$error->getPropertyPath()][] = $error->getMessage();
            }
            throw new CatalogueException(422, 'Catalogue input is invalid.', $fields);
        }
    }

    private function tags(array $tags): array
    {
        return array_values(array_unique(array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), $tags)));
    }
}
