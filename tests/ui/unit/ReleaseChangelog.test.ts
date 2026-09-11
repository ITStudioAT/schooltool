import { afterEach, describe, expect, it, vi } from 'vitest'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, realpathSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { tmpdir } from 'node:os'
import { spawnSync } from 'node:child_process'
import { prepareReleaseChangelog, updateReleaseChangelog } from '../../../scripts/update-changelog.mjs'

const updates = '# UPDATES\n\n## 3.47.1\n\n### Materialien\n\n- Vorschau für neue Dokumente repariert.\n\n## 3.47.0\n\n- Alt.\n'
const changelog = '---\ntitle: Changelog\n---\n\n# Changelog\n\n**Aktuelle Version:** `3.47.0`\n\n## 3.47.0\n\n- Historie.\n\n## Weitere Informationen\n\n- [Roadmap](./roadmap)\n'
const sidebar = 'export default { releases: [{ label: "Versionen", items: [\n        { type: "link", label: "3.47.0", href: "/releases/#3470" },\n        { type: "doc", id: "v3-32-2" },\n] }] }\n'
const directories = []

function fixture() {
    const root = mkdtempSync(join(tmpdir(), 'schooltool-changelog-test-'))
    directories.push(root)
    const projectRoot = join(root, 'app')
    const documentationRoot = join(root, 'docs')
    mkdirSync(join(documentationRoot, 'docs/releases'), { recursive: true })
    mkdirSync(projectRoot)
    writeFileSync(join(projectRoot, 'UPDATES.md'), updates)
    writeFileSync(join(documentationRoot, 'docs/releases/index.md'), changelog)
    writeFileSync(join(documentationRoot, 'sidebars.releases.js'), sidebar)

    return { root, projectRoot, documentationRoot, version: '3.47.1' }
}

afterEach(() => {
    for (const directory of directories.splice(0)) {
        expect(dirname(realpathSync(directory))).toBe(realpathSync(tmpdir()))
        rmSync(directory, { recursive: true })
    }
})

