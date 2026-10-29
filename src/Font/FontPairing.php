<?php

namespace App\Font;

use App\Rendering\DocumentAssembler;
use App\Theme\TypographySample;
use Styleguide\Contracts\CatalogueException;
use Styleguide\Contracts\FontRegistry;

final readonly class FontPairing
{
    public function __construct(private FontLibrary $library, private DocumentAssembler $assembler) {}

    public function preview(FontPairingInput $input): array
    {
        $fonts = []; $definitions = []; $roles = []; $css = '';
        $text = strip_tags(TypographySample::HTML).' '.$input->text;
        $codes = array_unique(array_map(mb_ord(...), mb_str_split($text)));
        foreach (['body', 'heading', 'code'] as $role) {
            $id = $input->typography->{$role.'FontId'} ?? '';
            $weight = $input->typography->{$role.'Weight'} ?? 400;
            $style = $input->typography->{$role.'Style'} ?? 'normal';
            if (!is_string($id) || strlen($id) > 100 || !is_int($weight) || $weight < 100 || $weight > 1000 || !in_array($style, ['normal', 'italic'], true)) throw new CatalogueException(422, 'Choose a valid '.$role.' family, integer weight and style.');
            $font = $this->library->definition($id); $definitions[$id] = clone $font; $missing = []; $selected = [];
            if ('local' === $font->kind) {
                $available = array_filter($font->faces, static fn ($face) => $face->style === $style && $face->weightMin <= $weight && $face->weightMax >= $weight);
                if (!$available) throw new CatalogueException(422, ucfirst($role).': '.$font->family.' has no real face for '.$weight.' '.$style.'. Choose an available weight/style; no synthetic face is used.');
                foreach ($available as $face) if (array_any($codes, fn ($code) => $this->covers($face->unicodeRange, $code))) $selected[$face->path] = $face;
                foreach ($codes as $code) if ($code > 32 && !array_any($available, fn ($face) => $this->covers($face->unicodeRange, $code))) $missing[] = 'U+'.strtoupper(str_pad(dechex($code), 4, '0', STR_PAD_LEFT));
                $font->faces = array_values($selected);
            }
            if (isset($fonts[$id]) && 'local' === $font->kind) $font->faces = array_values(array_column([...$fonts[$id]->faces, ...$font->faces], null, 'path'));
            $fonts[$id] = $font;
            $roles[$role] = ['family' => $font->family, 'kind' => $font->kind, 'weight' => $weight, 'style' => $style, 'missing' => $missing, 'faces' => array_map(static fn ($face) => ['sha256' => $face->sha256, 'subset' => $face->subset, 'unicodeRange' => $face->unicodeRange], array_values($selected))];
            $css .= '[data-font-role="'.$role.'"]{font-family:'.FontRegistry::cssFamily($font).';font-weight:'.$weight.';font-style:'.$style.';}';
        }
        $theme = (object) ['cssName' => 'font-pairing', 'colorScheme' => $input->scheme, 'typography' => $input->typography, 'fonts' => array_values($fonts)];
        $dark = 'dark' === $input->scheme;
        $css .= 'body{background:'.($dark ? '#17212b;color:#f4f6f8' : '#ffffff;color:#17212b').';line-height:1.6}article{max-width:48rem}h2{font-size:1.75rem}input,button{font:inherit;color:inherit;background:transparent;border:1px solid currentColor;border-radius:.3rem;padding:.5rem;max-width:100%;box-sizing:border-box}label{display:block;margin:1rem 0}input{display:block;width:100%}pre{overflow:auto;padding:1rem;border:1px solid currentColor}*{font-synthesis:none}';
        $html = TypographySample::HTML;
        foreach (['body', 'heading', 'code'] as $role) if ('' !== $input->text) $html .= '<p data-font-role="'.$role.'">'.htmlspecialchars($input->text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        $html .= '<p role="status">Local faces are checked when the frame loads. Characters outside declared coverage use the family’s system fallback; system families depend on your device.</p>';
        try {
            $document = $this->assembler->assemble($html, $css, '', bin2hex(random_bytes(16)), ['bodyFont' => 'theme', 'headingFont' => 'theme', 'codeFont' => 'theme'], (array) $theme);
        } catch (CatalogueException) {
            throw new CatalogueException(422, 'A requested pairing font asset is unavailable or corrupt. No pairing preview was loaded. Restore its local files or choose another installed family.');
        }
        return ['roles' => $roles, 'fonts' => array_values($definitions), 'html' => $document];
    }

    private function covers(string $range, int $code): bool
    {
        foreach (explode(',', $range) as $part) {
            if (!preg_match('/^\s*U\+([0-9a-f?]+)(?:-([0-9a-f]+))?\s*$/iD', $part, $match)) continue;
            $min = hexdec(str_replace('?', '0', $match[1]));
            $max = hexdec($match[2] ?? str_replace('?', 'F', $match[1]));
            if ($code >= $min && $code <= $max) return true;
        }
        return false;
    }
}
