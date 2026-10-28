"""Prepare/validate the existing immutable production fixture before any database writes."""
import json
from pathlib import Path
import secrets
import subprocess
import sys

directory = Path(sys.argv[1]).resolve()
root = Path(__file__).resolve().parents[2]
if directory.parent != root / 'var' or not directory.name.startswith('appearance-'):
    raise SystemExit('An exact task-owned appearance directory is required.')
private = directory / 'browser-private'
private.mkdir(mode=0o700)
run_id = 'browser-' + secrets.token_hex(12)
(private / 'ownership.json').write_text(json.dumps({'runId': run_id, 'secret': secrets.token_hex(32)}))
overlay = {'services': {name: {'environment': {'STUDIO_TEST_RUN_ID': run_id},
    'volumes': [f'{private}:/run/styleguide-test']} for name in ['app', 'preview-consumer']}, 'networks': {'edge': {'internal': True}}}
(directory / 'ownership.json').write_text(json.dumps(overlay))
command = ['docker', 'compose', '--env-file', str(directory / 'compose.env'), '-f', 'compose.production.yaml', '-f', str(directory / 'ownership.json')]
if len(sys.argv) > 2 and sys.argv[2]: command += ['-f', sys.argv[2]]
config = json.loads(subprocess.check_output(command + ['config', '--format', 'json'], cwd=root))
project = config['name']
if directory.name not in project: raise SystemExit('Production project must belong to this fixture.')
for key in ['volumes', 'networks']:
    for name, resource in config[key].items():
        if resource.get('external') or resource.get('name') != project+'_'+name:
            raise SystemExit('Production fixture cannot share volumes or networks.')
        if key == 'networks' and not resource.get('internal'):
            raise SystemExit('Production test networks must deny external egress.')
for service in config['services'].values():
    for mount in service.get('volumes', []):
        if mount['type'] == 'bind' and not Path(mount['source']).resolve().is_relative_to(directory):
            raise SystemExit('Production fixture cannot mount another runtime directory.')
for secret in config.get('secrets', {}).values():
    if not Path(secret['file']).resolve().is_relative_to(directory): raise SystemExit('Production fixture cannot use working secrets.')
print('Production fixture configuration owns all volumes, networks, secrets and writable mounts.')
