<?php

namespace App\Rendering;

final class DocumentAssembler
{
    public function assemble(string $html, string $css, string $js, string $channel, array $preset = []): string
    {
        $nonce = bin2hex(random_bytes(16));
        $csp = "default-src 'none'; script-src 'nonce-".$nonce."'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; media-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'";
        $css = str_ireplace('</style', '<\\/style', $css);
        $js = str_ireplace('</script', '<\\/script', $js);
        $channelJson = json_encode($channel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $bridge = 'const channel='.$channelJson.';const report=()=>parent.postMessage({type:"resize",channel,height:Math.min(4000,Math.max(100,Math.ceil(document.documentElement.scrollHeight)))},"*");new ResizeObserver(report).observe(document.documentElement);addEventListener("load",report);report();';

        return '<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($csp, ENT_QUOTES).'">'
            .'<style>'.$css."\nhtml{color-scheme:light}body{margin:0;padding:1rem}</style></head><body>".$html.'<script nonce="'.$nonce.'">'.$js.'</script><script nonce="'.$nonce.'">'.$bridge.'</script></body></html>';
    }
}
