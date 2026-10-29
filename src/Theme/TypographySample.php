<?php

namespace App\Theme;

/** Trusted, shared role examples for the renderer and local pairing workspace. */
final class TypographySample
{
    public const TEXT = 'English: Design for everyday reading. Français : Élève, cœur, Œuvre, ça coûte 12 € — « Une interface lisible ! »';
    public const HTML = '<article aria-label="Typography pairing"><h2 class="font-heading" data-font-role="heading">Design for everyday reading · Œuvre</h2><p data-font-role="body">'.self::TEXT.'</p><label data-font-role="body">Email address · Adresse courriel <input aria-label="Pairing email" value="élève@example.test" readonly></label><button type="button" data-font-role="body">Continue · Continuer</button><pre><code class="font-mono" data-font-role="code">const message = "Bonjour, world!"; // 12 €</code></pre></article>';
}
