<?php

namespace App\Theme;

use App\Catalogue\CatalogueException;
use App\Rendering\FontRegistry;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ThemeValidator
{
    public const COLORS = ['base-100', 'base-200', 'base-300', 'base-content', 'primary', 'primary-content', 'secondary', 'secondary-content', 'accent', 'accent-content', 'neutral', 'neutral-content', 'info', 'info-content', 'success', 'success-content', 'warning', 'warning-content', 'error', 'error-content'];

    public function __construct(#[Autowire('%kernel.project_dir%/agent-kit/schemas/theme.schema.json')] private string $schemaPath)
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
            if ('system' !== $font->kind || !isset(FontRegistry::SYSTEM[$font->family]) || isset($font->path) || isset($font->sha256)) {
                throw new CatalogueException(422, 'This profile supports registered system fonts only; font files are not accepted.');
            }
            $fonts[$font->id] = $font;
        }
        foreach ([$document->typography->bodyFontId, $document->typography->headingFontId] as $id) {
            if (!isset($fonts[$id])) {
                throw new CatalogueException(422, 'Typography must reference a declared font ID.');
            }
        }
        if (strlen($document->version) > 32) {
            throw new CatalogueException(422, 'Theme version exceeds 32 characters.');
        }
    }
}
