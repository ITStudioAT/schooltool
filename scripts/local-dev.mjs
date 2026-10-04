import { fork, spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { parseEnv } from 'node:util';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));

function canonical(filename) {
    return path.resolve(filename).replaceAll('\\', '/').toLowerCase();
}

export function workspacePorts(project, main, portOffset = 0) {
    return { server: 8000 + portOffset, vite: 5173 + portOffset };
}

function assertOrdinaryPath(filename) {
    for (let current = path.resolve(filename); current !== path.dirname(current); current = path.dirname(current)) {
        if (fs.existsSync(current) && fs.lstatSync(current).isSymbolicLink()) {
            throw new Error('Local development paths may not cross junctions or symbolic links.');
        }
    }
}

export function assertLocalEnvironment(values) {
    if (values.APP_ENV !== 'local' || !['mysql', 'mariadb'].includes(values.DB_CONNECTION)
        || !['127.0.0.1', 'localhost', '::1'].includes(values.DB_HOST)
        || values.DB_URL && !['null', '(null)', ''].includes(values.DB_URL)
        || ['true', '1'].includes(values.SCHOOLTOOL_PREVIEW_INSTANCE)) {
        throw new Error('Only a local environment with a loopback database can be used; configuration was preserved.');
    }
    if (!values.APP_KEY || !values.DB_DATABASE) {
        throw new Error('The local environment is incomplete; configuration was preserved.');
    }
}

export function prepareWorkspaceEnvironment(project, main, ports) {
    assertOrdinaryPath(project);
    assertOrdinaryPath(main);
    const target = path.join(project, '.env');
    assertOrdinaryPath(target);
    if (!fs.existsSync(target)) {
        const source = path.join(main, '.env');
        assertOrdinaryPath(source);
        const contents = fs.readFileSync(source, 'utf8');
        const values = parseEnv(contents);
        assertLocalEnvironment(values);
        const overrides = {
            APP_URL: `http://localhost:${ports.server}`,
        };
        let derived = contents;
        for (const [key, value] of Object.entries(overrides)) {
            derived = derived.replace(new RegExp(`^\\s*${key}\\s*=.*(?:\\r?\\n|$)`, 'gm'), '');
            derived += `\n${key}=${value}\n`;
        }
        fs.writeFileSync(target, derived, { flag: 'wx', mode: 0o600 });
        console.log('Local worktree configuration prepared; no database was copied or changed.');
    }
    let contents = fs.readFileSync(target, 'utf8');
    let values = parseEnv(contents);
    const legacySuffix = createHash('sha256').update(canonical(project)).digest('hex').slice(0, 12);
    if (canonical(project) !== canonical(main) && values.SESSION_COOKIE === `schooltool_${legacySuffix}_session`
        && values.REDIS_PREFIX === `schooltool_${legacySuffix}_`) {
        const source = path.join(main, '.env');
        assertOrdinaryPath(source);
        const sourceContents = fs.readFileSync(source, 'utf8');
        const sourceValues = parseEnv(sourceContents);
        assertLocalEnvironment(sourceValues);
        if (values.APP_KEY !== sourceValues.APP_KEY || values.DB_DATABASE !== sourceValues.DB_DATABASE) {
            throw new Error('The previously derived configuration has changed its session or database identity; it was preserved.');
        }
        for (const key of ['SESSION_COOKIE', 'REDIS_PREFIX', 'APP_URL']) {
            const pattern = new RegExp(`^\\s*${key}\\s*=.*(?:\\r?\\n|$)`, 'gm');
            contents = contents.replace(pattern, '');
            if (key !== 'APP_URL') {
                const original = sourceContents.match(new RegExp(`^\\s*${key}\\s*=.*$`, 'm'));
                if (original) {
                    contents += `\n${original[0].trim()}\n`;
                }
            }
        }
        contents += `\nAPP_URL=http://localhost:${ports.server}\n`;
        fs.writeFileSync(target, contents, { mode: 0o600 });
        values = parseEnv(contents);
        console.log('Previously derived worktree configuration now uses the shared local address and session.');
    }
    assertLocalEnvironment(values);
    return values;
}

function commandTokens(command) {
    return (command ?? '').match(/"[^"]*"|\S+/g)?.map(token => token.replace(/^"|"$/g, '')) ?? [];
}