describe('release changelog', () => {
    it.each(['', 'en/'])('publishes the SEPP documentation name and description for locale %s', (locale) => {
        const documentationRoot = resolve('public/documentation')
        const moduleRoot = join(documentationRoot, locale, 'schuelerstundenplaene')
        const landingPage = readFileSync(join(moduleRoot, 'index.html'), 'utf8')

        expect(landingPage).toContain('<h1>SEPP</h1>')
        expect(landingPage).toContain('Stundenplanerstellungs- und -planungsprogramm')

        const pages = readdirSync(moduleRoot, { recursive: true }).filter((path) => String(path).endsWith('.html'))
        expect(pages.length).toBeGreaterThan(1)
        for (const path of pages) {
            const html = readFileSync(join(moduleRoot, String(path)), 'utf8')
            expect(html).toContain('>SEPP</a>')
            expect(html).not.toContain('Schülerstundenpläne')
        }

        for (const path of ['index.html', 'releases/index.html']) {
            const html = readFileSync(join(documentationRoot, locale, path), 'utf8')
            expect(html).toContain('>SEPP</a>')
            expect(html).not.toContain('Schülerstundenpläne')
        }
    })

    it('upserts exactly the requested release without duplicating history or sidebar links', () => {
        const first = prepareReleaseChangelog(updates, changelog, sidebar, '3.47.1')
        const second = prepareReleaseChangelog(updates, first.changelog, first.sidebar, '3.47.1')

        expect(second).toEqual(first)
        expect(first.changelog).toContain('**Aktuelle Version:** `3.47.1`')
        expect(first.changelog).toContain('Vorschau für neue Dokumente repariert.')
        expect(first.changelog).toContain('## 3.47.0\n\n- Historie.')
        expect(first.changelog).toContain('## Weitere Informationen')
        expect(first.sidebar).toContain('href: "/releases/#3471"')
        expect(first.sidebar).toContain('id: "v3-32-2"')
        expect(prepareReleaseChangelog(updates.replace('repariert', 'verbessert'), first.changelog, first.sidebar, '3.47.1').changelog)
            .toContain('Vorschau für neue Dokumente verbessert.')
    })

    it.each([
        ['missing version', updates, '3.47.2'],
        ['partial match', updates, '3.47'],
        ['unsafe version', updates, '../3.47.1'],
        ['empty notes', '# UPDATES\n## 3.47.1\n### Materialien\n', '3.47.1'],
        ['duplicate version', updates + '\n## 3.47.1\n- Duplicate\n', '3.47.1'],
    ])('rejects %s before changing files or building', (_name, notes, version) => {
        const options = fixture()
        writeFileSync(join(options.projectRoot, 'UPDATES.md'), notes)
        const build = vi.fn()

        expect(() => updateReleaseChangelog({ ...options, version, build })).toThrow()
        expect(build).not.toHaveBeenCalled()
        expect(readFileSync(join(options.documentationRoot, 'docs/releases/index.md'), 'utf8')).toBe(changelog)
    })

    it('restores source files and leaves published documentation untouched on build failure', () => {
        const options = fixture()
        const build = () => { throw new Error('Build failed') }

        expect(() => updateReleaseChangelog({ ...options, build })).toThrow('Build failed')
        expect(readFileSync(join(options.documentationRoot, 'docs/releases/index.md'), 'utf8')).toBe(changelog)
        expect(readFileSync(join(options.documentationRoot, 'sidebars.releases.js'), 'utf8')).toBe(sidebar)
        expect(existsSync(join(options.projectRoot, 'public'))).toBe(false)
    })

    it('copies HTML and hydration assets together while preserving standalone pages and custom assets', () => {
        const options = fixture()
        const target = join(options.projectRoot, 'public/documentation')
        const preserved = ['schuelerstundenplaene/index.html', 'en/schuelerstundenplaene/index.html', 'assets/css/schuelerstundenplaene.css', 'assets/js/documentation-theme.js']
        for (const path of preserved) {
            mkdirSync(dirname(join(target, path)), { recursive: true })
            writeFileSync(join(target, path), 'Existing custom content')
        }
        const build = (root) => {
            expect(readFileSync(join(root, 'docs/releases/index.md'), 'utf8')).toContain('## 3.47.1')
            for (const path of ['releases/index.html', 'en/releases/index.html', 'assets/js/release-hash.js', 'en/en/schuelerstundenplaene/index.html', ...preserved]) {
                mkdirSync(dirname(join(root, 'build', path)), { recursive: true })
                writeFileSync(join(root, 'build', path), '<h2 id="3471">3.47.1</h2>')
            }
        }

        updateReleaseChangelog({ ...options, build })

        expect(readFileSync(join(target, 'releases/index.html'), 'utf8')).toContain('3.47.1')
        expect(existsSync(join(target, 'assets/js/release-hash.js'))).toBe(true)
        expect(existsSync(join(target, 'en/en'))).toBe(false)
        for (const path of preserved) {
            expect(readFileSync(join(target, path), 'utf8')).toBe('Existing custom content')
        }
    })

    it.each([
        ['versioned release', '3.47.1', false],
        ['unversioned release', '', false],
        ['failed documentation build', '3.47.1', true],
    ])('orders the real gitpush workflow correctly for %s', (_name, version, failBuild) => {
        if (process.platform !== 'win32') return
        const { root } = fixture()
        const scriptPath = join(root, 'workflow.ps1')
        writeFileSync(scriptPath, `param([string]$HelperPath, [string]$Version, [int]$FailBuild)
. $HelperPath
$script:calls = [System.Collections.Generic.List[string]]::new()
$script:committed = $false
function git {
    $global:LASTEXITCODE = 0
    switch ($args[0]) {
        'branch' { 'main' }
        'status' { if (-not $script:committed) { ' M public/documentation/releases/index.html' } }
        'rev-parse' { 'fixture-head' }
        'rev-list' { }
        'commit' { $script:calls.Add('commit'); $script:committed = $true }
        'push' { $script:calls.Add('push') }
    }
}
function php { $global:LASTEXITCODE = 0 }
function node { $script:calls.Add('changelog'); $global:LASTEXITCODE = $FailBuild }
function Test-Path { $false }
function Invoke-SchooltoolReleaseChecks { $script:calls.Add('checks') }
try { gitpush 'Fixture release' $Version } catch { $script:calls.Add('failed') }
Write-Output ('CALLS:' + ($script:calls -join ','))
`)
        const result = spawnSync('powershell', ['-NoProfile', '-NonInteractive', '-File', scriptPath,
            '-HelperPath', resolve('scripts/git_helpers.ps1'), '-Version', version, '-FailBuild', failBuild ? '1' : '0'],
        { encoding: 'utf8', windowsHide: true, timeout: 15000 })

        expect(result.status).toBe(0)
        expect(result.stdout).toContain(failBuild
            ? 'CALLS:changelog,failed'
            : version ? 'CALLS:changelog,checks,commit,commit,push' : 'CALLS:checks,commit,commit,push')
    })
})
