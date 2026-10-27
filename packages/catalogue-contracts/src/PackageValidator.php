<?php

namespace Styleguide\Contracts;

use Styleguide\Contracts\CatalogueException;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ErrorFormatter;

final readonly class PackageValidator
{
    public function __construct(
        private PropsValidator $props,
        private ThemeValidator $themes,
        private ThemeCssGenerator $themeCss,
        private \Styleguide\Contracts\CompositionValidator $compositions,
        private string $schemaPath = __DIR__.'/../schemas/package.schema.json',
    ) {
    }

    public function validate(\stdClass $manifest, \stdClass $sources, bool $refreshInventory = false): \stdClass
    {
        if (strlen(json_encode($manifest, JSON_THROW_ON_ERROR, 32)) > 262144) {
            $this->fail('manifestData', 'Manifest exceeds 256 KiB.');
        }
        $manifest = json_decode(json_encode($manifest, JSON_THROW_ON_ERROR), false, 32, JSON_THROW_ON_ERROR);
        if (($manifest->schemaVersion ?? null) !== 1) {
            $this->fail('manifestData.schemaVersion', 'Unknown format version. This studio supports schemaVersion 1; upgrade before importing newer formats.');
        }
        $validator = new CompliantValidator(max_errors: 10, stop_at_first_error: false);
        $result = $validator->validate($manifest, file_get_contents($this->schemaPath));
        if (!$result->isValid()) {
            throw new CatalogueException(422, 'Manifest structure is invalid.', (new ErrorFormatter())->format($result->error()));
        }
        if (count((array) $sources) > 100) {
            $this->fail('sourceMap', 'At most 100 draft files are supported.');
        }
        $seen = [];
        $total = 0;
        foreach ($sources as $path => $source) {
            $this->path($path);
            $this->owned($path, $manifest->name);
            if ('theme' !== $manifest->kind && str_ends_with($path, '.css') && preg_match('/@(?:plugin|config|import|tailwind|source|reference)\b/i', $source)) $this->fail('sourceMap', 'Executable CSS directives are not accepted in package assets.');
            $key = strtolower($path);
            if (isset($seen[$key])) {
                $this->fail('sourceMap', 'Case-insensitive path collision: '.$path);
            }
            $seen[$key] = true;
            if (!is_string($source) || !mb_check_encoding($source, 'UTF-8')) {
                $this->fail('sourceMap.'.$path, 'Source must be UTF-8 text.');
            }
            if (strlen($source) > 1048576 || ($total += strlen($source)) > 2097152) {
                $this->fail('sourceMap', 'Sources exceed 1 MiB per file or 2 MiB in total.');
            }
        }
        $inventory = [];
        foreach ($manifest->files as $file) {
            $this->path($file->path);
            $this->owned($file->path, $manifest->name);
            if (isset($inventory[strtolower($file->path)])) {
                $this->fail('manifestData.files', 'Duplicate inventory path: '.$file->path);
            }
            $inventory[strtolower($file->path)] = true;
            if (!$refreshInventory && (!property_exists($sources, $file->path) || strlen($sources->{$file->path}) !== $file->bytes || !hash_equals($file->sha256, hash('sha256', $sources->{$file->path})))) {
                $this->fail('manifestData.files', 'File bytes/checksum mismatch: '.$file->path);
            }
        }
        if (!$refreshInventory && count($inventory) !== count((array) $sources)) {
            $this->fail('manifestData.files', 'Inventory must contain every payload file exactly once.');
        }
        $this->path($manifest->entry);
        $this->owned($manifest->entry, $manifest->name);
        if ('theme' === $manifest->kind) {
            $this->theme($manifest, $sources);
        } else {
        if (!property_exists($sources, $manifest->entry) || !str_starts_with($manifest->entry, 'templates/catalogue/'.$manifest->name.'/') || !str_ends_with($manifest->entry, '.html.twig')) {
            $this->fail('manifestData.entry', 'Entry must be an existing owned Twig template.');
        }
        if (!(isset($manifest->propsSchema) || isset($manifest->defaults)) || !$manifest->defaults instanceof \stdClass) {
            $this->fail('manifestData.defaults', 'Non-theme packages require propsSchema and object defaults.');
        }
        $this->path($manifest->propsSchema);
        if (!str_starts_with($manifest->propsSchema, 'meta/') || !property_exists($sources, $manifest->propsSchema)) {
            $this->fail('manifestData.propsSchema', 'Props schema must be an existing metadata file.');
        }
        $schema = $this->json($sources->{$manifest->propsSchema}, 'propsSchema');
        if (!$schema instanceof \stdClass) {
            $this->fail('propsSchema', 'Props schema must be a JSON object.');
        }
        $this->props->validateSchema($schema);
        $this->props->validateValue($manifest->defaults, $schema, 'defaults');
        if (property_exists($sources, 'meta/examples.json')) {
            $examples = $this->json($sources->{'meta/examples.json'}, 'examples');
            if (!is_array($examples) || count($examples) > 30) {
                $this->fail('examples', 'Examples must be a list of at most 30 entries.');
            }
            $names = [];
            foreach ($examples as $index => $example) {
                if (!$example instanceof \stdClass || !isset($example->name, $example->props) || !is_string($example->name) || '' === trim($example->name) || strlen($example->name) > 80 || !$example->props instanceof \stdClass || array_diff(array_keys((array) $example), ['name', 'props'])) {
                    $this->fail('examples', 'Each example needs a bounded name and object props.');
                }
                if (isset($names[$example->name])) {
                    $this->fail('examples', 'Duplicate example name.');
                }
                $names[$example->name] = true;
                $this->props->validateValue($example->props, $schema, 'examples.'.$index);
            }
        }
        if (isset($sources->{'meta/composition.json'})) {
            $tree = $this->json($sources->{'meta/composition.json'}, 'composition', 64);
            if (!$tree instanceof \stdClass || !in_array($manifest->kind, ['block', 'layout'], true)) $this->fail('composition', 'Only blocks/layouts may own composition data.');
            $this->compositions->validate($tree);
        }
        }
        $controllers = [];
        foreach ($manifest->controllers as $controller) {
            $this->path($controller->path);
            $this->owned($controller->path, $manifest->name);
            $logical = substr($controller->path, strlen('assets/controllers/'));
            $expectedIdentifier = str_replace(['_', '/'], ['-', '--'], preg_replace('/[_-]controller\.js$/D', '', $logical));
            if (!str_starts_with($controller->path, 'assets/controllers/catalogue/') || !preg_match('/[_-]controller\.js$/D', $controller->path) || $controller->identifier !== $expectedIdentifier || !property_exists($sources, $controller->path) || isset($controllers[$controller->identifier])) {
                $this->fail('manifestData.controllers', 'Controller identifiers/paths must agree with package ownership and be unique.');
            }
            $controllers[$controller->identifier] = true;
        }
        foreach ($sources as $path => $source) {
            if (str_starts_with($path, 'assets/controllers/') && preg_match('/[_-]controller\.js$/D', $path) && !in_array($path, array_map(static fn ($c) => $c->path, $manifest->controllers), true)) $this->fail('controllers', 'Every controller file must be declared.');
        }
        $dependencies = [];
        foreach ($manifest->dependencies as $dependency) {
            if ($dependency->name === $manifest->name || isset($dependencies[$dependency->name])) {
                $this->fail('manifestData.dependencies', 'Self dependencies and duplicate dependency names are forbidden.');
            }
            $dependencies[$dependency->name] = true;
        }
        foreach ($manifest->safelist as $class) {
            if (preg_match('/[\s{};<>\x00-\x1f]/', $class)) {
                $this->fail('manifestData.safelist', 'Safelist entries must be individual literal class names.');
            }
        }
        if ($refreshInventory) {
            $manifest->files = [];
            $sorted = (array) $sources;
            ksort($sorted, SORT_STRING);
            foreach ($sorted as $path => $source) {
                $manifest->files[] = (object) ['path' => $path, 'sha256' => hash('sha256', $source), 'bytes' => strlen($source)];
            }
        }

        return $manifest;
    }

    private function theme(\stdClass $manifest, \stdClass $sources): void
    {
        if ('meta/theme.json' !== $manifest->entry || $manifest->controllers || $manifest->dependencies || (isset($manifest->propsSchema) || isset($manifest->defaults))) $this->fail('theme', 'A v1 theme needs meta/theme.json, no controllers/dependencies or props.');
        if (!isset($sources->{'meta/theme.json'})) $this->fail('theme', 'Missing theme tokens.');
        $document = $this->json($sources->{'meta/theme.json'}, 'theme');
        if (!$document instanceof \stdClass) $this->fail('theme', 'Theme must be an object.');
        $this->themes->validate($document);
        if ($document->name !== $manifest->name || $document->version !== $manifest->version) $this->fail('theme', 'Theme identity/version must match its package.');
        $prefix = 'assets/styles/catalogue/'.$manifest->name.'/';
        foreach ($this->themeCss->files($document) as $name => $expected) {
            if (!str_ends_with($name, '.css')) continue;
            $path = $prefix.$name;
            if (!isset($sources->$path) || ThemeImporter::normalizeCss($sources->$path) !== ThemeImporter::normalizeCss($expected)) $this->fail('theme', 'Theme source CSS conflicts with canonical tokens: '.$name);
        }
        foreach ($sources as $path => $source) {
            if (!str_starts_with($path, 'meta/') && !in_array($path, [$prefix.'app.css', $prefix.'theme.fragment.css', $prefix.'typography.css', $prefix.'preview.css'], true)) $this->fail('theme', 'Unexpected theme payload.');
            if ($path === $prefix.'preview.css' && preg_match('/@(?:plugin|config|import|source|tailwind)\b/i', $source)) $this->fail('theme', 'preview.css must be compiled CSS.');
        }
    }

    public function path(string $path): void
    {
        PortablePath::check($path);
    }

    private function owned(string $path, string $name): void
    {
        $allowed = (str_starts_with($path, 'templates/catalogue/'.$name.'/') && str_ends_with($path, '.html.twig'))
            || ($path === 'assets/controllers/catalogue/'.$name.'_controller.js')
            || (str_starts_with($path, 'assets/controllers/catalogue/'.$name.'/') && str_ends_with($path, '.js'))
            || (str_starts_with($path, 'assets/styles/catalogue/'.$name.'/') && preg_match('/\.css$/D', $path))
            || (str_starts_with($path, 'assets/catalogue/'.$name.'/') && preg_match('/\.(?:css|txt)$/D', $path))
            || (str_starts_with($path, 'meta/') && (preg_match('/\.(?:json|md|txt)$/D', $path) || $path === 'meta/LICENSE'));
        if (!$allowed || $path === 'manifest.json') {
            $this->fail('sourceMap', 'Path/type is not owned or supported: '.$path);
        }
    }

    private function json(string $source, string $field, int $depth = 24): mixed
    {
        if (strlen($source) > 65536) {
            $this->fail($field, 'JSON metadata exceeds 64 KiB.');
        }
        try {
            return StrictJson::decode($source, 65536, $depth);
        } catch (\JsonException) {
            $this->fail($field, 'Invalid or excessively nested JSON.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw new CatalogueException(422, $message, [$field => [$message]]);
    }
}
