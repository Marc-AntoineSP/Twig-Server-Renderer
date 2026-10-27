#!/usr/bin/env python3
"""Offline fixture checks, not a replacement for production JSON Schema validation."""
import base64
import hashlib
import json
import re
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[2]
SCHEMAS = ROOT / 'agent-kit/schemas'
PACKAGES = ROOT / 'agent-kit/examples/packages'


def require(condition, message):
    if not condition:
        raise AssertionError(message)


def read_json(path):
    return json.loads(path.read_text(encoding='utf-8'))


def validate(value, schema, root=None, location='$'):
    """The standard keywords used by this kit, with local JSON pointer refs only."""
    root = schema if root is None else root
    if '$ref' in schema:
        ref = schema['$ref']
        require(ref.startswith('#/'), f'{location}: remote ref forbidden')
        resolved = root
        for segment in ref[2:].split('/'):
            resolved = resolved[segment.replace('~1', '/').replace('~0', '~')]
        validate(value, resolved, root, location)
        return
    if 'oneOf' in schema:
        matches = 0
        for alternative in schema['oneOf']:
            try:
                validate(value, alternative, root, location)
                matches += 1
            except AssertionError:
                pass
        require(matches == 1, f'{location}: expected one variant, found {matches}')
    if 'const' in schema:
        require(value == schema['const'] and type(value) is type(schema['const']), f'{location}: const mismatch')
    if 'enum' in schema:
        require(any(value == v and type(value) is type(v) for v in schema['enum']), f'{location}: enum mismatch')
    types = {
        'object': lambda v: isinstance(v, dict),
        'array': lambda v: isinstance(v, list),
        'string': lambda v: isinstance(v, str),
        'integer': lambda v: isinstance(v, int) and not isinstance(v, bool),
        'number': lambda v: isinstance(v, (int, float)) and not isinstance(v, bool),
        'boolean': lambda v: isinstance(v, bool),
        'null': lambda v: v is None,
    }
    if 'type' in schema:
        require(types[schema['type']](value), f'{location}: wrong type, expected {schema["type"]}')
    if isinstance(value, dict):
        require(set(schema.get('required', [])) <= value.keys(), f'{location}: required fields missing')
        props = schema.get('properties', {})
        if schema.get('additionalProperties') is False:
            require(value.keys() <= props.keys(), f'{location}: unknown property')
        for key, item in value.items():
            if key in props:
                validate(item, props[key], root, f'{location}.{key}')
    if isinstance(value, list):
        require(len(value) >= schema.get('minItems', 0), f'{location}: too few items')
        require(len(value) <= schema.get('maxItems', float('inf')), f'{location}: too many items')
        if schema.get('uniqueItems'):
            require(len({json.dumps(v, sort_keys=True) for v in value}) == len(value), f'{location}: duplicate items')
        for index, item in enumerate(value):
            if 'items' in schema:
                validate(item, schema['items'], root, f'{location}[{index}]')
    if isinstance(value, str):
        require(len(value) >= schema.get('minLength', 0), f'{location}: string too short')
        require(len(value) <= schema.get('maxLength', float('inf')), f'{location}: string too long')
        if 'pattern' in schema:
            require(re.search(schema['pattern'], value) is not None, f'{location}: pattern mismatch')
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        require(value >= schema.get('minimum', -float('inf')), f'{location}: below minimum')
        require(value <= schema.get('maximum', float('inf')), f'{location}: above maximum')


def safe_path(path):
    require(isinstance(path, str) and path, 'empty/nonstring path')
    require(not path.startswith('/') and '\\' not in path and ':' not in path and '\x00' not in path, f'unsafe path: {path}')
    require(all(part not in ('', '.', '..') for part in path.split('/')), f'unsafe segment: {path}')
    require(PurePosixPath(path).as_posix() == path, f'noncanonical path: {path}')


def walk_nodes(node):
    yield node
    for child in node.get('children', []):
        yield from walk_nodes(child)


