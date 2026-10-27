<?php

namespace Styleguide\Contracts;

use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\FontRegistry;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;

final readonly class ThemeValidator
{
    public const COLORS = ['base-100', 'base-200', 'base-300', 'base-content', 'primary', 'primary-content', 'secondary', 'secondary-content', 'accent', 'accent-content', 'neutral', 'neutral-content', 'info', 'info-content', 'success', 'success-content', 'warning', 'warning-content', 'error', 'error-content'];

    public function __construct(private string $schemaPath = __DIR__.'/../schemas/theme.schema.json')
    {
    }

    public function validate(\stdClass $document): void
    {
        try {
            $json = json_encode($document, JSON_THROW_ON_ERROR, 24);
        } catch (\JsonException) {
            throw new CatalogueException(422, 'Theme data is excessively nested or invalid.');
        }
        if (strlen($json) > 65536 || ($document->schemaVersion ?? null) !== 1) {
            throw new CatalogueException(422, 'Theme must use schemaVersion 1 within 64 KiB.');
        }
        $result = (new CompliantValidator(max_errors: 10, stop_at_first_error: false))->validate($document, file_get_contents($this->schemaPath));
        if (!$result->isValid()) {
            throw new CatalogueException(422, 'Theme tokens are invalid.', (new ErrorFormatter())->format($result->error()), 'invalid_theme');
        }
        $fonts = [];
        foreach ($document->fonts as $font) {
            if (isset($fonts[$font->id])) {
                throw new CatalogueException(422, 'Font IDs must be unique.');
            }
            if ('system' === $font->kind) {
                if (!isset(FontRegistry::SYSTEM[$font->family]) || isset($font->path) || isset($font->sha256) || isset($font->faces)) throw new CatalogueException(422, 'Unknown system font or unexpected assets.');
            } else {
                foreach (['faces', 'license', 'licensePath', 'licenseSha256', 'category', 'source', 'sourceVersion'] as $field) {
                    if (!isset($font->$field)) throw new CatalogueException(422, 'Local fonts require faces, source and licence metadata.');
                }
                if (isset($font->path) || isset($font->sha256)) throw new CatalogueException(422, 'Local font assets belong in faces.');
                $seen = [];
                foreach ($font->faces as $face) {
                    if ($face->path !== 'fonts/'.$face->sha256.'.woff2' || $face->weightMin > $face->weightMax || isset($seen[$face->path])) throw new CatalogueException(422, 'Invalid or duplicate font face.');
                    $seen[$face->path] = true;
                    foreach ($face->axes as $axis) if ($axis->min > $axis->default || $axis->default > $axis->max) throw new CatalogueException(422, 'Invalid variation axis bounds.');
                    if (isset($face->axes->wght) && ($face->axes->wght->min != $face->weightMin || $face->axes->wght->max != $face->weightMax)) throw new CatalogueException(422, 'Weight axis and declared range differ.');
                }
            }
            $fonts[$font->id] = $font;
        }
        foreach ([$document->typography->bodyFontId, $document->typography->headingFontId, ... (isset($document->typography->codeFontId) ? [$document->typography->codeFontId] : [])] as $id) {
            if (!isset($fonts[$id])) {
                throw new CatalogueException(422, 'Typography must reference a declared font ID.');
            }
        }
        foreach (['body', 'heading', 'code'] as $role) {
            $id = $document->typography->{$role.'FontId'} ?? null;
            if (null === $id || 'system' === $fonts[$id]->kind) continue;
            $weight = $document->typography->{$role.'Weight'} ?? 400;
            $style = $document->typography->{$role.'Style'} ?? 'normal';
            if (!array_filter($fonts[$id]->faces, static fn ($face) => $face->style === $style && $face->weightMin <= $weight && $face->weightMax >= $weight)) throw new CatalogueException(422, 'Unsupported '.$role.' font weight/style. Choose a real installed face.');
        }
        $active = [$document->typography->bodyFontId, $document->typography->headingFontId, $document->typography->codeFontId ?? 'system-mono'];
        foreach ($fonts as $font) if ('local' === $font->kind && (!in_array($font->id, $active, true) || isset(FontRegistry::SYSTEM[$font->id]))) throw new CatalogueException(422, 'Declare only the local families selected for body, heading or code, using their own IDs. Other families remain in the font library.');
        if (strlen($document->version) > 32) {
            throw new CatalogueException(422, 'Theme version exceeds 32 characters.');
        }
    }
}
