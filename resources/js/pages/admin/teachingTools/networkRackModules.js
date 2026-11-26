export const patchPanelModule = 'patch-panel'

export const rackModuleLabels = {
    [patchPanelModule]: 'Patchpanel',
    switch: 'Switch',
    router: 'Router',
    server: 'Datenserver',
}

export const rackModulePorts = { [patchPanelModule]: 8, switch: 8, router: 3, server: 2 }

export function rackHasPatchPanel(placement) {
    return placement?.device.id === 'server-rack' && placement.rackModules?.includes(patchPanelModule) === true
}

export function normalizeRackModules(modules) {
    const slots = Array.from(modules, module =>
        typeof module === 'string' && Object.hasOwn(rackModuleLabels, module) ? module : null)
    while (slots.at(-1) === null) slots.pop()
    return slots
}

export function rackPortKey({ slot, port }) {
    return `${slot}:${port}`
}

export function normalizeRackPatchConnections(connections, modules) {
    if (!Array.isArray(connections)) return []

    const occupied = new Set()
    function freePort(endpoint) {
        const module = modules[endpoint?.slot]
        return Number.isSafeInteger(endpoint?.slot) && Number.isSafeInteger(endpoint?.port) && endpoint.port >= 0
            && (module === patchPanelModule || endpoint.port < rackModulePorts[module]) && !occupied.has(rackPortKey(endpoint))
    }

    return connections.flatMap(connection => {
        const { start, end } = connection ?? {}
        if (!freePort(start) || !freePort(end) || start.slot === end.slot) return []

        occupied.add(rackPortKey(start))
        occupied.add(rackPortKey(end))
        return [{ start: { slot: start.slot, port: start.port }, end: { slot: end.slot, port: end.port } }]
    })
}
