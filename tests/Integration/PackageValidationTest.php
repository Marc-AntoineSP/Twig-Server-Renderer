<?php

namespace App\Tests\Integration;

use Styleguide\Contracts\CatalogueException;
use App\Catalogue\CreateItemInput;
use App\Catalogue\SourceFactory;
use Styleguide\Contracts\PackageValidator;
use Styleguide\Contracts\PropsValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PackageValidationTest extends KernelTestCase
{
    public static function invalidPackages(): iterable
    {
        foreach (['version', 'required-field', 'foreign-path', 'php-file', 'traversal', 'case-collision', 'duplicate-inventory', 'wrong-defaults', 'wrong-example', 'schema-default', 'remote-ref', 'unknown-keyword', 'recursive-ref', 'controller', 'dependency', 'checksum'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidPackages')]
    public function testStructuralAndSemanticPackageRejection(string $case): void
    {
        self::bootKernel();
        [$manifest, $sources] = self::getContainer()->get(SourceFactory::class)->initial(new CreateItemInput('tests/validation', 'component', 'Validation'));
        $validator = self::getContainer()->get(PackageValidator::class);
        $manifest = $validator->validate($manifest, $sources, true);
        switch ($case) {
            case 'version': $manifest->schemaVersion = 2; break;
            case 'required-field': unset($manifest->license); break;
            case 'foreign-path': $sources->{'templates/catalogue/other/item/test.html.twig'} = 'x'; break;
            case 'php-file': $sources->{'meta/shell.php'} = '<?php echo 1;'; break;
            case 'traversal': $sources->{'meta/../shell.txt'} = 'x'; break;
            case 'case-collision': $sources->{'meta/EXAMPLES.json'} = '[]'; break;
            case 'duplicate-inventory': $manifest->files[] = clone $manifest->files[0]; break;
            case 'wrong-defaults': $manifest->defaults->message = 123; break;
            case 'wrong-example': $sources->{'meta/examples.json'} = '[{"name":"Bad","props":{"message":123}}]'; break;
            case 'schema-default': $sources->{'meta/props.schema.json'} = '{"type":"object","properties":{"count":{"type":"integer","default":"wrong"}}}'; break;
            case 'remote-ref': $sources->{'meta/props.schema.json'} = '{"type":"object","properties":{"message":{"$ref":"https://example.test/schema"}}}'; break;
            case 'unknown-keyword': $sources->{'meta/props.schema.json'} = '{"type":"object","allOf":[]}'; break;
            case 'recursive-ref': $sources->{'meta/props.schema.json'} = '{"type":"object","$defs":{"loop":{"$ref":"#/$defs/loop"}},"properties":{"message":{"$ref":"#/$defs/loop"}}}'; break;
            case 'controller': $manifest->controllers = [(object) ['identifier' => 'catalogue--other--item', 'path' => 'assets/controllers/catalogue/other/item_controller.js']]; break;
            case 'dependency': $manifest->dependencies = [(object) ['name' => 'tests/validation', 'version' => '1.0.0']]; break;
            case 'checksum': $sources->{$manifest->entry} .= 'changed'; break;
        }
        $this->expectException(CatalogueException::class);
        $validator->validate($manifest, $sources, $case !== 'checksum');
    }

    public function testLocalDefinitionsAndDefaultsAndExamplesValidate(): void
    {
        self::bootKernel();
        $props = self::getContainer()->get(PropsValidator::class);
        $schema = json_decode('{"type":"object","$defs":{"message":{"type":"string","minLength":1}},"properties":{"message":{"$ref":"#/$defs/message"}},"required":["message"],"additionalProperties":false}');
        $props->validateSchema($schema);
        $props->validateValue((object) ['message' => 'Valid'], $schema);
        $this->expectException(CatalogueException::class);
        $props->validateValue((object) ['message' => ''], $schema);
    }

    #[DataProvider('unsafePaths')]
    public function testUnsafePortablePathsAreRejected(string $path): void
    {
        self::bootKernel();
        $this->expectException(CatalogueException::class);
        self::getContainer()->get(PackageValidator::class)->path($path);
    }

    public static function unsafePaths(): iterable
    {
        foreach (['/absolute', 'C:/drive', 'meta\\file.txt', 'meta//file.txt', 'meta/./file.txt', 'meta/../file.txt', "meta/a\0b", 'meta/CON.txt', 'meta/trailing.'] as $path) {
            yield [$path];
        }
    }

    public function testUrlPropsRejectExecutableSchemes(): void
    {
        self::bootKernel();
        $this->expectException(CatalogueException::class);
        self::getContainer()->get(PropsValidator::class)->validateValue((object) ['url' => 'javascript:alert(1)'], (object) ['type' => 'object']);
    }
}
