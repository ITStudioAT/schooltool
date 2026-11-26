import { patchPanelModule, rackHasPatchPanel } from './networkRackModules'

export function isNumberedDevice(device) {
    return Boolean(device.ports && !device.channelMounted)
}

export function nextDeviceNumber(device, placements) {
    const usedNumbers = new Set(placements.filter(placement => placement.device.id === device.id)
        .map(placement => placement.deviceNumber))
    let number = 1
    while (usedNumbers.has(number)) number++
    return number
}

export function patchDeviceLabel({ device, deviceNumber }) {
    return deviceNumber === undefined ? device.label : `${device.label} ${deviceNumber}`
}

export function firstFreeRackPort(occupiedPorts) {
    let port = 0
    while (occupiedPorts.has(port)) port++
    return port
}

export function floorPatchAssignments(placements, floorConnections) {
    const placementsById = new Map(placements.map(placement => [placement.id, placement]))
    return floorConnections.flatMap(({ start, end, rackPort }) => {
        const first = placementsById.get(start.placementId)
        const second = placementsById.get(end.placementId)
        if (!first || !second) return []

        const [outlet, placement] = first.device.channelMounted ? [first, second] : [second, first]
        return [{ port: rackPort, placement, channelId: outlet.channelId }]
    }).sort((first, second) => first.port - second.port)
}

export function rackCableAssignments(placements, assignments, neighborsByChannel) {
    const rackOutlets = new Map(placements.filter(rackHasPatchPanel).map(({ id }) => [id, []]))
    for (const outlet of placements) {
        if (outlet.device.bundleEnd && neighborsByChannel.has(outlet.channelId)) {
            rackOutlets.get(outlet.rackId)?.push(outlet)
        }
    }

    return new Map([...rackOutlets].map(([rackId, outlets]) => {
        const towardRack = new Map(outlets.map(({ channelId, channelPosition }) =>
            [channelId, { nextChannelId: null, position: channelPosition }]))
        for (const channelId of towardRack.keys()) {
            for (const neighbor of neighborsByChannel.get(channelId)) {
                if (towardRack.has(neighbor.id)) continue
                towardRack.set(neighbor.id, { nextChannelId: channelId, position: neighbor.position })
            }
        }

        const reachableAssignments = assignments.filter(({ channelId }) => towardRack.has(channelId)).map(assignment => {
            const channelPath = []
            for (let channelId = assignment.channelId; channelId !== null;) {
                const { nextChannelId, position } = towardRack.get(channelId)
                channelPath.push({ channelId, position })
                channelId = nextChannelId
            }
            return { ...assignment, channelPath }
        })
        return [rackId, reachableAssignments]
    }))
}

export function networkStatus(placements, assignmentsByRack) {
    const racks = new Map(placements.filter(placement => placement.device.id === 'server-rack')
        .map(rack => [rack.id, rackNetworkStatus(rack)]))
    const wiredAssignments = [...racks].flatMap(([id, { localPorts, internetPorts }]) =>
        (assignmentsByRack.get(id) ?? []).filter(({ port }) => localPorts.has(port))
            .map(assignment => ({ ...assignment, hasInternet: internetPorts.has(assignment.port) })))
    const activeChannels = new Map()
    for (const { channelPath } of wiredAssignments) {
        for (const { channelId, position } of channelPath) {
            if (!activeChannels.has(channelId)) activeChannels.set(channelId, position)
        }
    }

    const internetDevices = new Set(wiredAssignments.filter(({ hasInternet }) => hasInternet).map(({ placement }) => placement.id))
    const wiredDevices = wiredAssignments.map(({ placement }) => placement)
    const clients = new Map(wiredDevices.filter(({ device }) => ['workstation', 'standing-table'].includes(device.id))
        .map(({ id }) => [id, 'lan']))

    const lan = clients.size > 0
    const accessPoints = new Set(wiredDevices.filter(({ device }) => device.id === 'access-point').map(({ id }) => id))
    if (accessPoints.size) {
        const wirelessInternet = [...accessPoints].some(id => internetDevices.has(id))
        for (const { id, device } of placements) {
            if (device.id !== 'standing-table' || clients.has(id)) continue
            clients.set(id, 'wlan')
            if (wirelessInternet) internetDevices.add(id)
        }
    }

    return { internet: [...racks.values()].some(rack => rack.hasUplink), lan, clients, accessPoints, internetDevices, activeChannels,
        racks }
}

function rackNetworkStatus({ rackModules = [], rackPatchConnections = [] }) {
    const peers = rackPatchConnections.flatMap(({ start, end }) => [[start, end], [end, start]])
    const uplinkSwitches = new Set(peers
        .filter(([source, target]) => rackModules[source.slot] === 'router' && rackModules[target.slot] === 'switch')
        .map(([, target]) => target.slot))

    for (const slot of uplinkSwitches) {
        for (const [source, target] of peers) {
            if (source.slot === slot && rackModules[target.slot] === 'switch') uplinkSwitches.add(target.slot)
        }
    }

    const panelSlot = rackModules.indexOf(patchPanelModule)
    const switchLinks = peers.filter(([source, target]) => source.slot === panelSlot && rackModules[target.slot] === 'switch')
    const localPorts = new Set(switchLinks.map(([source]) => source.port))
    const internetPorts = new Set(switchLinks.filter(([, target]) => uplinkSwitches.has(target.slot)).map(([source]) => source.port))
    const serverSwitches = new Set(peers.filter(([source, target]) =>
        rackModules[source.slot] === 'server' && rackModules[target.slot] === 'switch').map(([, target]) => target.slot))
    const serverPortGroups = [...serverSwitches].map(slot =>
        new Set(switchLinks.filter(([, target]) => target.slot === slot).map(([source]) => source.port)))
    return { hasUplink: uplinkSwitches.size > 0, localPorts, internetPorts, serverPortGroups }
}
