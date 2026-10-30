#!/usr/bin/env python3
"""Reproduce publication timing/failure evidence on an exact disposable browser run."""
import argparse
import fcntl
import json
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT))
from tests.BrowserHarness.run import Run


def capture(run,path,script,*args):
    result=run.compose('run','--rm','--no-deps','app','php',script,*args,capture=True,check=False)
    if result.returncode:raise RuntimeError(f'{script} failed (exit {result.returncode}); inspect the exact owned app logs. No response/credentials are printed.')
    path.write_text(json.dumps(json.loads(result.stdout),indent=2)+'\n')
    print(result.stderr or '',flush=True)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--run-id',required=True)
    parser.add_argument('--faults-only',action='store_true',help='For an already seeded/measured owned run; never reseeds or repeats a version.')
    args=parser.parse_args();run=Run(args.run_id)
    with (run.directory/'.lifecycle.lock').open('w') as lock:
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB);run.verify_resources()
        out=run.directory/'artifacts'
        expected=['publication-worker-unavailable.json','publication-worker-retry.json']
        if not args.faults_only:expected+=['publication-baseline.json']
        if any((out/name).exists() for name in expected):raise RuntimeError('Evidence already exists; use a fresh owned run rather than overwriting it.')
        run.compose('--profile','tools','build','app')
        run.app('php','bin/console','cache:clear')
        run.compose('up','-d','--wait','--force-recreate','app','preview-consumer')
        if not args.faults_only:
            run.app('php','tests/PublicationBenchmark/seed.php')
            capture(run,out/'publication-baseline.json','tests/PublicationBenchmark/measure.php')
        run.compose('stop','build-worker')
        try:capture(run,out/'publication-worker-unavailable.json','tests/PublicationBenchmark/fault.php','unavailable')
        finally:run.compose('up','-d','--wait','build-worker')
        capture(run,out/'publication-worker-retry.json','tests/PublicationBenchmark/fault.php','retry')
        print('Retained publication evidence at '+str(out),flush=True)
        print('Cleanup: make browser-cleanup RUN='+run.run_id,flush=True)


if __name__=='__main__':
    try:main()
    except (RuntimeError,OSError,ValueError) as error:
        print(str(error),file=sys.stderr);sys.exit(1)
