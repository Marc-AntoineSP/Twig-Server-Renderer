<?php
namespace Styleguide\Contracts;
final class PortablePath
{
    public static function check(string $path): void
    {
        if (strlen($path)>240 || !preg_match('~^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$~D',$path)) throw new CatalogueException(422,'Unsafe path: '.$path);
        foreach(explode('/',$path) as $segment) {
            if(in_array($segment,['.','..'],true) || str_ends_with($segment,'.') || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i',$segment)) throw new CatalogueException(422,'Nonportable path segment.');
        }
    }
}
