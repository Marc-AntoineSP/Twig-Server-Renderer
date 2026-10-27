<?php

namespace App\Rendering;
use Styleguide\Contracts\FontRegistry;

final class DocumentAssembler
{
    public function __construct(private readonly FontRegistry $fonts, private readonly \App\Font\ThemeFontAssets $fontAssets, private readonly \App\Font\FontLibrary $library)
    {
    }

    public function assemble(string $html, string $css, string $js, string $channel, array $preset = [], ?array $theme = null, array $fontFiles = []): string
    {
        $nonce = bin2hex(random_bytes(16));
        $csp = "default-src 'none'; script-src 'nonce-".$nonce."'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; media-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'";
        $tokens = $theme ? json_decode(json_encode($theme, JSON_THROW_ON_ERROR), false, 24, JSON_THROW_ON_ERROR) : null;
        $effective = (object) ['typography' => new \stdClass(), 'fonts' => []]; $families = [];
        foreach (['body', 'heading', 'code'] as $role) {
            $id = $preset[$role.'Font'] ?? ('code' === $role ? 'theme' : 'system-sans');
            if ('theme' === $id) {
                $fontId = $tokens?->typography->{$role.'FontId'} ?? ('code' === $role ? 'system-mono' : 'system-sans');
                $font = array_values(array_filter($tokens?->fonts ?? [], static fn ($font) => $font->id === $fontId))[0] ?? $this->library->definition($fontId);
                $families[$role] = 'var(--sg-font-'.$role.', '.FontRegistry::cssFamily($font).')';
            } else { $font = $this->library->definition($id); $families[$role] = FontRegistry::cssFamily($font); }
            $effective->typography->{$role.'FontId'} = $font->id;
            $effective->fonts[$font->id] = $font;
        }
        $effective->fonts = array_values($effective->fonts);
        // Replace generated face rules with only the effective renderer selections.
        $css = preg_replace_callback('/@font-face\s*\{[^}]*\}/i', static fn ($match) => preg_match('~url\(([\'"]?)(?:\./)?fonts/[a-f0-9]{64}\.woff2\1\)~', $match[0]) ? '' : $match[0], $css)."\n".FontRegistry::faceCss($effective);
        $css = $this->fontAssets->embed($css, $effective, $fontFiles);
        $css = str_ireplace('</style', '<\\/style', $css);
        $js = str_ireplace('</script', '<\\/script', $js);
        $channelJson = json_encode($channel, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $bridge = 'const channel='.$channelJson.';const report=()=>parent.postMessage({type:"resize",channel,height:Math.min(4000,Math.max(100,Math.ceil(document.documentElement.scrollHeight)))},"*");new ResizeObserver(report).observe(document.documentElement);addEventListener("load",report);report();document.fonts.ready.then(()=>{const failed=[...document.fonts].some(f=>f.status==="error");document.documentElement.dataset.fontStatus=failed?"fallback":"loaded";if(failed){const message=document.createElement("p");message.setAttribute("role","status");message.textContent="A selected font could not load. System fallback is active.";document.body.prepend(message);}report();});';
        $rootSize = max(12, min(24, (int) ($preset['rootSize'] ?? 16)));
        $bodyFont = $families['body']; $headingFont = $families['heading']; $codeFont = $families['code'];
        $textClass = htmlspecialchars($preset['textClass'] ?? 'text-base', ENT_QUOTES);
        $themeName = htmlspecialchars($theme['cssName'] ?? (($preset['colorScheme']??'theme')==='dark'?'dark':'light'), ENT_QUOTES);
        $scheme = in_array($preset['colorScheme']??'theme',['light','dark'],true)?$preset['colorScheme']:(($theme['colorScheme'] ?? 'light') === 'dark' ? 'dark' : 'light');
        $surface=match($preset['surface']??'base-100'){'base-200'=>'var(--color-base-200)', 'transparent'=>'transparent',default=>'var(--color-base-100)'};

        return '<!doctype html><html lang="en" data-theme="'.$themeName.'" style="font-size:'.$rootSize.'px"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="'.htmlspecialchars($csp, ENT_QUOTES).'">'
            .'<style>'.$css."\nhtml{color-scheme:".$scheme.'}body{margin:0;padding:1rem;font-synthesis:none;background:'.$surface.';font-family:'.$bodyFont.'}h1,h2,h3,h4,h5,h6{font-family:'.$headingFont.'}code,pre,kbd,samp{font-family:'.$codeFont.'}</style></head><body class="'.$textClass.'">'.$html.'<script nonce="'.$nonce.'">'.$js.'</script><script nonce="'.$nonce.'">'.$bridge.'</script></body></html>';
    }
}
