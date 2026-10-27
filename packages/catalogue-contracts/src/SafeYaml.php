<?php
namespace Styleguide\Contracts;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Exception\ParseException;
final class SafeYaml
{
    public static function decode(string $content,int $maxBytes=3145728):mixed
    {
        if(strlen($content)>$maxBytes) throw new CatalogueException(413,'YAML exceeds its byte limit.');
        // Bound indentation before the parser allocates deeply nested structures.
        if(substr_count($content,"\n")>20000) throw new CatalogueException(422,'YAML exceeds 20,000 lines; use ZIP or JSON.');
        foreach(explode("\n",$content) as $line) if(strspn($line,' ')>128) throw new CatalogueException(422,'YAML exceeds nesting limits.');
        try{$value=(new Parser(maxNestingLevel:32,maxAliasesForCollections:0))->parse($content,Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE|Yaml::PARSE_OBJECT_FOR_MAP|Yaml::PARSE_EXCEPTION_ON_ALIAS);}
        catch(ParseException){throw new CatalogueException(422,'Invalid YAML document.');}
        try{return StrictJson::decode(json_encode($value,JSON_THROW_ON_ERROR,64),$maxBytes);}
        catch(\JsonException){throw new CatalogueException(422,'YAML must contain finite bounded JSON values.');}
    }
    public static function encode(mixed $value):string{$yaml=Yaml::dump($value,32,2,Yaml::DUMP_OBJECT_AS_MAP|Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);self::decode($yaml);return $yaml;}
}
