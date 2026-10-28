#!/usr/bin/env python3
"""Live negative checks; only accepts exact owned browser-run receipts."""
import json
import sys
from run import Run, execute

run = Run(sys.argv[1])
other = Run(sys.argv[2])
run.verify_resources()
other.verify_resources()


def sql(query):
    return run.compose('exec', '-T', 'database', 'psql', '-U', 'browser', '-d', 'browser', '-At', '-c', query, capture=True).stdout.strip()


def counts():
    return [sql('SELECT count(*) FROM '+table) for table in ['studio_user', 'catalogue_item', 'draft', 'catalogue_release', 'theme', 'personal_favorite']]


before = counts()
run.seed()
assert counts() == before, 'Repeated seeding changed the fixture volume.'
guard = 'require "vendor/autoload.php"; $k=new App\\Kernel("dev",false); $k->boot(); $db=$k->getContainer()->get("doctrine")->getConnection(); $g=new App\\Testing\\BrowserRunGuard($db);'
assert run.compose('exec', '-T', 'app', 'php', '-r', guard+'$g->verify();', capture=True).returncode == 0

# A flag, a copied account file or the wrong database cannot authorize cleanup.
failure = run.compose('run', '--rm', '--no-deps', '-e', 'STUDIO_TEST_RUN_ID=browser-'+('0'*24), 'app',
                      'php', 'bin/console', 'styleguide:test:accounts', '--cleanup', capture=True, check=False)
assert failure.returncode != 0 and counts() == before

comment = sql("SELECT shobj_description(oid, 'pg_database') FROM pg_database WHERE datname=current_database()")
try:
    sql("COMMENT ON DATABASE browser IS '{\"wrong\":true}'")
    failure = run.compose('exec', '-T', 'app', 'php', 'bin/console', 'styleguide:test:accounts', '--cleanup', capture=True, check=False)
    assert failure.returncode != 0 and counts() == before
finally:
    sql("COMMENT ON DATABASE browser IS '"+comment.replace("'", "''")+"'")

blob = '/app/var/catalogue/.browser-test-ownership.json'
try:
    run.compose('exec', '-T', 'app', 'php', '-r', 'file_put_contents("'+blob+'", "{}");', capture=True)
    try:
        run.test(['tests/Browser/authentication.spec.js', '--project=chromium'])
        raise AssertionError('Browser startup accepted the wrong blob volume.')
    except RuntimeError as failure:
        assert 'Browser checks failed' in str(failure)
        logs = list((run.directory/'artifacts').rglob('browser.log'))
        assert any('no verified disposable database/blob ownership' in p.read_text() for p in logs)
    assert counts() == before, 'Failed browser preflight wrote catalogue rows.'
finally:
    run.compose('exec', '-T', 'app', 'php', '-r', guard+'file_put_contents("'+blob+'", $db->fetchOne("SELECT shobj_description(oid, \'pg_database\') FROM pg_database WHERE datname=current_database()"));', capture=True)

config = json.loads(run.compose('--profile', 'tools', 'config', '--format', 'json', capture=True).stdout)
second = json.loads(other.compose('--profile', 'tools', 'config', '--format', 'json', capture=True).stdout)
development = json.loads(execute(['docker', 'compose', 'config', '--format', 'json'], capture=True).stdout)
for key in ['volumes', 'networks']:
    names = [{v['name'] for v in c[key].values()} for c in [config, second, development]]
    assert not (names[0] & names[1] or names[0] & names[2] or names[1] & names[2])
assert all(not service.get('ports') for service in config['services'].values())
assert counts() == before
print('PASS: repeatable seeds; wrong run/database/blob proofs denied before mutations; two live runs and development configuration have disjoint resources and no test host ports.')
