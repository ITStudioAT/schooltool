import { spawn } from 'node:child_process';
import { randomBytes, createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const project = fs.realpathSync(path.dirname(path.dirname(fileURLToPath(import.meta.url))));
const nonce = randomBytes(16).toString('hex');
const runtime = path.join(project, 'storage/framework');
fs.mkdirSync(runtime, { recursive: true });
const manifestPath = path.join(runtime, `vite-dev-${nonce}.json`);
const pausePath = manifestPath.replace(/\.json$/, '.pause');
const entry = path.join(project, 'node_modules/vite/bin/vite.js');
const startedAt = new Date(Date.now() - process.uptime() * 1000).toISOString();
let child = null;
let pauseRequest = null;
let stopping = false;

function publish(status) {
    fs.writeFileSync(`${manifestPath}.tmp`, JSON.stringify({
        format: 'schooltool-vite-dev-v1', nonce, project, wrapper_pid: process.pid,
        started_at: startedAt, vite_pid: child?.pid ?? null, status, pause_request: pauseRequest,
    }), { mode: 0o600 });
    fs.renameSync(`${manifestPath}.tmp`, manifestPath);
}

function finish(code) {
    if (stopping) return;
    stopping = true;
    child?.kill();
    for (const filename of [manifestPath, `${manifestPath}.tmp`, pausePath]) {
        fs.rmSync(filename, { force: true });
    }
    process.exit(code);
}

function startVite() {
    child = spawn(process.execPath, [entry, ...process.argv.slice(2)], { cwd: project, stdio: 'inherit' });
    child.on('error', error => {
        console.error(error.message);
        finish(1);
    });
    child.on('exit', code => {
        child = null;
        if (pauseRequest) {
            publish('paused');
            return;
        }
        finish(code ?? 1);
    });
    publish('running');
}

function installationComplete() {
    const receipt = path.join(runtime, 'frontend-dependencies.sha256');
    if (fs.existsSync(`${receipt}.installing`) || !fs.existsSync(entry) || !fs.existsSync(receipt)) return false;
    const hash = createHash('sha256').update(fs.readFileSync(path.join(project, 'package-lock.json'))).digest('hex');
    return fs.readFileSync(receipt, 'utf8').trim() === hash;
}

setInterval(() => {
    try {
        if (!pauseRequest && child && fs.existsSync(pausePath)) {
            const request = JSON.parse(fs.readFileSync(pausePath, 'utf8'));
            if (request.nonce !== nonce || request.wrapper_pid !== process.pid || request.vite_pid !== child.pid) return;
            pauseRequest = request;
            publish('pausing');
            child.kill();
        } else if (pauseRequest && !child && !fs.existsSync(pausePath) && installationComplete()) {
            pauseRequest = null;
            startVite();
        }
    } catch (error) {
        console.error(`Vite coordination: ${error.message}`);
    }
}, 100);

process.on('SIGINT', () => finish(0));
process.on('SIGTERM', () => finish(0));
startVite();
