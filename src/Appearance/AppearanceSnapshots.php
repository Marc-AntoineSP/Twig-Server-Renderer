<?php

namespace App\Appearance;

use App\Entity\AppearanceSnapshot;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Styleguide\Contracts\CanonicalJson;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\ThemeCssGenerator;
use Styleguide\Contracts\ThemeValidator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AppearanceSnapshots
{
    public function __construct(private EntityManagerInterface $em, private ThemeCssGenerator $css, private ThemeValidator $validator, private CanonicalJson $json, private UrlGeneratorInterface $urls, private \App\Font\FontLibrary $fonts) {}

    public function saved(Theme $theme, bool $default = false): AppearanceSnapshot
    {
        return $this->capture($theme->getDocument(), $default ? 'default' : 'saved', $theme, null);
    }

    public function trial(\stdClass $document, User $owner): AppearanceSnapshot
    {
        $snapshot = $this->capture($document, 'trial', null, $owner);
        $old = $this->em->getRepository(AppearanceSnapshot::class)->findBy(['owner' => $owner, 'kind' => 'trial'], ['createdAt' => 'DESC']);
        foreach ($old as $index => $entry) if (($index >= 32 || $entry->getExpiresAt() < new \DateTimeImmutable()) && $entry->getId() !== $snapshot->getId()) $this->em->remove($entry);
        $this->em->flush();
        return $snapshot;
    }

    private function capture(\stdClass $document, string $kind, ?Theme $theme, ?User $owner): AppearanceSnapshot
    {
        $this->validator->validate($document);
        $id = hash('sha256', 'appearance-v2|'.$kind.'|'.$theme?->getId().'|'.$theme?->getRevision().'|'.$owner?->getId().'|'.($owner ? gmdate('YmdH') : '').'|'.$this->json->encode($document));
        if ($existing = $this->em->find(AppearanceSnapshot::class, $id)) return $existing;
        return $this->em->wrapInTransaction(function () use ($id, $document, $kind, $theme, $owner): AppearanceSnapshot {
            $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtext(?))', ['appearance-snapshot-'.$id]);
            if ($existing = $this->em->find(AppearanceSnapshot::class, $id)) return $existing;
            $css = $this->css->applicationCss($document, $id);
            preg_match_all('~url\("\./fonts/([a-f0-9]{64})\.woff2"\)~', $css, $faces);
            foreach (array_unique($faces[1]) as $hash) {
                try { \Styleguide\Contracts\SourceBytes::validateFont($this->fonts->bytes($hash)); }
                catch (CatalogueException $error) { throw new CatalogueException(422, 'A selected local font asset is unavailable. Choose an installed font or restore its files.'); }
            }
            $css = preg_replace_callback('~url\("\./fonts/([a-f0-9]{64})\.woff2"\)~', fn ($match) => 'url("'.$this->urls->generate('font_asset', ['hash' => $match[1], 'extension' => 'woff2']).'")', $css);
            if (strlen($css) > 65536) throw new CatalogueException(413, 'Application theme CSS exceeds 64 KiB.');
            $snapshot = new AppearanceSnapshot($id, $theme, $theme?->getRevision(), $document, $css, hash('sha256', $css), $kind, $owner, $owner ? new \DateTimeImmutable('+1 hour') : null);
            $this->em->persist($snapshot);
            return $snapshot;
        });
    }

    public function describe(AppearanceSnapshot $snapshot): array
    {
        $doc = $snapshot->getDocument();
        return ['id' => $snapshot->getId(), 'themeId' => $snapshot->getTheme()?->getId(), 'revision' => $snapshot->getSourceRevision(), 'cssName' => $doc->cssName, 'mode' => $doc->colorScheme,
            'url' => $this->urls->generate('appearance_stylesheet', ['id' => $snapshot->getId(), 'hash' => $snapshot->getCssHash()]), 'cssHash' => $snapshot->getCssHash(), 'integrity' => 'sha256-'.base64_encode(hex2bin($snapshot->getCssHash()))];
    }
}
