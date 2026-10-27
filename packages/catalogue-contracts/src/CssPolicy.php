<?php
namespace Styleguide\Contracts;
final class CssPolicy
{
    public static function hasExecutableDirective(string $css):bool
    {
        // CSS escapes are decoded before policy matching, including hex terminator space.
        $decoded=preg_replace_callback('~\\\\([a-f0-9]{1,6})(?:\r\n|[ \t\r\n\f])?|\\\\([^\r\n\f])~i',static function(array $m):string{
            if(($m[1]??'')==='')return $m[2];$point=hexdec($m[1]);return mb_chr($point===0||$point>0x10ffff||($point>=0xd800&&$point<=0xdfff)?0xfffd:$point,'UTF-8');
        },$css);
        return (bool)preg_match('/@(?:plugin|config|import|tailwind|source|reference)\b/i',$decoded);
    }
}
