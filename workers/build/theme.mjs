import Ajv2020 from 'ajv/dist/2020.js';
import { readFileSync } from 'node:fs';

const schema = JSON.parse(readFileSync('/toolchain/theme.schema.json', 'utf8'));
const validate = new Ajv2020({ strict: false, allErrors: false }).compile(schema);
const families = { 'system-sans': 'ui-sans-serif, system-ui, sans-serif', 'system-serif': 'ui-serif, Georgia, serif', 'system-mono': 'ui-monospace, SFMono-Regular, Menlo, monospace' };
const geometry = { radiusSelectorRem: ['radius-selector', 'rem'], radiusFieldRem: ['radius-field', 'rem'], radiusBoxRem: ['radius-box', 'rem'], sizeSelectorRem: ['size-selector', 'rem'], sizeFieldRem: ['size-field', 'rem'], borderPx: ['border', 'px'], depth: ['depth', ''], noise: ['noise', ''] };

export function themeCss(theme) {
    if (!validate(theme) || Buffer.byteLength(JSON.stringify(theme)) > 65536) throw new Error('Invalid typed theme.');
    const fonts = new Map();
    for (const font of theme.fonts) {
        if (font.kind !== 'system' || !Object.hasOwn(families, font.family) || fonts.has(font.id) || font.path || font.sha256) throw new Error('Only unique registered system fonts are supported.');
        fonts.set(font.id, families[font.family]);
    }
    if (!fonts.has(theme.typography.bodyFontId) || !fonts.has(theme.typography.headingFontId)) throw new Error('Unknown theme font reference.');
    let css = `@plugin "/toolchain/node_modules/daisyui/theme/index.js" {\nname: "${theme.cssName}";\ndefault: ${theme.default};\nprefersdark: ${theme.prefersDark};\ncolor-scheme: ${theme.colorScheme};\n`;
    for (const [color, value] of Object.entries(theme.colors)) css += `--color-${color}: ${value};\n`;
    for (const [name, [variable, unit]] of Object.entries(geometry)) css += `--${variable}: ${theme.geometry[name]}${unit};\n`;
    css += '}\n@theme inline { --font-sans: var(--sg-font-body); --font-heading: var(--sg-font-heading); }\n';
    const sizes = { 'text-xs': '.75rem', 'text-sm': '.875rem', 'text-base': '1rem', 'text-lg': '1.125rem', 'text-xl': '1.25rem' };
    css += `[data-theme="${theme.cssName}"] { --sg-font-body: ${fonts.get(theme.typography.bodyFontId)}; --sg-font-heading: ${fonts.get(theme.typography.headingFontId)}; --sg-body-size: ${sizes[theme.typography.textClass]}; --sg-line-height: ${theme.typography.lineHeight}; font-family: var(--sg-font-body); }\n`;
    css += `html[data-theme="${theme.cssName}"] { font-size: ${theme.typography.rootFontSizePx}px; }\n`;
    css += `:where([data-theme="${theme.cssName}"]) body,:where([data-theme="${theme.cssName}"].sg-surface) { font-size: var(--sg-body-size); line-height: var(--sg-line-height); }\n`;
    css += `:where([data-theme="${theme.cssName}"]) :where(h1,h2,h3,h4,h5,h6) { font-family: var(--sg-font-heading); }\n`;

    return css;
}
