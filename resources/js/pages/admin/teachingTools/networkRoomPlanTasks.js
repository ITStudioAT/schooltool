import { patchPanelModule, rackHasPatchPanel } from './networkRackModules'

export function roomPlanTaskCompletion({ placements, rooms, walls, neighborsByChannel, floorAssignments, assignmentsByRack, network }) {
    function roomOf(placement) {
        for (const [id, { left, right, top, bottom }] of rooms) {
            if (placement.x >= left && placement.x <= right && placement.y >= top && placement.y <= bottom) return id
        }
    }

    const workstations = placements.filter(({ device }) => device.id === 'workstation')
    const standingTables = placements.filter(({ device }) => device.id === 'standing-table')
    const foyerAccessPoints = placements.filter(placement => placement.device.id === 'access-point' && roomOf(placement) === 'foyer')
    const serverRacks = placements.filter(placement => placement.device.id === 'server-rack' && roomOf(placement) === 'server')
    const firstOfficeCount = workstations.filter(placement => roomOf(placement) === 'office-1').length
    const secondOfficeCount = workstations.filter(placement => roomOf(placement) === 'office-2').length
    const officesReady = workstations.length === 5 && firstOfficeCount + secondOfficeCount === 5
        && (firstOfficeCount === 2 || firstOfficeCount === 3)

    const ductRooms = new Map(placements.filter(placement => placement.wallId)
        .map(placement => [placement.id, walls.get(placement.wallId)?.roomId]))
    const ductsToServerRoom = new Set([...ductRooms].filter(([, room]) => room === 'server').map(([id]) => id))
    for (const id of ductsToServerRoom) {
        for (const neighbor of neighborsByChannel.get(id) ?? []) ductsToServerRoom.add(neighbor.id)
    }
    const roomSockets = new Map()
    for (const { device, channelId } of placements) {
        if (!device.channelMounted || !device.ports || !ductsToServerRoom.has(channelId)) continue
        const room = ductRooms.get(channelId)
        roomSockets.set(room, (roomSockets.get(room) ?? 0) + device.ports)
    }

    const patchedDevices = new Set(floorAssignments.map(({ placement }) => placement.id))
    const bundleRacks = new Set(placements.filter(placement => placement.device.bundleEnd
        && ductRooms.get(placement.channelId) === 'server').map(placement => placement.rackId))
    const wiredRacks = serverRacks.filter(rack => bundleRacks.has(rack.id)).map(rack => ({
        ...network.racks.get(rack.id),
        incoming: assignmentsByRack.get(rack.id) ?? [],
    }))

    function hasInternet(placement, connection) {
        return network.clients.get(placement.id) === connection && network.internetDevices.has(placement.id)
    }

    return {
        furniture: officesReady && standingTables.length === 4 && standingTables.every(placement => roomOf(placement) === 'foyer'),
        rack: serverRacks.some(rack => [patchPanelModule, 'switch', 'router', 'server']
            .every(module => rack.rackModules?.includes(module))),
        ducts: officesReady && roomSockets.get('office-1') >= firstOfficeCount && roomSockets.get('office-2') >= secondOfficeCount,
        accessPoint: foyerAccessPoints.length > 0 && roomSockets.get('foyer') >= 1,
        deviceCables: workstations.length === 5 && workstations.every(({ id }) => patchedDevices.has(id))
            && foyerAccessPoints.some(({ id }) => patchedDevices.has(id)),
        bundle: serverRacks.some(rack => rackHasPatchPanel(rack) && bundleRacks.has(rack.id)),
        rackCables: workstations.length === 5 && standingTables.length === 4
            && workstations.every(placement => hasInternet(placement, 'lan'))
            && standingTables.every(placement => hasInternet(placement, 'wlan'))
            && wiredRacks.some(({ hasReachableServer, incoming, internetPorts }) =>
                hasReachableServer && incoming.some(({ placement }) => placement.device.id === 'access-point')
                && incoming.every(({ port }) => internetPorts.has(port))),
    }
}
