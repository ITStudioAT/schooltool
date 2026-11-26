import { canConnectPatchDevices } from './useNetworkPatchCables'
import { normalizeRackModules, rackHasPatchPanel } from './networkRackModules'

function emptyDraft() {
    return { placements: [], connections: [] }
}

function isId(value) {
    return Number.isSafeInteger(value) && value > 0
}

function isRatio(value) {
    return Number.isFinite(value) && value >= 0 && value <= 1
}

export function readNetworkRoomPlanDraft(key, devices) {
    let draft
    try {
        draft = key ? JSON.parse(window.localStorage.getItem(key)) : null
    } catch {
        return emptyDraft()
    }
    if (!Array.isArray(draft?.placements) || !Array.isArray(draft.connections)) return emptyDraft()

    const placementsById = new Map()
    for (const item of draft.placements) {
        const device = devices.find(device => device.id === item?.deviceId)
        if (!device || device.wiringTool || !isId(item.id) || placementsById.has(item.id)) continue

        const placement = { id: item.id, device, rotation: [90, 180, 270].includes(item.rotation) ? item.rotation : 0 }
        if (device.channelMounted) {
            if (!isId(item.channelId) || !isRatio(item.channelPosition)) continue
            Object.assign(placement, { channelId: item.channelId, channelPosition: item.channelPosition })
        } else {
            if (!isRatio(item.x) || !isRatio(item.y) || (device.wallMounted && typeof item.wallId !== 'string')) continue
            Object.assign(placement, { x: item.x, y: item.y })
            if (device.wallMounted) placement.wallId = item.wallId
        }
        if (device.id === 'server-rack' && Array.isArray(item.rackModules)) {
            placement.rackModules = normalizeRackModules(item.rackModules)
        }
        if (device.bundleEnd && isId(item.rackId)) placement.rackId = item.rackId
        placementsById.set(item.id, placement)
    }
    for (const [id, placement] of placementsById) {
        if (placement.device.channelMounted && !placementsById.get(placement.channelId)?.device.wallMounted) {
            placementsById.delete(id)
        }
        if (placement.rackId && !rackHasPatchPanel(placementsById.get(placement.rackId))) {
            delete placement.rackId
        }
    }

    const connections = []
    const connectionIds = new Set()
    const usedPorts = new Set()
    function availablePort(port) {
        const limit = placementsById.get(port?.placementId)?.device.ports
        return Number.isInteger(port?.index) && port.index >= 0 && port.index < limit
            && !usedPorts.has(`${port.placementId}:${port.index}`)
    }
    for (const item of draft.connections) {
        if (!isId(item?.id) || connectionIds.has(item.id) || !availablePort(item.start) || !availablePort(item.end)) continue
        if (!canConnectPatchDevices(placementsById.get(item.start.placementId).device,
            placementsById.get(item.end.placementId).device)) continue

        connections.push({ id: item.id, start: item.start, end: item.end })
        connectionIds.add(item.id)
        for (const port of [item.start, item.end]) usedPorts.add(`${port.placementId}:${port.index}`)
    }
    return { placements: [...placementsById.values()], connections, zoom: draft.zoom }
}

export function saveNetworkRoomPlanDraft(key, placements, connections, zoom) {
    try {
        const items = placements.map(({ device, ...position }) => ({ deviceId: device.id, ...position }))
        window.localStorage.setItem(key, JSON.stringify({ placements: items, connections, zoom }))
        return true
    } catch {
        return false
    }
}

