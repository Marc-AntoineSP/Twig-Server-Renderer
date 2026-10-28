#!/usr/bin/env python3
"""Exercise real failed/SIGTERM commands and verify exact resource removal."""
import json
from pathlib import Path
import re
import signal
import subprocess
import sys
from run import ROOT, execute

for interrupted in [False, True]:
    command = [sys.executable, 'tests/BrowserHarness/run.py', 'run', '--no-build', '--', 'tests/Browser/does-not-exist.spec.js', '--project=chromium']
    child = subprocess.Popen(command, cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    lines = []
    run_id = None
    for line in child.stdout:
        lines.append(line)
        if line.startswith('Browser run: '): run_id = line.strip().split()[-1]
        if interrupted and line.startswith('Ownership verified: '):
            child.send_signal(signal.SIGTERM)
            break
    remaining = child.communicate(timeout=90)[0]
    lines.append(remaining)
    assert child.returncode != 0 and run_id and re.fullmatch(r'browser-[a-f0-9]{24}', run_id)
    directory = ROOT/'var/browser-runs'/run_id
    assert (directory/'CLEANED').is_file(), 'The failed/interrupted command did not finish safe cleanup.'
    assert not list((directory/'private').iterdir()), 'Credentials remained after teardown.'
    for resource in [['docker', 'ps', '-aq'], ['docker', 'volume', 'ls', '-q'], ['docker', 'network', 'ls', '-q']]:
        assert not execute(resource+['--filter', 'label=com.docker.compose.project='+run_id], capture=True).stdout.strip()
    (directory/'artifacts/lifecycle.log').write_text(''.join(lines)[-32768:])
    print(f'PASS: {"SIGTERM" if interrupted else "failed tests"} cleaned exactly {run_id}; credentials and Docker resources removed.')
