#!/usr/bin/env python3
"""Safe read benchmark on an exact existing BrowserHarness run; no working-app target."""
import argparse
import fcntl
import json
import re
from pathlib import Path
import statistics
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))
from tests.BrowserHarness.run import Run


def capture(run, file, script, *arguments):
    result = run.compose('run', '--rm', '--no-deps', 'app', 'php', script, *arguments, capture=True, check=False)
    if result.returncode:
        # Never echo a failed HTTP response, credentials, parameters or profiler data.
        raise RuntimeError(f'{script} failed (exit {result.returncode}); owned runtime logs are available.')
    data = json.loads(result.stdout)
    file.write_text(json.dumps(data, indent=2)+'\n')
    return data


def compare(directory):
    before = json.loads((directory/'read-before.json').read_text())
    after = json.loads((directory/'read-after.json').read_text())
    for key in ['seed','runtime','conditions','repetitions']:
        if before[key] != after[key]: raise RuntimeError(f'Benchmark {key} differs; use identical runtime and conditions.')
    if before['results'].keys() != after['results'].keys(): raise RuntimeError('Benchmark cases differ.')
    for name, result in after['results'].items():
        if before['results'][name]['request'] != result['request']: raise RuntimeError('Benchmark client parameters differ.')
        for data in [before,after]:
            if not re.fullmatch(r'[a-f0-9]{64}', data['results'][name]['semanticHash']):
                raise RuntimeError('Fingerprints are unavailable or sanitized; compare before browser artifact sanitation and cleanup.')
    report = {'sameCounts': before['counts'] == after['counts'], 'counts': after['counts'], 'runtime': after['runtime'], 'cases': {}}
    for name, result in after['results'].items():
        entry = {'sameResults': before['results'][name]['semanticHash'] == result['semanticHash']}
        for label, source in [('before',before),('after',after)]:
            samples = source['results'][name]['samples']
            entry[label] = {key: {'median': statistics.median(s[key] for s in samples), 'min': min(s[key] for s in samples), 'max': max(s[key] for s in samples)} for key in ['elapsedMs','databaseMs','sqlQueries','responseBytes','writes']}
            http = json.loads((directory/f'http-{label}.json').read_text())['results'][name]['samples']
            entry[label]['httpMs'] = {'median': statistics.median(s['elapsedMs'] for s in http), 'min': min(s['elapsedMs'] for s in http), 'max': max(s['elapsedMs'] for s in http)}
        report['cases'][name] = entry
    report['legacy'] = {label: json.loads((directory/f'legacy-{label}.json').read_text()) for label in ['before','after']}
    (directory/'comparison.json').write_text(json.dumps(report, indent=2)+'\n')
    if not report['sameCounts'] or not all(c['sameResults'] for c in report['cases'].values()):
        raise RuntimeError('Dataset or semantic fingerprints differ. Inspect the comparison before making a performance claim.')
    print('Same counts and all result fingerprints verified; comparison.json retained.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['seed','measure','compare'])
    parser.add_argument('--run-id', required=True)
    parser.add_argument('--label', choices=['before','after','sample'], default='sample')
    args = parser.parse_args()
    run = Run(args.run_id)
    with (run.directory/'.lifecycle.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        run.verify_resources()
        if args.action == 'compare':
            compare(run.directory/'artifacts'); return
        # Source is copied into images; always measure the current snapshot and clear
        # the serving APP_DEBUG=0 cache. Leave the database and blob store intact.
        run.compose('--profile','tools','build','app')
        run.app('php','bin/console','cache:clear')
        run.compose('up','-d','--wait','--force-recreate','app','preview-consumer')
        if args.action == 'seed':
            run.app('php','tests/ReadPerformance/seed.php'); return
        directory = run.directory/'artifacts'
        paths = {kind: directory/f'{kind}-{args.label}.json' for kind in ['read','http','legacy']}
        if any(path.exists() for path in paths.values()): raise RuntimeError('This label already has evidence; use a fresh run rather than overwriting it.')
        run.app('php','tests/ReadPerformance/legacy.php','restore')
        capture(run, paths['read'], 'tests/ReadPerformance/measure.php', '5')
        capture(run, paths['http'], 'tests/ReadPerformance/http.php')
        run.app('php','tests/ReadPerformance/legacy.php','detach')
        try: capture(run, paths['legacy'], 'tests/ReadPerformance/legacy-probe.php')
        finally: run.app('php','tests/ReadPerformance/legacy.php','restore')
        print(f'Retained non-secret evidence: {directory}. Cleanup: make browser-cleanup RUN={run.run_id}')


if __name__ == '__main__':
    try: main()
    except (RuntimeError, OSError, ValueError) as error:
        print(str(error), file=sys.stderr); sys.exit(1)
