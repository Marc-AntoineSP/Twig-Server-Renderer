<?php
namespace Styleguide\Contracts;
final class PackageReference
{
    public static function parse(string $reference):array
    {
        if(!preg_match('~^([a-z][a-z0-9-]*/[a-z][a-z0-9-]*)@((?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))$~D',$reference,$m) || strlen($m[1])>100 || strlen($m[2])>32)throw new CatalogueException(422,'Use namespace/slug@stable.x.y with an exact stable version.');
        return [$m[1],$m[2]];
    }
}
