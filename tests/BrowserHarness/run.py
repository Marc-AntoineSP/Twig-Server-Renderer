#!/usr/bin/env python3
"""Disposable browser lifecycle. State and credentials never come from a browser prefix."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import signal
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
RUNS = ROOT / 'var/browser-runs'
SERVICES = ['database', 'render-worker', 'build-worker', 'app', 'preview-consumer']


def execute(command, *, capture=False, check=True):
    environment = {k: v for k, v in os.environ.items() if not k.startswith(('BROWSER_', 'COMPOSE_', 'STUDIO_'))}
    child = subprocess.Popen(command, cwd=ROOT, env=environment, text=True, start_new_session=True,
                             stdout=subprocess.PIPE if capture else None, stderr=subprocess.PIPE if capture else None)
    try:
        stdout, stderr = child.communicate()
    except BaseException:
        os.killpg(child.pid, signal.SIGTERM)
        try: child.wait(timeout=10)
        except subprocess.TimeoutExpired:
            os.killpg(child.pid, signal.SIGKILL)
            child.wait()
        raise
    result = subprocess.CompletedProcess(command, child.returncode, stdout, stderr)
    if check and child.returncode:
        # Captured configuration/inspect can contain credentials; never echo it.
        raise RuntimeError(f'{command[0]} {command[1]} failed (exit {result.returncode}).')
    return result


class Run:
    def __init__(self, run_id=None):
        if run_id is None:
            run_id = 'browser-' + secrets.token_hex(12)
            self.directory = RUNS / run_id
            self.directory.mkdir(parents=True, mode=0o700)
            for name in ['private', 'artifacts']:
                (self.directory / name).mkdir(mode=0o700)
            shutil.copytree(ROOT / 'tests/Browser/baselines', self.directory / 'baselines', dirs_exist_ok=True)
            private = self.directory / 'private'
            ownership = {'runId': run_id, 'secret': secrets.token_hex(32)}
            (private / 'ownership.json').write_text(json.dumps(ownership))
            env = {'BROWSER_RUN_ID': run_id, 'BROWSER_RUN_DIRECTORY': str(self.directory),
                   'BROWSER_APP_SECRET': secrets.token_hex(32), 'BROWSER_DB_PASSWORD': secrets.token_hex(24),
                   'BROWSER_WORKER_TOKEN': secrets.token_hex(32), 'LOCAL_UID': str(os.getuid()), 'LOCAL_GID': str(os.getgid())}
            (private / 'compose.env').write_text(''.join(f'{k}={v}\n' for k, v in env.items()))
            (self.directory / 'state.json').write_text(json.dumps({'runId': run_id, 'root': str(ROOT),
                'ownerHash': hashlib.sha256((private / 'ownership.json').read_bytes()).hexdigest()}))
            for path in [private/'ownership.json', private/'compose.env', self.directory/'state.json']:
                path.chmod(0o600)
        if not re.fullmatch(r'browser-[a-f0-9]{24}', run_id):
            raise RuntimeError('Use the exact run ID printed by browser-start.')
        self.run_id = run_id
        self.directory = RUNS / run_id
        if self.directory.is_symlink() or self.directory.resolve().parent != RUNS.resolve():
            raise RuntimeError('Run must be an owned directory under var/browser-runs.')
        state = json.loads((self.directory / 'state.json').read_text())
        owner_path = self.directory / 'private/ownership.json'
        if state != {'runId': run_id, 'root': str(ROOT), 'ownerHash': hashlib.sha256(owner_path.read_bytes()).hexdigest()}:
            raise RuntimeError('Run ownership receipt mismatch; refusing all operations.')
        self.dc = ['docker', 'compose', '--project-directory', str(ROOT), '--env-file', str(self.directory / 'private/compose.env'),
                   '-f', str(ROOT / 'compose.browser.yaml'), '-p', run_id]
        self.validate_configuration()

    def compose(self, *arguments, **kwargs):
        return execute(self.dc + list(arguments), **kwargs)

    def validate_configuration(self):
        config = json.loads(self.compose('--profile', 'tools', 'config', '--format', 'json', capture=True).stdout)
        if config['name'] != self.run_id or set(config['services']) != set(SERVICES + ['browser']):
            raise RuntimeError('Unexpected browser topology.')
        for name, network in config['networks'].items():
            if network.get('name') != f'{self.run_id}_{name}' or not network.get('internal') or network.get('external'):
                raise RuntimeError('Only run-owned internal networks are allowed.')
        for name, volume in config['volumes'].items():
            if volume.get('name') != f'{self.run_id}_{name}' or volume.get('external') or volume.get('driver_opts'):
                raise RuntimeError('Only run-owned volumes are allowed.')
        expected = {'runtime': '/app/var', 'blobs': '/app/var/catalogue'}
        for name, service in config['services'].items():
            if service.get('ports') or service.get('privileged') or service.get('network_mode') or service.get('env_file'):
                raise RuntimeError('Published ports, external environments and privileged test services are forbidden.')
            for volume in service.get('volumes', []):
                if volume['type'] == 'bind' and not Path(volume['source']).resolve().is_relative_to(self.directory.resolve()):
                    raise RuntimeError('Browser services may only bind their private run directory.')
            if name in ['app', 'preview-consumer']:
                actual = {v['source']: v['target'] for v in service['volumes'] if v['type'] == 'volume'}
                if actual != expected or service['environment']['STUDIO_TEST_RUN_ID'] != self.run_id:
                    raise RuntimeError('App and consumer must share exactly this run runtime/blob storage.')
                if service['environment']['MAILER_DSN'] != 'null://null' or '@database:5432/browser?' not in service['environment']['DATABASE_URL']:
                    raise RuntimeError('Unexpected side effects or database target.')
                if service['environment']['RENDER_WORKER_URL'] != 'http://render-worker:8080' or service['environment']['BUILD_WORKER_URL'] != 'http://build-worker:8080':
                    raise RuntimeError('Only this run\'s internal worker endpoints are allowed.')

    def verify_resources(self):
        for kind in ['volume', 'network']:
            ids = execute(['docker', kind, 'ls', '-q', '--filter', f'label=com.docker.compose.project={self.run_id}'], capture=True).stdout.split()
            for resource in ids:
                inspected = json.loads(execute(['docker', kind, 'inspect', resource], capture=True).stdout)[0]
                labels = inspected.get('Labels') or {}
                if labels.get('studio.browser.run') != self.run_id or not inspected['Name'].startswith(self.run_id + '_'):
                    raise RuntimeError('Resource ownership mismatch; refusing mutation/teardown.')
                if (kind == 'network' and not inspected.get('Internal')) or (kind == 'volume' and inspected.get('Options')):
                    raise RuntimeError('External runtime network/storage options are forbidden.')
        ids = execute(['docker', 'ps', '-aq', '--filter', f'label=com.docker.compose.project={self.run_id}'], capture=True).stdout.split()
        for container in ids:
            inspected = json.loads(execute(['docker', 'inspect', container], capture=True).stdout)[0]
            if inspected['HostConfig'].get('PortBindings'):
                raise RuntimeError('Test containers cannot publish host ports.')
            for mount in inspected['Mounts']:
                if mount['Type'] == 'volume' and mount['Name'] not in [f'{self.run_id}_{v}' for v in ['database', 'runtime', 'blobs']]:
                    raise RuntimeError('Unexpected mounted volume; refusing mutation/teardown.')
                if mount['Type'] == 'bind' and not Path(mount['Source']).resolve().is_relative_to(self.directory.resolve()):
                    raise RuntimeError('Unexpected bind mount; refusing mutation/teardown.')
            if any(not key.startswith(self.run_id + '_') for key in inspected['NetworkSettings']['Networks']):
                raise RuntimeError('Unexpected container network.')
        print(f'Ownership verified: {self.run_id} (database, blobs, runtime, internal networks).', flush=True)

    def app(self, *arguments):
        return self.compose('run', '--rm', '--no-deps', 'app', *arguments)

    def check(self):
        self.verify_resources()
        self.app('composer', 'validate', '--strict')
        for command, paths in [('lint:container', []), ('lint:yaml', ['config']), ('lint:twig', ['templates'])]:
            self.app('php', 'bin/console', command, *paths)
        self.app('php', 'bin/console', 'doctrine:database:create', '--env=test', '--if-not-exists')
        self.app('php', 'bin/console', 'doctrine:migrations:migrate', '--env=test', '--no-interaction')
        self.compose('run', '--rm', '--no-deps', '-e', 'APP_ENV=test', '-e', 'MCP_PUBLIC_BASE_URL=http://127.0.0.1:8095', '-e', 'MCP_PUBLIC_HOST=localhost', 'app', 'php', 'bin/phpunit')

    def start(self, build=True):
        print(f'Browser run: {self.run_id}\nArtifacts: {self.directory / "artifacts"}', flush=True)
        if build:
            self.compose('--profile', 'tools', 'build', 'app', 'browser', 'render-worker', 'build-worker')
        self.compose('up', '-d', '--wait', 'database', 'render-worker', 'build-worker')
        self.verify_resources()
        self.app('php', 'tests/BrowserHarness/initialize.php')
        self.app('php', 'tools/mcp/init.php')
        self.app('php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction')
        self.compose('up', '-d', '--wait', 'app', 'preview-consumer')
        self.seed()

    def seed(self):
        self.verify_resources()
        self.app('php', 'bin/console', 'styleguide:test:accounts')
        for family in ['inter', 'lora', 'jetbrains-mono', 'atkinson-hyperlegible', 'manrope']:
            self.app('php', 'bin/console', 'styleguide:fonts:sync', '--family='+family)
        self.app('php', 'bin/console', 'styleguide:test:seed')

    def test(self, arguments, update_baselines=False, visual_probe=False):
        self.verify_resources()
        if any(arg.startswith(('--update-snapshots', '-u')) for arg in arguments):
            raise RuntimeError('Use make browser-baselines for deliberate baseline updates.')
        if update_baselines and os.environ.get('CI'):
            raise RuntimeError('Baseline updates are forbidden in CI.')
        if visual_probe and update_baselines:
            raise RuntimeError('Visual probes cannot update baselines.')
        before = {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in (self.directory / 'baselines').rglob('*.png')}
        invocation = 'invocation-' + secrets.token_hex(4)
        output = self.directory / 'artifacts' / invocation
        output.mkdir(mode=0o700)
        env = ['-e', 'STUDIO_BROWSER_ARTIFACTS=/artifacts/'+invocation]
        if update_baselines: env += ['-e', 'STUDIO_UPDATE_BASELINES=1']
        if visual_probe: env += ['-e', 'STUDIO_VISUAL_PROBE=1']
        result = self.compose('run', '--rm', '--no-deps', *env, 'browser', 'bash', '-c',
                     'mkdir -p var/library-staging var/browser; cp -r /app/font-fixtures/fonts var/library-staging/; exec npm run test:browser -- "$@"', 'browser', *arguments, capture=True, check=False)
        log = self.redact((result.stdout or '') + (result.stderr or ''))[-262144:]
        (output / 'browser.log').write_text(log)
        self.sanitize_artifacts()
        print(log[-32768:], flush=True)
        if visual_probe:
            after = {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in (self.directory / 'baselines').rglob('*.png')}
            if result.returncode == 0 or 'toHaveScreenshot' not in log or 'pixels' not in log or before != after:
                raise RuntimeError('Visual probe did not demonstrate a rejected pixel difference with unchanged baselines.')
            print('PASS: deliberate heading regression detected; baseline hashes unchanged.', flush=True)
        elif result.returncode: raise RuntimeError(f'Browser checks failed (exit {result.returncode}); see {output / "browser.log"}.')
        if update_baselines:
            shutil.copytree(self.directory / 'baselines', ROOT / 'tests/Browser/baselines', dirs_exist_ok=True)
            print('Baseline candidates copied to tests/Browser/baselines. Review the image diff before committing.', flush=True)

    def redact(self, text):
        for file in ['ownership.json', 'accounts.json']:
            path = self.directory / 'private' / file
            if path.exists():
                values = json.loads(path.read_text())
                rows = values.values() if file == 'accounts.json' else [values]
                for row in rows:
                    for key in ['password', 'composeToken', 'secret']:
                        if row.get(key): text = text.replace(row[key], '[redacted]')
        text = re.sub(r'sg[ct]_[a-f0-9]{64}', '[redacted-token]', text)
        text = re.sub(r'eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+', '[redacted-jwt]', text)
        return re.sub(r'\b[a-f0-9]{48,}\b', '[redacted-secret-or-hash]', text)

    def sanitize_artifacts(self):
        for path in (self.directory / 'artifacts').rglob('*'):
            if path.is_file() and path.suffix in ['.json', '.md', '.txt', '.log']:
                path.write_text(self.redact(path.read_text())[-262144:])

    def cleanup(self):
        self.verify_resources()
        self.compose('--profile', 'tools', 'down', '--volumes', '--remove-orphans', '--timeout', '10')
        # Compose --rmi local can also remove shared app/browser tags. Only remove these exact owned tags.
        for service in ['render-worker', 'build-worker']:
            tag = f'{self.run_id}-{service}:latest'
            found = execute(['docker', 'image', 'ls', '-q', '--filter', 'reference='+tag], capture=True).stdout.strip()
            if found:
                metadata = json.loads(execute(['docker', 'image', 'inspect', tag], capture=True).stdout)[0]
                labels = metadata['Config'].get('Labels') or {}
                if labels.get('com.docker.compose.project') != self.run_id or labels.get('com.docker.compose.service') != service:
                    raise RuntimeError('Worker image ownership mismatch; refusing image removal.')
                execute(['docker', 'image', 'rm', tag])
        self.sanitize_artifacts()
        # Only private files inside this exact owned run. No prefix-based catalogue deletion.
        for file in (self.directory / 'private').iterdir():
            if file.is_file() and not file.is_symlink():
                file.unlink()
        (self.directory / 'CLEANED').write_text('Run containers, volumes, networks, worker tags and private credentials removed. Reusable app/browser image caches are retained.\n')
        print(f'Cleaned {self.run_id}; sanitized artifacts retained at {self.directory / "artifacts"}.', flush=True)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['run', 'start', 'seed', 'test', 'verify', 'cleanup', 'check'])
    parser.add_argument('--run-id')
    parser.add_argument('--keep', action='store_true')
    parser.add_argument('--no-build', action='store_true')
    parser.add_argument('--update-baselines', action='store_true')
    parser.add_argument('--visual-probe', action='store_true')
    parser.add_argument('--with-checks', action='store_true')
    options, arguments = parser.parse_known_args()
    if arguments[:1] == ['--']: arguments = arguments[1:]
    if options.action not in ['run', 'start'] and not options.run_id:
        parser.error('This action requires --run-id with an exact owned run ID.')
    run = Run(options.run_id)
    lock = (run.directory / '.lifecycle.lock').open('w')
    try: fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError: raise RuntimeError('This run is busy. Use a separate run ID for concurrent tests.')
    automatic_cleanup = options.action == 'run' or (options.action == 'start' and not options.keep)
    try:
        if options.action in ['run', 'start']:
            run.start(not options.no_build)
            if options.with_checks: run.check()
            if options.action == 'run': run.test(arguments, options.update_baselines, options.visual_probe)
        elif options.action == 'seed': run.seed()
        elif options.action == 'test': run.test(arguments, options.update_baselines, options.visual_probe)
        elif options.action == 'verify': run.verify_resources()
        elif options.action == 'cleanup': run.cleanup()
        elif options.action == 'check':
            run.check()
    finally:
        if automatic_cleanup and not options.keep:
            try: run.cleanup()
            except Exception:
                print(f'Cleanup incomplete for {run.run_id}. Retained resources require: make browser-cleanup RUN={run.run_id}', file=sys.stderr)
                raise
        elif options.action in ['start', 'run']:
            print(f'Retained owned run {run.run_id}. Remove it with: make browser-cleanup RUN={run.run_id}', flush=True)


if __name__ == '__main__':
    signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(KeyboardInterrupt()))
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyboardInterrupt) as error:
        print(f'Browser harness stopped: {error or "interrupted"}', file=sys.stderr)
        sys.exit(1)