export function ownsDevListener(owner, project, kind, port) {
    const tokens = commandTokens(owner?.command);
    const expected = canonical(path.join(project, kind === 'server'
        ? 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        : 'node_modules/vite/bin/vite.js'));
    if (kind === 'server') {
        return owner?.name?.toLowerCase() === 'php.exe'
            && tokens.includes('-S') && tokens.includes(`127.0.0.1:${port}`)
            && tokens.some(token => canonical(token) === expected);
    }
    return owner?.name?.toLowerCase() === 'node.exe'
        && tokens[1] && canonical(tokens[1]) === expected;
}

export function existingWorkspaceSession(project, ports, inventory) {
    const result = {};
    for (const [kind, port] of Object.entries(ports)) {
        const listeners = inventory.listeners.filter(listener => listener.port === port);
        const ownerIds = [...new Set(listeners.map(listener => listener.pid))];
        if (ownerIds.length === 0) {
            continue;
        }
        const owner = inventory.processes.find(candidate => candidate.pid === ownerIds[0]);
        if (ownerIds.length !== 1 || !ownsDevListener(owner, project, kind, port)) {
            throw new Error(`Port ${port} is occupied by another process. No process was stopped and no alternative port was selected.`);
        }
        result[kind] = owner;
    }
    if (Object.keys(result).length === 1) {
        throw new Error('This worktree has an incomplete development session. Stop that session and run composer dev again; other processes were preserved.');
    }
    return Object.keys(result).length === 2 ? result : null;
}

function processInventory(ports) {
    const result = spawnSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
        '-File', path.join(scriptDirectory, 'dev-processes.ps1'), '-Ports', Object.values(ports).join(',')],
    { encoding: 'utf8', windowsHide: true, timeout: 20000 });
    if (result.status !== 0) {
        throw new Error('Could not verify local port ownership. No processes were changed.');
    }
    return JSON.parse(result.stdout.trim());
}

function runRequired(project, executable, argumentsList, environment) {
    const result = spawnSync(executable, argumentsList, { cwd: project, env: environment, stdio: 'inherit', windowsHide: true });
    if (result.status !== 0) {
        throw new Error('Local development preparation failed.');
    }
}

async function waitForWeb(project, ports, children = []) {
    const deadline = Date.now() + 45000;
    while (Date.now() < deadline) {
        if (children.some(child => child.exitCode !== null)) {
            throw new Error('A development service stopped before it became ready.');
        }
        try {
            const backend = await fetch(`http://127.0.0.1:${ports.server}/up`, { signal: AbortSignal.timeout(1000) });
            const frontend = await fetch(`http://localhost:${ports.vite}/@vite/client`, { signal: AbortSignal.timeout(1000) });
            const hot = fs.readFileSync(path.join(project, 'public/hot'), 'utf8').trim();
            if (backend.ok && frontend.ok && hot === `http://localhost:${ports.vite}`) {
                const inventory = processInventory(ports);
                if (existingWorkspaceSession(project, ports, inventory)) {
                    return;
                }
            }
        } catch {
            // Services may not yet have bound their ports or written their own hot file.
        }
        await new Promise(resolve => setTimeout(resolve, 300));
    }
    throw new Error('The matching backend and Vite server did not become ready. No browser was opened.');
}

function openWorkspace(url) {
    const opener = spawn('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', `Start-Process '${url}'`],
        { stdio: 'ignore', windowsHide: true });
    opener.unref();
}

