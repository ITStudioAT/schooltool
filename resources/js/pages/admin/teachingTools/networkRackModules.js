export const patchPanelModule = 'patch-panel'

export const rackModuleLabels = {
    [patchPanelModule]: 'Patchpanel',
    switch: 'Switch',
    router: 'Router',
    server: 'Server',
}

export function rackHasPatchPanel(placement) {
    return placement?.device.id === 'server-rack' && placement.rackModules?.includes(patchPanelModule) === true
}

export function normalizeRackModules(modules) {
    const slots = Array.from(modules, module =>
        typeof module === 'string' && Object.hasOwn(rackModuleLabels, module) ? module : null)
    while (slots.at(-1) === null) slots.pop()
    return slots
}
