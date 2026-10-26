import { mkdtemp, writeFile, readFile, rm } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { posix, join } from 'node:path';
import { tmpdir } from 'node:os';
import { build } from 'esbuild';
import { parse } from 'acorn';

const execute = promisify(execFile);
let directory;
const invalid = message => { throw new Error(message); };
const safePath = value => typeof value === 'string' && value.length <= 240 && /^[A-Za-z0-9_.-]+(?:\/[A-Za-z0-9_.-]+)*$/.test(value) && !value.split('/').some(x => x === '.' || x === '..');
try {
    let text = '';
    for await (const chunk of process.stdin) {
        text += chunk;
        if (Buffer.byteLength(text) > 3145728) invalid('Input exceeds 3 MiB.');
    }
    const job = JSON.parse(text);
    if (!job.sources || Array.isArray(job.sources) || typeof job.sources !== 'object' || Object.keys(job.sources).length > 200 || !Array.isArray(job.controllers) || job.controllers.length > 100 || !Array.isArray(job.safelist) || job.safelist.length > 1000) invalid('Invalid bounded compiler input.');
    let bytes = 0;
    for (const [path, source] of Object.entries(job.sources)) {
        if (!safePath(path) || typeof source !== 'string' || Buffer.byteLength(source) > 1048576 || (bytes += Buffer.byteLength(source)) > 2097152) invalid('Invalid bounded source map.');
        if (path.endsWith('.css') && /@(import|plugin|config)\b/i.test(source)) invalid('Imported CSS configuration and plugins are forbidden.');
    }
    for (const name of job.safelist) if (typeof name !== 'string' || name.length > 120 || /[\s{};<>\x00-\x1f]/.test(name)) invalid('Safelist must contain literal class names.');
    directory = await mkdtemp(join(tmpdir(), 'studio-build-'));
    await writeFile(join(directory, 'scan.txt'), Object.values(job.sources).join('\n') + '\n' + job.safelist.join('\n'));
    await writeFile(join(directory, 'input.css'), `@import "/toolchain/node_modules/tailwindcss/index.css" source(none);\n@source "${directory}/scan.txt";\n@plugin "/toolchain/node_modules/daisyui/index.js" { themes: light --default, dark --prefersdark; }\n`);
    await execute(process.execPath, ['--max-old-space-size=128', '/toolchain/node_modules/@tailwindcss/cli/dist/index.mjs', '-i', join(directory, 'input.css'), '-o', join(directory, 'output.css'), '--minify'], { cwd: '/toolchain', timeout: 7000, maxBuffer: 1048576 });
    const css = await readFile(join(directory, 'output.css'), 'utf8');
    const identifiers = new Set();
    const imports = [];
    const registrations = [];
    for (const [index, controller] of job.controllers.entries()) {
        if (!controller || typeof controller.identifier !== 'string' || !/^catalogue--[a-z][a-z0-9-]*--[a-z][a-z0-9-]*$/.test(controller.identifier) || !safePath(controller.path) || !Object.hasOwn(job.sources, controller.path) || identifiers.has(controller.identifier)) invalid('Invalid or duplicate controller registration.');
        identifiers.add(controller.identifier);
        imports.push(`import Controller${index} from ${JSON.stringify('sg:'+controller.path)};`);
        registrations.push(`app.register(${JSON.stringify(controller.identifier)}, Controller${index});`);
    }
    const parsed = new Set();
    const checkSyntax = (source, path) => {
        if (parsed.has(path)) return;
        parsed.add(path);
        let ast;
        try { ast = parse(source, { ecmaVersion: 'latest', sourceType: 'module', locations: true }); }
        catch (error) { invalid(`JavaScript syntax error in ${path}:${error.loc?.line || 1}: ${error.message}`); }
        const visit = node => {
            if (!node || typeof node !== 'object') return;
            if (node.type === 'ImportExpression') invalid(`Dynamic imports are not supported in ${path}:${node.loc.start.line}.`);
            for (const value of Object.values(node)) {
                if (Array.isArray(value)) value.forEach(visit);
                else if (value && typeof value === 'object' && value.type) visit(value);
            }
        };
        visit(ast);
    };
    const entry = `import { Application } from '@hotwired/stimulus';\n${imports.join('\n')}\nconst app = Application.start();\n${registrations.join('\n')}`;
    const result = await build({
        stdin: { contents: entry, sourcefile: 'studio-bootstrap.js', resolveDir: '/toolchain' },
        bundle: true, write: false, platform: 'browser', format: 'iife', target: 'es2022', minify: true, sourcemap: false, logLevel: 'silent',
        plugins: [{ name: 'finite-package-imports', setup(plugin) {
            plugin.onResolve({ filter: /.*/ }, args => {
                if (args.path === '@hotwired/stimulus') return { path: '/toolchain/node_modules/@hotwired/stimulus/dist/stimulus.js' };
                let path;
                if (args.path.startsWith('sg:') && args.namespace !== 'package') path = args.path.slice(3);
                else if (args.namespace === 'package' && /^\.{1,2}\//.test(args.path)) path = posix.normalize(posix.join(posix.dirname(args.importer), args.path));
                else invalid(`Forbidden compiler import: ${args.path.slice(0, 160)}`);
                if (!safePath(path) || !path.endsWith('.js') || !Object.hasOwn(job.sources, path)) invalid('Unknown package module.');
                return { path, namespace: 'package' };
            });
            plugin.onLoad({ filter: /.*/, namespace: 'package' }, args => {
                checkSyntax(job.sources[args.path], args.path);
                return { contents: job.sources[args.path], loader: 'js' };
            });
        } }],
    });
    const js = result.outputFiles[0].text;
    if (Buffer.byteLength(css) + Buffer.byteLength(js) > 2097152) invalid('Compiled assets exceed 2 MiB.');
    process.stdout.write(JSON.stringify({ ok: true, css, js }));
} catch (error) {
    let message = error.errors?.[0]?.text || error.message || 'Compilation failed.';
    message = String(message).replaceAll('/toolchain/', '').replaceAll(/\/tmp\/studio-build-[^/\s]+\//g, '').slice(0, 1000);
    process.stdout.write(JSON.stringify({ ok: false, error: { code: 'build_invalid', message } }));
} finally {
    if (directory) await rm(directory, { recursive: true, force: true });
}