def main():
    parsed = {}
    for path in ROOT.rglob('*.json'):
        parsed[path] = read_json(path)
    package_schema = read_json(SCHEMAS / 'package.schema.json')
    manifests = {}
    template_paths = {}
    controller_ids = set()
    inventory_count = 0
    for manifest_path in sorted(PACKAGES.glob('*/manifest.json')):
        manifest = read_json(manifest_path)
        validate(manifest, package_schema)
        key = (manifest['name'], manifest['version'])
        require(key not in manifests, 'duplicate package version')
        manifests[key] = (manifest_path.parent, manifest)
        inventory = {entry['path']: entry for entry in manifest['files']}
        require(len(inventory) == len(manifest['files']), 'duplicate inventory entry')
        require(len({p.casefold() for p in inventory}) == len(inventory), 'case-colliding paths')
        actual = {p.relative_to(manifest_path.parent).as_posix() for p in manifest_path.parent.rglob('*') if p.is_file() and p.name != 'manifest.json'}
        require(actual == inventory.keys(), f'{key}: inventory/files mismatch')
        require(manifest['entry'] in inventory, f'{key}: missing entry')
        namespace, slug = manifest['name'].split('/')
        prefixes = [f'templates/catalogue/{namespace}/{slug}/', f'assets/controllers/catalogue/{namespace}/{slug}_controller.js', f'assets/controllers/catalogue/{namespace}/{slug}/', f'assets/styles/catalogue/{namespace}/{slug}/', f'assets/catalogue/{namespace}/{slug}/', 'meta/']
        for name, entry in inventory.items():
            safe_path(name)
            require(any(name.startswith(prefix) for prefix in prefixes), f'{key}: unowned path {name}')
            path = manifest_path.parent / name
            require(not path.is_symlink(), 'symlink fixture')
            payload = path.read_bytes()
            require(hashlib.sha256(payload).hexdigest() == entry['sha256'], f'{key}: hash mismatch {name}')
            require(len(payload) == entry['bytes'], f'{key}: byte mismatch {name}')
            inventory_count += 1
            if name.startswith('templates/'):
                logical = name[len('templates/'):]
                require(logical not in template_paths, f'duplicate template {logical}')
                template_paths[logical] = (key, path)
        if manifest['kind'] != 'theme':
            require('propsSchema' in manifest and 'defaults' in manifest, 'missing props contract')
            props_schema = read_json(manifest_path.parent / manifest['propsSchema'])
            validate(manifest['defaults'], props_schema)
            for example in read_json(manifest_path.parent / 'meta/examples.json'):
                validate(example['props'], props_schema)
        else:
            require(manifest['entry'] == 'meta/theme.json' and not manifest['controllers'], 'invalid theme envelope')
            theme = read_json(manifest_path.parent / manifest['entry'])
            validate(theme, read_json(SCHEMAS / 'theme.schema.json'))
            font_ids = {font['id'] for font in theme['fonts']}
            require(len(font_ids) == len(theme['fonts']), 'duplicate font id')
            require(theme['typography']['bodyFontId'] in font_ids and theme['typography']['headingFontId'] in font_ids, 'unknown font')
            fragment = next(manifest_path.parent.rglob('theme.fragment.css')).read_text()
            match = re.search(r'/\* styleguide-theme-v1:([A-Za-z0-9+/=]+) \*/', fragment)
            require(match is not None, 'missing round-trip metadata')
            require(json.loads(base64.b64decode(match[1])) == theme, 'round-trip metadata mismatch')
            for token, color in theme['colors'].items():
                require(f'--color-{token}: {color};' in fragment, f'missing colour {token}')
        for controller in manifest['controllers']:
            require(controller['path'] in inventory, 'missing controller file')
            require(controller['identifier'] not in controller_ids, 'controller collision')
            relative = controller['path'].removeprefix('assets/controllers/').removesuffix('_controller.js')
            expected = relative.replace('/', '--').replace('_', '-')
            require(controller['identifier'] == expected, 'controller path/identifier mismatch')
            controller_ids.add(controller['identifier'])

    def closure(key, stack=()):
        require(key not in stack, 'dependency cycle')
        require(key in manifests, f'missing dependency {key}')
        found = {key}
        for dep in manifests[key][1]['dependencies']:
            found |= closure((dep['name'], dep['version']), stack + (key,))
        versions = {}
        for dep_name, dep_version in found:
            require(dep_name not in versions or versions[dep_name] == dep_version, 'version conflict')
            versions[dep_name] = dep_version
        return found

    for key, (directory, manifest) in manifests.items():
        allowed = closure(key)
        for path in directory.rglob('*.twig'):
            for target in re.findall(r"{%\s*include\s+['\"]([^'\"]+)['\"]", path.read_text()):
                require(target in template_paths, f'missing include {target}')
                require(template_paths[target][0] in allowed, 'undeclared include dependency')
        composition_path = directory / 'meta/composition.json'
        if composition_path.exists():
            graph = read_json(composition_path)
            validate(graph, read_json(SCHEMAS / 'composition.schema.json'))
            for node in walk_nodes(graph['root']):
                if node['type'] == 'reference':
                    target = (node['name'], node['version'])
                    require(target in allowed, 'undeclared composition dependency')
                    target_dir, target_manifest = manifests[target]
                    validate(node['props'], read_json(target_dir / target_manifest['propsSchema']))

    validate(read_json(ROOT / 'agent-kit/examples/empty-consumer-lock.json'), read_json(SCHEMAS / 'lock.schema.json'))
    for bad_path in ['../secret', '/etc/passwd', 'C:/secret', 'a/../../b', 'a\\b', 'a//b', './a', 'a/./b']:
        try:
            safe_path(bad_path)
        except AssertionError:
            continue
        raise AssertionError(f'unsafe path accepted: {bad_path}')
    for number in range(9):
        require(len(list((ROOT / '.codex/prompts').glob(f'M{number:02}-*.md'))) == 1, f'missing prompt M{number:02}')
    required_docs = ['PRODUCT', 'ARCHITECTURE', 'CONTRACTS', 'SECURITY', 'OPERATIONS', 'MILESTONES', 'ACCEPTANCE', 'SOURCES']
    for name in required_docs:
        require((ROOT / 'docs/styleguide' / f'{name}.md').is_file(), f'missing document {name}')
    print(f'PASS: {len(parsed)} JSON documents parsed; {len(manifests)} package schemas validated; {inventory_count} payload hashes/byte counts verified.')
    print('PASS: props/examples, theme round-trip metadata, controllers, includes, composition references, dependency graph and path rejection fixtures.')
    print('PASS: milestone prompts and required documents present.')
    print('NOT RUN: PHP/Twig, Tailwind/esbuild, Docker, browser isolation, full external JSON Schema validator or consumer installation.')


if __name__ == '__main__':
    main()