export async function startLocalDev(project, { webOnly = false, open = true, checkSeconds = 0, portOffset = 0 } = {}) {
    if (process.platform !== 'win32') {
        throw new Error('This workstation launcher supports Windows only.');
    }
    project = fs.realpathSync(project);
    const git = spawnSync('git', ['-C', project, 'rev-parse', '--path-format=absolute', '--git-common-dir'], { encoding: 'utf8', windowsHide: true });
    if (git.status !== 0 || path.basename(git.stdout.trim()) !== '.git') {
        throw new Error('Run composer dev inside the local Schooltool checkout or one of its worktrees.');
    }
    const main = path.dirname(git.stdout.trim());
    const ports = workspacePorts(project, main, portOffset);
    const values = prepareWorkspaceEnvironment(project, main, ports);
    const external = Object.fromEntries(Object.entries(process.env).filter(([key]) =>
        ['APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_URL', 'SCHOOLTOOL_PREVIEW_INSTANCE'].includes(key)));
    assertLocalEnvironment({ ...values, ...external });
    const url = `http://localhost:${ports.server}`;
    console.log(`Schooltool folder: ${project}\nApplication: ${url}\nVite: http://localhost:${ports.vite}`);
    const existing = existingWorkspaceSession(project, ports, processInventory(ports));
    if (existing) {
        await waitForWeb(project, ports);
        console.log('This worktree is already running; the existing services were preserved.');
        if (open) {
            openWorkspace(url);
        }
        process.send?.({ ready: true, reused: true, project, url });
        return;
    }
    const cachePrefix = `bootstrap/cache/local-dev-${process.pid}`;
    const environment = { ...process.env, APP_ENV: 'local', APP_URL: url,
        APP_CONFIG_CACHE: `${cachePrefix}.config.php`, APP_ROUTES_CACHE: `${cachePrefix}.routes.php`,
        APP_PACKAGES_CACHE: `${cachePrefix}.packages.php`, APP_SERVICES_CACHE: `${cachePrefix}.services.php`,
        APP_EVENTS_CACHE: `${cachePrefix}.events.php` };
    runRequired(project, 'php', ['scripts/update.php', '--dev-preflight'], environment);
    if (!webOnly) {
        runRequired(project, 'php', ['artisan', 'app:update', '--versions-only'], environment);
    }
    const children = [];
    const jobs = new Set();
    const timers = new Set();
    let stopping = false;
    const start = (executable, argumentsList) => {
        const child = spawn(executable, argumentsList, { cwd: project, env: environment, stdio: 'inherit', windowsHide: true });
        child.on('error', error => console.error(error.message));
        children.push(child);
        return child;
    };
    const php = start('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${ports.server}`, '--tries=1']);
    const vite = start(process.execPath, [path.join(project, 'scripts/vite-dev.mjs'), '--host', 'localhost', '--port', String(ports.vite), '--strictPort']);
    const stop = async () => {
        if (stopping) {
            return;
        }
        stopping = true;
        for (const timer of timers) {
            clearTimeout(timer);
        }
        const inventory = processInventory(ports);
        for (const child of children) {
            const actual = inventory.processes.find(candidate => candidate.pid === child.pid);
            if (child.exitCode === null && child.pid && actual?.parent === process.pid) {
                spawnSync('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], { stdio: 'ignore', windowsHide: true });
            }
        }
        if (jobs.size > 0) {
            console.log('Waiting for this session\'s running jobs before switching folders...');
            await Promise.all([...jobs].map(child => new Promise(resolve => child.once('exit', resolve))));
        }
        for (const suffix of ['config', 'routes', 'packages', 'services', 'events']) {
            fs.rmSync(path.join(project, `${cachePrefix}.${suffix}.php`), { force: true });
        }
        const hot = path.join(project, 'public/hot');
        if (fs.existsSync(hot) && fs.readFileSync(hot, 'utf8').trim() === `http://localhost:${ports.vite}`) {
            fs.rmSync(hot);
        }
    };
    let requestStop;
    let stopRequested = false;
    const stopped = new Promise(resolve => { requestStop = () => { stopRequested = true; resolve(); }; });
    process.once('SIGINT', requestStop);
    process.once('SIGTERM', requestStop);
    process.on('message', message => { if (message?.stop === true) { requestStop(); } });
    const later = (callback, delay) => {
        const timer = setTimeout(() => { timers.delete(timer); callback(); }, delay);
        timers.add(timer);
    };
    const startJob = (argumentsList, next) => {
        if (stopping || stopRequested) {
            return;
        }
        const child = spawn('php', argumentsList, { cwd: project, env: environment, stdio: 'inherit', windowsHide: true });
        jobs.add(child);
        child.once('error', error => { console.error(error.message); jobs.delete(child); requestStop(); });
        child.once('exit', code => {
            jobs.delete(child);
            if (!stopping && code !== 0) {
                console.error('A local background job failed; see its output above.');
            }
            if (!stopping && !stopRequested && next) {
                later(next, 1000);
            }
        });
    };
    try {
        await waitForWeb(project, ports, [php, vite]);
        if (!webOnly && !stopRequested) {
            for (const argumentsList of [
                ['artisan', 'queue:work', 'redis', '--once', '--queue=critical,notifications', '--sleep=1', '--tries=3', '--timeout=60'],
                ['artisan', 'queue:work', 'redis', '--once', '--queue=default', '--sleep=1', '--tries=2', '--timeout=1830'],
                ['artisan', 'queue:work', 'redis', '--once', '--queue=imports', '--sleep=1', '--tries=1', '--timeout=60'],
                ['artisan', 'queue:work', 'redis', '--once', '--queue=materials,maintenance', '--sleep=1', '--tries=1', '--timeout=1830'],
            ]) {
                const runWorker = () => startJob(argumentsList, runWorker);
                runWorker();
            }
            const schedule = () => {
                if (stopping) {
                    return;
                }
                startJob(['artisan', 'schedule:run']);
                later(schedule, 60000 - Date.now() % 60000);
            };
            later(schedule, 60000 - Date.now() % 60000);
        }
        if (open) {
            openWorkspace(url);
        }
        console.log('The matching application is ready. Ctrl+C stops this session.');
        process.send?.({ ready: true, reused: false, project, url });
        if (webOnly && checkSeconds > 0) {
            await new Promise(resolve => setTimeout(resolve, checkSeconds * 1000));
        } else {
            const code = await Promise.race([stopped, ...children.map(child => new Promise(resolve => child.once('exit', resolve)))]);
            if (code !== undefined && code !== 0) {
                throw new Error('A development service failed; no alternative port was selected.');
            }
        }
    } finally {
        await stop();
        if (process.connected) {
            process.disconnect();
        }
    }
}

export function publishDevSelection(common, project) {
    const directory = path.join(common, 'schooltool-dev');
    fs.mkdirSync(directory, { recursive: true });
    const temporary = path.join(directory, `selection-${process.pid}.tmp`);
    fs.writeFileSync(temporary, JSON.stringify({ format: 'schooltool-dev-selection-v1', project }), { mode: 0o600 });
    fs.renameSync(temporary, path.join(directory, 'selection.json'));
}

function selectedProject(common, fallback) {
    const selection = JSON.parse(fs.readFileSync(path.join(common, 'schooltool-dev/selection.json'), 'utf8'));
    if (selection.format !== 'schooltool-dev-selection-v1' || typeof selection.project !== 'string') {
        throw new Error('The local development selection is invalid.');
    }
    const project = fs.realpathSync(selection.project ?? fallback);
    assertOrdinaryPath(project);
    const git = spawnSync('git', ['-C', project, 'rev-parse', '--path-format=absolute', '--git-common-dir'], { encoding: 'utf8', windowsHide: true });
    if (git.status !== 0 || canonical(git.stdout.trim()) !== canonical(common)) {
        throw new Error('The selected folder belongs to another repository.');
    }
    return project;
}

function projectIdentity(project) {
    const git = spawnSync('git', ['-C', project, 'rev-parse', 'HEAD'], { encoding: 'utf8', windowsHide: true });
    if (git.status !== 0) {
        throw new Error('Could not verify the selected source checkout.');
    }
    const branch = spawnSync('git', ['-C', project, 'branch', '--show-current'], { encoding: 'utf8', windowsHide: true });
    if (branch.status !== 0 || !branch.stdout.trim()) {
        throw new Error('The selected worktree must have a named branch.');
    }
    return `${canonical(project)}:${branch.stdout.trim()}:${git.stdout.trim()}`;
}

export async function watchLocalDev(project, options) {
    const git = spawnSync('git', ['-C', project, 'rev-parse', '--path-format=absolute', '--git-common-dir'], { encoding: 'utf8', windowsHide: true });
    if (git.status !== 0 || path.basename(git.stdout.trim()) !== '.git') {
        throw new Error('Run composer dev in the local checkout or its feature worktree.');
    }
    const common = git.stdout.trim();
    const selectionPath = path.join(common, 'schooltool-dev/selection.json');
    if (fs.existsSync(selectionPath)) {
        project = selectedProject(common, project);
    } else {
        publishDevSelection(common, project);
    }
    const ports = workspacePorts(project, path.dirname(common), options.portOffset);
    const receiptPath = path.join(common, 'schooltool-dev/controller.json');
    if (fs.existsSync(receiptPath)) {
        const receipt = JSON.parse(fs.readFileSync(receiptPath, 'utf8'));
        const actual = processInventory(ports).processes.find(candidate => candidate.pid === receipt.owner?.pid);
        if (actual && JSON.stringify(actual) === JSON.stringify(receipt.owner) && receipt.format === 'schooltool-dev-controller-v1') {
            console.log('The running composer dev follows this worktree; no second session was started.');
            return;
        }
        if (actual) {
            throw new Error('The existing local development controller has a different process identity. It was preserved.');
        }
    }
    const owner = processInventory(ports).processes.find(candidate => candidate.pid === process.pid);
    if (!owner) {
        throw new Error('Could not bind the local development controller to its process identity.');
    }
    fs.writeFileSync(receiptPath, JSON.stringify({ format: 'schooltool-dev-controller-v1', owner, webOnly: options.webOnly }), { mode: 0o600 });
    let stopping = false;
    let child = null;
    let identity = '';
    let ready = false;
    let reused = false;
    let nextProbe = 0;
    const stopChild = async () => {
        if (child && child.exitCode === null) {
            const previous = child;
            const exited = new Promise(resolve => previous.once('exit', resolve));
            if (previous.connected) {
                previous.send({ stop: true });
            }
            await exited;
        }
        child = null;
    };
    process.once('SIGINT', () => { stopping = true; });
    process.once('SIGTERM', () => { stopping = true; });
    process.on('message', message => { if (message?.stop === true) { stopping = true; } });
    const deadline = options.checkSeconds ? Date.now() + options.checkSeconds * 1000 : Infinity;
    try {
        while (!stopping && Date.now() < deadline) {
            const selected = selectedProject(common, project);
            const nextIdentity = projectIdentity(selected);
            if (nextIdentity !== identity) {
                await stopChild();
                identity = nextIdentity;
                ready = false;
                reused = false;
                console.log(`Following worktree: ${selected}`);
                const argumentsList = ['--session', '--project', selected];
                if (options.webOnly) {
                    argumentsList.push('--web-only');
                }
                if (!options.open) {
                    argumentsList.push('--no-open');
                }
                if (options.portOffset) {
                    argumentsList.push(`--check-port-offset=${options.portOffset}`);
                }
                child = fork(fileURLToPath(import.meta.url), argumentsList, { cwd: selected, stdio: 'inherit' });
                child.on('message', message => {
                    if (message?.ready === true) {
                        ready = true;
                        reused = message.reused === true;
                    }
                });
            }
            if (child?.exitCode !== null && child?.exitCode !== undefined && (!ready || child.exitCode !== 0)) {
                throw new Error('The selected development session failed; see its output above.');
            }
            if (ready && child?.exitCode === 0 && Date.now() > nextProbe) {
                nextProbe = Date.now() + 5000;
                const selectedPorts = workspacePorts(selected, path.dirname(common), options.portOffset);
                if (!reused || !existingWorkspaceSession(selected, selectedPorts, processInventory(selectedPorts))) {
                    identity = '';
                }
            }
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    } finally {
        await stopChild();
        if (fs.existsSync(receiptPath)) {
            const receipt = JSON.parse(fs.readFileSync(receiptPath, 'utf8'));
            if (receipt.owner?.pid === process.pid && receipt.owner?.started === owner.started) {
                fs.rmSync(receiptPath);
            }
        }
        if (process.connected) {
            process.disconnect();
        }
    }
}

if (process.argv[1] && canonical(process.argv[1]) === canonical(fileURLToPath(import.meta.url))) {
    const projectArgument = process.argv.indexOf('--project');
    const project = projectArgument === -1 ? process.cwd() : process.argv[projectArgument + 1];
    const checkArgument = process.argv.find(argument => argument.startsWith('--check-seconds='));
    const checkSeconds = checkArgument ? Number(checkArgument.split('=')[1]) : 0;
    if (!Number.isInteger(checkSeconds) || checkSeconds < 0 || checkSeconds > 120 || checkSeconds && !process.argv.includes('--web-only')) {
        throw new Error('Bounded checks require --web-only and a duration of at most 120 seconds.');
    }
    const offsetArgument = process.argv.find(argument => argument.startsWith('--check-port-offset='));
    const portOffset = offsetArgument ? Number(offsetArgument.split('=')[1]) : 0;
    if (!Number.isInteger(portOffset) || portOffset < 0 || portOffset > 20000 || portOffset && !process.argv.includes('--web-only')) {
        throw new Error('Port offsets are limited to isolated web-only checks.');
    }
    const options = { webOnly: process.argv.includes('--web-only'), open: !process.argv.includes('--no-open'), checkSeconds, portOffset };
    const start = process.argv.includes('--session') ? startLocalDev : watchLocalDev;
    start(project, options)
        .catch(error => { console.error(error.message); process.exitCode = 1; });
}
