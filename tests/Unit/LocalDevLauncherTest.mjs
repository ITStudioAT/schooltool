import assert from 'node:assert/strict';
import { fork, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { assertLocalEnvironment, existingWorkspaceSession, ownsDevListener, prepareWorkspaceEnvironment, shouldOpenLocalDev,
    workspacePorts } from '../../scripts/local-dev.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const launcher = path.join(root, 'scripts/local-dev.mjs');
const helpers = path.join(root, 'scripts/git_helpers.ps1');
const temporaryRoot = os.tmpdir();
const localEnvironment = 'APP_ENV=local\nAPP_KEY=fixture-private-key\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_DATABASE=owned_fixture_not_connected\nDB_PASSWORD=fixture-secret-do-not-log\nSESSION_COOKIE=owned-shared-session\nREDIS_PREFIX=owned_shared_\n';

test('opens an explicit development launch but keeps automatic sessions silent', () => {
    assert.equal(shouldOpenLocalDev([]), true);
    assert.equal(shouldOpenLocalDev(['--no-open']), false);
    assert.equal(shouldOpenLocalDev(['--session']), false);
    assert.equal(shouldOpenLocalDev(['--session', '--open']), true);
    assert.equal(shouldOpenLocalDev(['--session', '--open', '--no-open']), false);
});

function git(project, ...argumentsList) {
    const result = spawnSync('git', argumentsList, { cwd: project, encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, result.stderr);
    return result.stdout.trim();
}

function ownedFixture() {
    const directory = fs.mkdtempSync(path.join(temporaryRoot, 'local-dev-test-'));
    const project = path.join(directory, 'main');
    fs.mkdirSync(project);
    return { directory, project, cleanup() {
        assert.ok(path.resolve(directory).startsWith(path.resolve(temporaryRoot) + path.sep));
        assert.equal(fs.lstatSync(directory).isSymbolicLink(), false);
        fs.rmSync(directory, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
    } };
}

test('derives only missing feature configuration and preserves existing private settings', () => {
    const fixture = ownedFixture();
    const feature = path.join(fixture.directory, 'feature');
    fs.mkdirSync(feature);
    fs.writeFileSync(path.join(fixture.project, '.env'), localEnvironment);
    try {
        const ports = workspacePorts(feature, fixture.project);
        prepareWorkspaceEnvironment(feature, fixture.project, ports);
        const contents = fs.readFileSync(path.join(feature, '.env'), 'utf8');
        assert.match(contents, /DB_PASSWORD=fixture-secret-do-not-log/);
        assert.match(contents, new RegExp(`APP_URL=http://localhost:${ports.server}`));
        assert.match(contents, /SESSION_COOKIE=owned-shared-session/);
        assert.match(contents, /REDIS_PREFIX=owned_shared_/);
        fs.appendFileSync(path.join(feature, '.env'), '\nPRIVATE_SETTING=preserve\n');
        const existing = fs.readFileSync(path.join(feature, '.env'), 'utf8');
        prepareWorkspaceEnvironment(feature, fixture.project, ports);
        assert.equal(fs.readFileSync(path.join(feature, '.env'), 'utf8'), existing);
        assert.equal(fs.readFileSync(path.join(fixture.project, '.env'), 'utf8'), localEnvironment);
    } finally { fixture.cleanup(); }
});

test('profile navigation shows the chosen VS Code worktree and preserves Git/dev when the editor is unavailable', { skip: process.platform !== 'win32' }, () => {
    const fixture = ownedFixture();
    const originalProject = fixture.project;
    fixture.project = path.join(fixture.directory, 'main with spaces');
    fs.renameSync(originalProject, fixture.project);
    const scripts = path.join(fixture.project, 'scripts');
    fs.mkdirSync(scripts);
    const quote = filename => filename.replaceAll("'", "''");
    fs.copyFileSync(path.join(root, 'scripts/install_powershell_helpers.ps1'), path.join(scripts, 'install_powershell_helpers.ps1'));
    fs.writeFileSync(path.join(scripts, 'git_workflow.ps1'), [
        'param([string]$Command, [string[]]$CommandArguments)',
        ". '" + quote(helpers) + "'",
        'if ($CommandArguments.Count -gt 0) { & $Command -Name $CommandArguments[0] } else { & $Command }',
    ].join('\n'));
    fs.writeFileSync(path.join(fixture.project, 'composer.json'), '{}');
    git(fixture.project, 'init', '--initial-branch=main');
    git(fixture.project, 'config', 'user.email', 'owned@example.test');
    git(fixture.project, 'config', 'user.name', 'Owned Test');
    git(fixture.project, 'config', 'commit.gpgsign', 'false');
    git(fixture.project, 'add', '.');
    git(fixture.project, 'commit', '-m', 'Owned editor fixture');
    git(fixture.project, 'remote', 'add', 'origin', 'https://github.com/ITStudioAT/schooltool.git');
    const feature = path.resolve(fixture.project + '-features/helpers');
    git(fixture.project, 'worktree', 'add', '-b', 'feature/helpers', feature);
    const head = git(fixture.project, 'rev-parse', 'HEAD');
    const fakeBin = path.join(fixture.project, '.git/fake code');
    fs.mkdirSync(fakeBin);
    const log = path.join(fixture.project, '.git/editor-calls.jsonl');
    const capture = path.join(fakeBin, 'capture.mjs');
    fs.writeFileSync(capture, "import fs from 'node:fs';\nfs.appendFileSync(" + JSON.stringify(log)
        + ", JSON.stringify(process.argv.slice(2))+'\\n');\nprocess.exit(Number(process.env.OWNED_EDITOR_EXIT ?? 0));\n");
    fs.writeFileSync(path.join(fakeBin, 'code.cmd'), '@echo off\n"' + process.execPath + '" "' + capture + '" %*\nexit /b %errorlevel%\n');
    fs.mkdirSync(path.join(fixture.project, '.git/schooltool-dev'));
    const controllerPath = path.join(fixture.project, '.git/schooltool-dev/controller.json');
    fs.writeFileSync(controllerPath, 'owned-controller-preserved');
    try {
        for (const shell of ['powershell.exe', 'pwsh.exe']) {
            for (const scenario of ['vscode', 'outside', 'no-cli', 'cli-failed']) {
                fs.writeFileSync(log, '');
                const script = [
                    "$ErrorActionPreference='Stop'",
                    "$PROFILE=[pscustomobject]@{CurrentUserCurrentHost=(Join-Path (Get-Location) '.git/editor-profile.ps1')}",
                    "& './scripts/install_powershell_helpers.ps1' -DocumentsDirectory (Join-Path (Get-Location) '.git/documents')",
                    '. $PROFILE.CurrentUserCurrentHost',
                    "$env:PATH='" + quote(fakeBin) + "'+[IO.Path]::PathSeparator+$env:PATH",
                    "$env:TERM_PROGRAM='" + (scenario === 'outside' ? 'ordinary-shell' : 'vscode') + "'",
                    '$env:VSCODE_IPC_HOOK_CLI=$null',
                    "$env:OWNED_EDITOR_EXIT='" + (scenario === 'cli-failed' ? '7' : '0') + "'",
                    scenario === 'no-cli'
                        ? "function Get-Command { param($Name, $CommandType, $ErrorAction); if ($Name -ceq 'code') { return }; Microsoft.PowerShell.Core\\Get-Command @PSBoundParameters }"
                        : '',
                    "try { gitwork '../invalid'; throw 'INVALID_SELECTION_ALLOWED' } catch { if ($_.Exception.Message -eq 'INVALID_SELECTION_ALLOWED') { throw } }",
                    'gitwork helpers',
                    "if ((Get-Location).Path -ine '" + quote(feature) + "') { throw 'Feature navigation failed.' }",
                    'gitmain',
                    "if ((Get-Location).Path -ine '" + quote(fixture.project) + "') { throw 'Main navigation failed.' }",
                    "Write-Host 'EDITOR_NAVIGATION_COMPLETED'",
                ].join('\n');
                const result = spawnSync(shell, ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', script],
                    { cwd: fixture.project, encoding: 'utf8', timeout: 30000, windowsHide: true });
                assert.equal(result.status, 0, result.stdout + result.stderr);
                assert.match(result.stdout, /EDITOR_NAVIGATION_COMPLETED/);
                const calls = fs.readFileSync(log, 'utf8').trim().split(/\r?\n/).filter(Boolean).map(line => JSON.parse(line));
                if (scenario === 'vscode') {
                    assert.deepEqual(calls, [
                        ['--add', feature],
                        ['--reuse-window', '--goto', path.join(feature, 'composer.json')],
                        ['--add', fixture.project],
                        ['--reuse-window', '--goto', path.join(fixture.project, 'composer.json')],
                    ]);
                } else if (scenario === 'cli-failed') {
                    assert.deepEqual(calls, [['--add', feature], ['--add', fixture.project]]);
                    assert.match(result.stdout, /Git\/dev selection succeeded/);
                } else {
                    assert.deepEqual(calls, []);
                    if (scenario === 'no-cli') { assert.match(result.stdout, /CLI is unavailable/); }
                }
                assert.equal(git(fixture.project, 'rev-parse', 'HEAD'), head);
                assert.equal(git(feature, 'rev-parse', 'HEAD'), head);
                assert.equal(fs.readFileSync(controllerPath, 'utf8'), 'owned-controller-preserved');
                const selection = JSON.parse(fs.readFileSync(path.join(fixture.project, '.git/schooltool-dev/selection.json'), 'utf8'));
                assert.equal(path.resolve(selection.project), fixture.project);
            }
        }
    } finally { fixture.cleanup(); }
});

test('rejects nonlocal, preview and incomplete configurations', () => {
    const values = { APP_ENV: 'local', APP_KEY: 'private', DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_DATABASE: 'owned' };
    for (const invalid of [{ APP_ENV: 'production' }, { DB_HOST: 'remote.example' }, { DB_URL: 'mysql://remote.example' },
        { SCHOOLTOOL_PREVIEW_INSTANCE: 'true' }, { APP_KEY: '' }]) {
        assert.throws(() => assertLocalEnvironment({ ...values, ...invalid }));
    }
});

test('migrates only the previously generated worktree session settings to the shared browser origin', () => {
    const fixture = ownedFixture();
    const feature = path.join(fixture.directory, 'feature');
    fs.mkdirSync(feature);
    fs.writeFileSync(path.join(fixture.project, '.env'), localEnvironment);
    const suffix = createHash('sha256').update(path.resolve(feature).replaceAll('\\', '/').toLowerCase())
        .digest('hex').slice(0, 12);
    const target = path.join(feature, '.env');
    fs.writeFileSync(target, localEnvironment.replace('SESSION_COOKIE=owned-shared-session', `SESSION_COOKIE=schooltool_${suffix}_session`)
        .replace('REDIS_PREFIX=owned_shared_', `REDIS_PREFIX=schooltool_${suffix}_`)
        + '\nAPP_URL=http://localhost:8343\nPRIVATE_SETTING=preserved\n');
    try {
        const values = prepareWorkspaceEnvironment(feature, fixture.project, workspacePorts(feature, fixture.project));
        assert.equal(values.APP_URL, 'http://localhost:8000');
        assert.equal(values.SESSION_COOKIE, 'owned-shared-session');
        assert.equal(values.REDIS_PREFIX, 'owned_shared_');
        assert.equal(values.PRIVATE_SETTING, 'preserved');
        assert.equal(values.DB_PASSWORD, 'fixture-secret-do-not-log');
        assert.equal(fs.readFileSync(path.join(fixture.project, '.env'), 'utf8'), localEnvironment);
        const contents = fs.readFileSync(target, 'utf8');
        prepareWorkspaceEnvironment(feature, fixture.project, workspacePorts(feature, fixture.project));
        assert.equal(fs.readFileSync(target, 'utf8'), contents);
    } finally { fixture.cleanup(); }
});

test('keeps the same browser address between workspaces and never accepts a foreign listener', () => {
    const main = 'C:/owned/main';
    const feature = 'C:/owned/main-features/helpers';
    const ports = workspacePorts(feature, main);
    assert.deepEqual(workspacePorts(main, main), { server: 8000, vite: 5173 });
    assert.deepEqual(ports, workspacePorts(main, main));
    assert.deepEqual(workspacePorts(feature.toUpperCase(), main), ports);
    const foreign = { pid: 11, name: 'php.exe', command: 'php -S 127.0.0.1:8000 C:/foreign/server.php' };
    assert.equal(ownsDevListener(foreign, main, 'server', 8000), false);
    assert.throws(() => existingWorkspaceSession(feature, ports, {
        listeners: [{ port: ports.server, pid: 11 }], processes: [foreign],
    }), /occupied by another process/);
    const backend = { pid: 12, name: 'php.exe', command: `php -S 127.0.0.1:${ports.server} ${feature}/vendor/laravel/framework/src/Illuminate/Foundation/Console/../resources/server.php` };
    assert.throws(() => existingWorkspaceSession(feature, ports, {
        listeners: [{ port: ports.server, pid: 12 }], processes: [backend],
    }), /incomplete/);
});

function writeFixtureApplication(project) {
    const write = (filename, contents) => {
        const target = path.join(project, filename);
        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.writeFileSync(target, contents);
    };
    write('.gitignore', '.env\n/public/hot\n/storage/framework/\n');
    write('scripts/update.php', '<?php echo "Owned fixture preparation\\n";');
    write('artisan', `<?php
if (($argv[1] ?? '') !== 'serve') {
    if (in_array($argv[1] ?? '', ['queue:work','schedule:run'], true)) { file_put_contents(__DIR__.'/background-was-started', 'unsafe'); }
    exit(0);
}
$port = 0;
foreach ($argv as $argument) { if (str_starts_with($argument, '--port=')) { $port = (int) substr($argument, 7); } }
passthru('"'.PHP_BINARY.'" -S 127.0.0.1:'.$port.' "'.__DIR__.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"', $code);
exit($code);
`);
    write('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php', '<?php header("Content-Type: application/json"); echo json_encode(["project" => realpath(__DIR__."/../../../../../../../")]);');
    write('scripts/vite-dev.mjs', `import {spawn} from 'node:child_process';
const child = spawn(process.execPath, [process.cwd()+'/node_modules/vite/bin/vite.js', ...process.argv.slice(2)], {stdio:'inherit'});
child.once('exit', code => process.exit(code ?? 1));`);
    write('node_modules/vite/bin/vite.js', `import fs from 'node:fs';
import http from 'node:http';
const project = process.cwd();
const port = Number(process.argv[process.argv.indexOf('--port')+1]);
http.createServer((request,response) => { response.end(project); }).listen(port,'localhost', () => fs.writeFileSync(project+'/public/hot', 'http://localhost:'+port));`);
    write('package.json', '{"type":"module"}');
    write('public/.gitkeep', '');
}

function workflow(project, command) {
    const bootstrap = `$ErrorActionPreference='Stop'\n. '${helpers.replaceAll('\\', '/').replaceAll("'", "''")}'\nfunction Invoke-SchooltoolLocalPreparation { Write-Host 'Owned preparation mock' }\n${command}`;
    const result = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', bootstrap],
        { cwd: project, encoding: 'utf8', timeout: 60000, windowsHide: true });
    assert.equal(result.status, 0, result.stdout + result.stderr);
}

async function waitForProject(project, main, output) {
    const ports = workspacePorts(project, main, 20000);
    const deadline = Date.now() + 45000;
    while (Date.now() < deadline) {
        try {
            const backend = await fetch(`http://127.0.0.1:${ports.server}/up`, { signal: AbortSignal.timeout(1000) });
            const result = await backend.json();
            const frontend = await fetch(`http://localhost:${ports.vite}/@vite/client`, { signal: AbortSignal.timeout(1000) });
            if (path.resolve(result.project) === path.resolve(project) && path.resolve(await frontend.text()) === path.resolve(project)) {
                return;
            }
        } catch { /* The owned services are still starting. */ }
        await new Promise(resolve => setTimeout(resolve, 250));
    }
    assert.fail(`The matching owned services did not start: ${output().slice(-3000)}`);
}

test('a persistent launcher follows gitmain and gitwork without jobs, duplicate controllers or foreign process cleanup', { timeout: 150000, skip: process.platform !== 'win32' }, async () => {
    const fixture = ownedFixture();
    writeFixtureApplication(fixture.project);
    git(fixture.project, 'init', '--initial-branch=main');
    git(fixture.project, 'config', 'user.email', 'owned@example.test');
    git(fixture.project, 'config', 'user.name', 'Owned Test');
    git(fixture.project, 'config', 'commit.gpgsign', 'false');
    git(fixture.project, 'add', '.');
    git(fixture.project, 'commit', '-m', 'Owned fixture');
    const remote = path.join(fixture.directory, 'remote.git');
    git(fixture.project, 'init', '--bare', remote);
    git(fixture.project, 'remote', 'add', 'origin', remote);
    git(fixture.project, 'push', '-u', 'origin', 'main');
    fs.writeFileSync(path.join(fixture.project, '.env'), localEnvironment);
    workflow(fixture.project, 'gitstart helpers');
    const feature = `${fixture.project}-features/helpers`;
    const controller = fork(launcher, ['--project', fixture.project, '--web-only', '--no-open', '--check-seconds=75', '--check-port-offset=20000'], { cwd: fixture.project, silent: true, windowsHide: true });
    let output = '';
    controller.stdout.on('data', data => { output += data; });
    controller.stderr.on('data', data => { output += data; });
    const exited = new Promise(resolve => controller.once('exit', resolve));
    try {
        await waitForProject(feature, fixture.project, () => output);
        const receiptPath = path.join(fixture.project, '.git/schooltool-dev/controller.json');
        const initialOwner = JSON.parse(fs.readFileSync(receiptPath, 'utf8')).owner;
        const repeated = spawnSync(process.execPath, [launcher, '--project', fixture.project, '--web-only', '--no-open', '--check-port-offset=20000'], { cwd: fixture.project, encoding: 'utf8', timeout: 15000, windowsHide: true });
        assert.equal(repeated.status, 0, repeated.stderr);
        assert.match(repeated.stdout, /no second session/);
        assert.deepEqual(JSON.parse(fs.readFileSync(receiptPath, 'utf8')).owner, initialOwner);
        assert.equal(path.resolve(JSON.parse(fs.readFileSync(path.join(fixture.project, '.git/schooltool-dev/selection.json'), 'utf8')).project), path.resolve(feature));
        workflow(feature, 'gitmain');
        await waitForProject(fixture.project, fixture.project, () => output);
        workflow(fixture.project, 'gitwork helpers');
        await waitForProject(feature, fixture.project, () => output);
        assert.equal(JSON.parse(fs.readFileSync(receiptPath, 'utf8')).owner.pid, controller.pid);
        assert.equal(fs.existsSync(path.join(feature, 'background-was-started')), false);
        assert.equal(fs.existsSync(path.join(fixture.project, 'background-was-started')), false);
        assert.equal(output.includes('fixture-secret-do-not-log'), false);
        assert.equal(git(fixture.project, 'status', '--porcelain'), '');
        assert.equal(git(feature, 'status', '--porcelain'), '');
    } finally {
        if (controller.connected) {
            controller.send({ stop: true });
        }
        await exited;
        fixture.cleanup();
    }
});

test('PowerShell profiles route composer dev to the selected folder and preserve native Composer commands', { skip: process.platform !== 'win32' }, () => {
    const fixture = ownedFixture();
    fs.mkdirSync(path.join(fixture.project, 'scripts'));
    fs.writeFileSync(path.join(fixture.project, 'scripts/git_workflow.ps1'), '');
    fs.writeFileSync(path.join(fixture.project, 'scripts/local-dev.mjs'), '');
    git(fixture.project, 'init', '--initial-branch=main');
    git(fixture.project, 'remote', 'add', 'origin', 'https://github.com/ITStudioAT/schooltool.git');
    const log = path.join(fixture.project, '.git/composer-dev-calls.txt');
    const installer = path.join(fixture.project, 'scripts/install_powershell_helpers.ps1');
    fs.copyFileSync(path.join(root, 'scripts/install_powershell_helpers.ps1'), installer);
    const quote = filename => filename.replaceAll("'", "''");
    try {
        for (const shell of ['powershell.exe', 'pwsh.exe']) {
            for (const override of [false, true]) {
                const script = `$ErrorActionPreference='Stop'
$PROFILE=[pscustomobject]@{CurrentUserCurrentHost=(Join-Path (Get-Location) '.git/profile.ps1')}
& '${quote(installer)}' -DocumentsDirectory (Join-Path (Get-Location) '.git/documents') ${override ? '-WorkflowDirectory (Join-Path (Get-Location) \'scripts\')' : ''}
. $PROFILE.CurrentUserCurrentHost
function node { [IO.File]::AppendAllText('${quote(log)}', ($args -join '|')+[Environment]::NewLine); $global:LASTEXITCODE=0 }
composer dev
composer run dev
composer --version
git remote set-url origin https://example.invalid/foreign.git
try { composer dev; throw 'FOREIGN_START_ALLOWED' } catch { if($_.Exception.Message -eq 'FOREIGN_START_ALLOWED') { throw } }
git remote set-url origin https://github.com/ITStudioAT/schooltool.git
`;
                const result = spawnSync(shell, ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', script],
                    { cwd: fixture.project, encoding: 'utf8', timeout: 30000, windowsHide: true });
                assert.equal(result.status, 0, result.stdout + result.stderr);
                assert.match(result.stdout, /Composer version/);
            }
        }
        const lines = fs.readFileSync(log, 'utf8').trim().split(/\r?\n/);
        assert.equal(lines.length, 8);
        for (const line of lines) {
            const [entry, projectOption, selected] = line.split('|');
            assert.equal(path.resolve(entry), path.join(fixture.project, 'scripts/local-dev.mjs'));
            assert.equal(projectOption, '--project');
            assert.equal(path.resolve(selected), fixture.project);
        }
    } finally { fixture.cleanup(); }
});
