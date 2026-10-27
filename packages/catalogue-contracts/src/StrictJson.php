<?php
namespace Styleguide\Contracts;
final class StrictJson
{
    public static function decode(string $json, int $maxBytes = 262144, int $depth = 64): mixed
    {
        if (strlen($json) > $maxBytes) throw new CatalogueException(413, 'JSON exceeds its byte limit.');
        $structural=0; $nesting=0;
        for ($i=0,$length=strlen($json);$i<$length;++$i) {
            $c=$json[$i];
            if ('"'===$c) { for(++$i;$i<$length && '"'!==$json[$i];++$i) if('\\'===$json[$i]) ++$i; continue; }
            if ('{'===$c || '['===$c) ++$nesting;
            if ('}'===$c || ']'===$c) --$nesting;
            if (str_contains('{[,',$c) && ++$structural>20000 || $nesting>$depth) throw new CatalogueException(422,'JSON exceeds structural limits.');
        }
        try { $value = json_decode($json, false, $depth, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new CatalogueException(422, 'Invalid or excessively nested JSON.'); }
        // Native decoding validates grammar; this pass rejects duplicate decoded object keys.
        $stack = [];
        for ($i=0, $length=strlen($json); $i<$length; ++$i) {
            $c=$json[$i];
            if ('{' === $c) { $stack[]=['object'=>true,'key'=>true,'seen'=>[]]; continue; }
            if ('[' === $c) { $stack[]=['object'=>false]; continue; }
            if ('}' === $c || ']' === $c) { array_pop($stack); continue; }
            $last=count($stack)-1;
            if (',' === $c && $last>=0 && $stack[$last]['object']) { $stack[$last]['key']=true; continue; }
            if ('"' !== $c) continue;
            $start=$i++;
            while ($i<$length && '"' !== $json[$i]) { if ('\\' === $json[$i]) ++$i; ++$i; }
            if ($last>=0 && $stack[$last]['object'] && $stack[$last]['key']) {
                $key=json_decode(substr($json,$start,$i-$start+1),true,8,JSON_THROW_ON_ERROR);
                if (isset($stack[$last]['seen'][$key])) throw new CatalogueException(422, 'Duplicate JSON object key: '.$key, [], 'duplicate_json_key');
                $stack[$last]['seen'][$key]=true; $stack[$last]['key']=false;
            }
        }
        return $value;
    }
}
