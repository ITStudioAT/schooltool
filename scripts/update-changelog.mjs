import { cpSync, existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { spawnSync } from 'node:child_process'

function normalizeText(text) {
    return text.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n')
}

function versionSection(text, version) {
    const headings = [...text.matchAll(/^## (.+)$/gm)]
    const matches = headings.filter((heading) => heading[1].trim() === version)
    if (matches.length !== 1) {
        throw new Error(`Expected exactly one "## ${version}" section.`)
    }

    const heading = matches[0]
    const end = headings[headings.indexOf(heading) + 1]?.index ?? text.length

    return { start: heading.index, end, text: text.slice(heading.index, end).trim() }
}

export function prepareReleaseChangelog(updates, changelog, sidebar, version) {
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
        throw new Error('Use a release version such as 3.47.1, without a v prefix.')
    }

    const section = versionSection(normalizeText(updates), version)
    if (!section.text.split('\n').slice(1).some((line) => line.trim() && !line.startsWith('#'))) {
        throw new Error(`UPDATES.md has no release notes for ${version}.`)
    }

    let nextChangelog = normalizeText(changelog)
    if (!/^\*\*Aktuelle Version:\*\* `[^`]+`$/m.test(nextChangelog)) {
        throw new Error('The changelog current-version marker is missing.')
    }

    const existingHeadings = [...nextChangelog.matchAll(/^## (.+)$/gm)]
    if (existingHeadings.some((heading) => heading[1].trim() === version)) {
        const existing = versionSection(nextChangelog, version)
        nextChangelog = nextChangelog.slice(0, existing.start) + nextChangelog.slice(existing.end)
    }

    const firstRelease = /^## \d+\.\d+\.\d+.*$/m.exec(nextChangelog)
    if (!firstRelease) {
        throw new Error('The changelog has no release history insertion point.')
    }

    nextChangelog = nextChangelog.slice(0, firstRelease.index)
        + section.text + '\n\n' + nextChangelog.slice(firstRelease.index)
    nextChangelog = nextChangelog.replace(/^\*\*Aktuelle Version:\*\* `[^`]+`$/m, `**Aktuelle Version:** \`${version}\``)

    const escapedVersion = version.replaceAll('.', '\\.')
    const existingLink = new RegExp(`^[ \\t]*\\{\\s*type:\\s*["']link["'],\\s*label:\\s*["']${escapedVersion}["'],[^\\n]*\\},?\\n`, 'gm')
    let nextSidebar = normalizeText(sidebar).replace(existingLink, '')
    const versionItems = /(label:\s*["']Versionen["'][\s\S]*?items:\s*\[)\n/
    if (!versionItems.test(nextSidebar)) {
        throw new Error('The releases sidebar Versionen category is missing.')
    }

    nextSidebar = nextSidebar.replace(versionItems, `$1\n        { type: "link", label: "${version}", href: "/releases/#${version.replaceAll('.', '')}" },\n`)

    return { changelog: nextChangelog, sidebar: nextSidebar }
}

function buildDocumentation(documentationRoot) {
    const windows = process.platform === 'win32'
    const result = spawnSync(windows ? (process.env.COMSPEC || 'cmd.exe') : 'npm',
        windows ? ['/d', '/s', '/c', 'npm run build'] : ['run', 'build'],
        { cwd: documentationRoot, stdio: 'inherit', windowsHide: true })

    if (result.error || result.status !== 0) {
        throw new Error(`Documentation build failed: ${result.error?.message || `exit code ${result.status}`}`)
    }
}

export function updateReleaseChangelog({
    version,
    projectRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..'),
    documentationRoot = process.env.SCHOOLTOOL_DOCUMENTATION_ROOT || 'C:/docusaurus/schooltool',
    build = buildDocumentation,
}) {
    const changelogPath = resolve(documentationRoot, 'docs/releases/index.md')
    const sidebarPath = resolve(documentationRoot, 'sidebars.releases.js')
    const changelog = readFileSync(changelogPath, 'utf8')
    const sidebar = readFileSync(sidebarPath, 'utf8')
    const updated = prepareReleaseChangelog(readFileSync(resolve(projectRoot, 'UPDATES.md'), 'utf8'), changelog, sidebar, version)

    try {
        writeFileSync(changelogPath, updated.changelog, 'utf8')
        writeFileSync(sidebarPath, updated.sidebar, 'utf8')
        build(documentationRoot)

        const output = resolve(documentationRoot, 'build')
        for (const page of ['releases/index.html', 'en/releases/index.html']) {
            const html = readFileSync(resolve(output, page), 'utf8')
            if (!html.includes(`id="${version.replaceAll('.', '')}"`)) {
                throw new Error(`Built ${page} does not contain release ${version}.`)
            }
        }

        const destinationRoot = resolve(projectRoot, 'public/documentation')
        cpSync(output, destinationRoot, {
            recursive: true,
            filter(source, destination) {
                const path = relative(destinationRoot, destination).replaceAll('\\', '/')
                if (/^en\/en(?:\/|$)/.test(path)) {
                    return false
                }

                const standalone = /^(en\/)?schuelerstundenplaene(?:\/|$)/.test(path)
                    || /^assets\/[^/]+\/schuelerstundenplaene(?:[./]|$)/.test(path)
                    || path === 'assets/js/documentation-theme.js'

                return !standalone || !existsSync(destination)
            },
        })
    } catch (error) {
        writeFileSync(changelogPath, changelog, 'utf8')
        writeFileSync(sidebarPath, sidebar, 'utf8')
        throw error
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    try {
        if (process.argv.length !== 3) {
            throw new Error('Usage: node scripts/update-changelog.mjs <version>')
        }
        updateReleaseChangelog({ version: process.argv[2] })
        console.log(`Changelog ${process.argv[2]} built and copied to public/documentation.`)
    } catch (error) {
        console.error(error.message)
        process.exitCode = 1
    }
}
