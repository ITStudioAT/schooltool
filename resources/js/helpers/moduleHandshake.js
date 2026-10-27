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
        const expectedKeys = data?.schema_version === 1 ? ['module_id', 'schema_version']
            : data?.schema_version === 2 ? ['dropbox_folder_id', 'module_id', 'schema_version'] : []
        if (!data || Array.isArray(data) || expectedKeys.length === 0 || Object.keys(data).sort().join(',') !== expectedKeys.join(',')
            || keys.length !== expectedKeys.length || new Set(keys).size !== expectedKeys.length || typeof data.module_id !== 'string'
            || (data.schema_version === 2 && (typeof data.dropbox_folder_id !== 'string' || !/^id:[A-Za-z0-9_-]+$/.test(data.dropbox_folder_id)))
            || !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(data.module_id)) throw new Error()
    } catch {
        throw new Error('handshake.json muss als UTF-8 eine unterstützte Modulkennung enthalten.')
    }
    return { filename: file.name, contents }
}
