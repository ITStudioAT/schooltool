export function acceptsModuleHandshake(file) {
    return Boolean(file && /^handshake\.json$/i.test(file.name) && file.size <= 4096)
}

export async function readModuleHandshake(file) {
    if (!acceptsModuleHandshake(file)) throw new Error('Bitte ausschließlich die Datei handshake.json mit höchstens 4 KB auswählen.')
    let contents
    try {
        contents = typeof file.arrayBuffer === 'function'
            ? new TextDecoder('utf-8', { fatal: true }).decode(await file.arrayBuffer()) : await file.text()
        const data = JSON.parse(contents)
        const keys = [...contents.matchAll(/"(?:[^"\\]|\\.)*"\s*:/g)].map(match => JSON.parse(match[0].slice(0, match[0].lastIndexOf(':')).trim()))
        if (!data || Array.isArray(data) || Object.keys(data).length !== 2 || keys.length !== 2 || new Set(keys).size !== 2
            || data.schema_version !== 1 || typeof data.module_id !== 'string'
            || !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(data.module_id)) throw new Error()
    } catch {
        throw new Error('handshake.json muss als UTF-8 genau schema_version: 1 und eine gültige module_id enthalten.')
    }
    return { filename: file.name, contents }
}
