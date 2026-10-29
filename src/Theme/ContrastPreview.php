<?php

namespace App\Theme;

use App\Rendering\DocumentAssembler;

final readonly class ContrastPreview
{
    public function __construct(private DocumentAssembler $assembler) {}

    public function document(object $colors, array $results): string
    {
        $html = '<h1>Current draft examples</h1><p>Solid foreground and background pairs. Each icon uses the content colour on its paired surface; surrounding control boundaries are not measured.</p>';
        $css = 'body{background:#fff;color:#17212b;font:16px/1.6 system-ui}section{padding:1rem;margin:1rem 0;border:2px solid transparent}section:focus{outline:3px solid #17212b;outline-offset:3px}';
        foreach ($results as $background => $result) {
            $html .= '<section tabindex="-1" id="example-'.$background.'"><h2>'.$background.' / '.$result['foreground'].'</h2>';
            if (null === $result['ratio']) $html .= '<p>Cannot evaluate this pair. Enter opaque six-digit hex colours.</p>';
            else {
                $css .= '#example-'.$background.'{background:'.$colors->$background.';color:'.$colors->{$result['foreground']}.'}';
                $html .= '<p>Ordinary text: Read this message · Lisez ce message.</p><p style="font-size:24px">Large text: Your workspace</p><span aria-label="Example check icon" role="img">✓</span> <span>Content-colour icon on this surface</span>';
            }
            $html .= '</section>';
        }
        // Trusted code accepts only an exact, application-owned fragment ID from its parent.
        $js = 'addEventListener("message",event=>{if(event.source!==parent||event.data?.type!=="contrast-example"||typeof event.data.id!=="string")return;const target=document.getElementById(event.data.id);if(target?.matches("section[id^=example-]")){target.focus();target.scrollIntoView();}});';
        return $this->assembler->assemble($html, $css, $js, bin2hex(random_bytes(16)));
    }
}
