<?php
namespace App\Composition;
use App\Catalogue\CatalogueException;
use Opis\JsonSchema\CompliantValidator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final readonly class CompositionValidator
{
    public function __construct(#[Autowire('%kernel.project_dir%/agent-kit/schemas/composition.schema.json')] private string $schema) {}
    public function validate(\stdClass $document): void
    {
        try { $json = json_encode($document, JSON_THROW_ON_ERROR, 64); }
        catch (\JsonException) { throw new CatalogueException(422, 'Composition exceeds nesting limits.'); }
        if (strlen($json) > 65536) throw new CatalogueException(422, 'Composition exceeds 64 KiB.');
        $nodes = 0;
        $walk = function ($node, int $depth) use (&$walk, &$nodes): void {
            if ($depth > 20 || ++$nodes > 500) throw new CatalogueException(422, 'Composition exceeds 20 levels or 500 nodes.');
            if (!$node instanceof \stdClass) throw new CatalogueException(422, 'Composition nodes must be objects.');
            if (isset($node->classes) && !is_array($node->classes)) throw new CatalogueException(422, 'Classes must be a list.');
            foreach ($node->classes ?? [] as $class) {
                if (!is_string($class) || !preg_match('~^[A-Za-z0-9_:/\.!\[\]()%#,+*=\-]+$~D', $class) || str_contains(strtolower($class), 'url(')) throw new CatalogueException(422, 'Container classes must be complete literal utility names.');
            }
            if (isset($node->children) && !is_array($node->children)) throw new CatalogueException(422, 'Children must be a list.');
            foreach ($node->children ?? [] as $child) $walk($child, $depth + 1);
        };
        $walk($document->root ?? null, 1);
        $result = (new CompliantValidator())->validate($document, file_get_contents($this->schema));
        if (!$result->isValid()) throw new CatalogueException(422, 'Invalid composition tree.');
    }
}
