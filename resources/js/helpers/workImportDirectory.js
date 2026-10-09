import { shallowReactive } from 'vue'

export const workImportDirectories = shallowReactive(new Map())

export function workImportDirectoryKey(userId, work) {
    return userId && work?.id && work?.teaching_course_id ? `${userId}:${work.teaching_course_id}:${work.id}` : null
}

export function supportsWorkImportDirectory() {
    return typeof window.showDirectoryPicker === 'function' && Boolean(window.indexedDB)
}

async function directoryDatabase() {
    return new Promise((resolve, reject) => {
        const request = window.indexedDB.open('schooltool-work-import', 1)
        request.onupgradeneeded = () => request.result.createObjectStore('directories')
        request.onsuccess = () => resolve(request.result)
        request.onerror = () => reject(request.error)
    })
}

export async function loadWorkImportDirectory(key) {
    if (!key || !supportsWorkImportDirectory()) return null
    if (workImportDirectories.has(key)) return workImportDirectories.get(key)
    const database = await directoryDatabase()
    try {
        const handle = await new Promise((resolve, reject) => {
            const request = database.transaction('directories').objectStore('directories').get(key)
            request.onsuccess = () => resolve(request.result || null)
            request.onerror = () => reject(request.error)
        })
        workImportDirectories.set(key, handle)
        return handle
    } finally { database.close() }
}

export async function saveWorkImportDirectory(key, handle) {
    if (!key || !handle) return
    const database = await directoryDatabase()
    try {
        await new Promise((resolve, reject) => {
            const transaction = database.transaction('directories', 'readwrite')
            transaction.objectStore('directories').put(handle, key)
            transaction.oncomplete = resolve
            transaction.onerror = () => reject(transaction.error)
            transaction.onabort = () => reject(transaction.error)
        })
        workImportDirectories.set(key, handle)
    } finally { database.close() }
}

async function optionalDirectory(parent, name) {
    try { return await parent.getDirectoryHandle(name) }
    catch (error) { if (error.name === 'NotFoundError') return null; throw error }
}

export async function readWorkImportDirectory(handle) {
    const files = []
    async function addFile(parent, name, path) {
        const file = await (await parent.getFileHandle(name)).getFile()
        Object.defineProperty(file, '_relativePath', { value: `${handle.name}/${path}` })
        files.push(file)
        if (files.length > 51 || files.reduce((sum, item) => sum + item.size, 0) > 6 * 1024 * 1024) {
            throw new Error('Der Ordner überschreitet die zulässige Anzahl oder Größe der Importdateien.')
        }
        return file
    }
    try { await addFile(handle, 'Schooltool-Bewertungen.json', 'Schooltool-Bewertungen.json') }
    catch (error) { if (error.name !== 'NotFoundError') throw error }
    const evaluations = await optionalDirectory(handle, 'Beurteilungen')
    if (evaluations) {
        try { await addFile(evaluations, 'Schooltool-Bewertungen.json', 'Beurteilungen/Schooltool-Bewertungen.json') }
        catch (error) { if (error.name !== 'NotFoundError') throw error }
    }
    if (files.length === 1) {
        const packageFile = files[0]
        let packageData
        try { packageData = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(await packageFile.arrayBuffer())) }
        catch { throw new Error('Schooltool-Bewertungen.json kann nicht als UTF-8-JSON gelesen werden. Bitte den Ordner prüfen oder erneut auswählen.') }
        const directory = packageFile._relativePath.includes('/Beurteilungen/') ? evaluations : handle
        const prefix = directory === evaluations ? 'Beurteilungen/' : ''
        const references = [packageData.overview_pdf, ...(packageData.records || []).map(record => record?.pdf)].filter(Boolean)
        for (const name of new Set(references.map(reference => reference.filename))) {
            if (typeof name !== 'string' || !/^[^/\\]+\.pdf$/i.test(name)) throw new Error('Ungültige PDF-Referenz im Ordner.')
            try { await addFile(directory, name, prefix + name) }
            catch (error) {
                if (error.name === 'NotFoundError') throw new Error('Referenziertes PDF fehlt: ' + name + '. Bitte den Ordner prüfen oder erneut auswählen.')
                throw error
            }
        }
    }
    const dispatch = await optionalDirectory(handle, 'Versand')
    if (dispatch) {
        for (const purpose of ['Aufgaben', 'Ergebnisse']) {
            const directory = await optionalDirectory(dispatch, purpose)
            if (!directory) continue
            for await (const entry of directory.values()) {
                if (entry.kind !== 'directory' || !/^Versand_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}$/.test(entry.name)) continue
                try { await addFile(entry, 'Versandprotokoll.txt', `Versand/${purpose}/${entry.name}/Versandprotokoll.txt`) }
                catch (error) { if (error.name !== 'NotFoundError') throw error }
            }
        }
    }
    return files
}
