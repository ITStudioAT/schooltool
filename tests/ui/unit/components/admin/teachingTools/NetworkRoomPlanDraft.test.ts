import { afterEach, expect, it } from 'vitest'
import { readNetworkRoomPlanDraft, saveNetworkRoomPlanDraft } from '@/pages/admin/teachingTools/networkRoomPlanDraft'

const key = 'test-network-room-plan-draft'
const duct = { id: 'cable-duct', wallMounted: true }
const outlet = { id: 'bundle-outlet', channelMounted: true, bundleEnd: true }
const rack = { id: 'server-rack' }
const devices = [duct, outlet, rack]

afterEach(() => window.localStorage.removeItem(key))

it('keeps devices in the same rooms when loading an older plan with an equal-width foyer', () => {
    const standingTable = { id: 'standing-table' }
    window.localStorage.setItem(key, JSON.stringify({
        placements: [
            { id: 1, deviceId: rack.id, x: .25, y: .8 },
            { id: 2, deviceId: standingTable.id, x: .75, y: .3 },
        ],
        connections: [],
        zoom: 100,
    }))

    const planDevices = [...devices, standingTable]
    const restored = readNetworkRoomPlanDraft(key, planDevices)

    expect(restored.placements.map(({ x, y }) => [x, y])).toEqual([[1 / 3, .8], [5 / 6, .3]])
    expect(saveNetworkRoomPlanDraft(key, restored.placements, restored.connections, restored.zoom)).toBe(true)
    expect(readNetworkRoomPlanDraft(key, planDevices)).toEqual(restored)
})

it('keeps the other cable on its original patchpanel port after a cable is removed and the draft is reopened', () => {
    const socket = { id: 'double-network-outlet', channelMounted: true, ports: 2 }
    const workstation = { id: 'workstation', ports: 1 }
    const planDevices = [...devices, socket, workstation]
    window.localStorage.setItem(key, JSON.stringify({
        placements: [
            { id: 1, deviceId: duct.id, wallId: 'north-office-1', x: .2, y: 0 },
            { id: 2, deviceId: socket.id, channelId: 1, channelPosition: .5 },
            { id: 3, deviceId: workstation.id, x: .2, y: .2 },
            { id: 4, deviceId: workstation.id, x: .3, y: .2 },
        ],
        connections: [
            { id: 1, start: { placementId: 2, index: 0 }, end: { placementId: 3, index: 0 } },
            { id: 2, start: { placementId: 2, index: 1 }, end: { placementId: 4, index: 0 } },
        ],
    }))
    const restored = readNetworkRoomPlanDraft(key, planDevices)
    expect(restored.connections.map(({ rackPort }) => rackPort)).toEqual([0, 1])
    expect(restored.placements.slice(2).map(({ deviceNumber }) => deviceNumber)).toEqual([1, 2])

    saveNetworkRoomPlanDraft(key, restored.placements.filter(placement => placement.id !== 3), restored.connections.slice(1), 100)

    const reopened = readNetworkRoomPlanDraft(key, planDevices)
    expect(reopened.connections.map(({ rackPort }) => rackPort)).toEqual([1])
    expect(reopened.placements.at(-1)?.deviceNumber).toBe(2)
})

it('restores exact rack slots, including empty slots, and a cable bundle linked to the rack', () => {
    const placements = [
        { device: duct, id: 1, rotation: 0, wallId: 'south-server', x: .25, y: 1 },
        { device: outlet, id: 2, rotation: 0, channelId: 1, channelPosition: .5, rackId: 3 },
        { device: rack, id: 3, rotation: 0, x: .38, y: .8, rackModules: [null, 'patch-panel', null, 'router'] },
        { device: rack, id: 4, rotation: 0, x: .17, y: .8 },
    ]

    expect(saveNetworkRoomPlanDraft(key, placements, [], 100)).toBe(true)
    expect(readNetworkRoomPlanDraft(key, devices)).toEqual({ placements, connections: [], zoom: 100 })
})

it.each([
    { scenario: 'is missing', rackPlacements: [] },
    { scenario: 'has no patch panel', rackPlacements: [
        { device: rack, id: 3, rotation: 0, x: .38, y: .8, rackModules: ['router', 'switch'] },
    ] },
])('keeps the cable bundle outlet usable when the saved rack $scenario', ({ rackPlacements }) => {
    const placements = [
        { device: duct, id: 1, rotation: 0, wallId: 'south-server', x: .25, y: 1 },
        { device: outlet, id: 2, rotation: 0, channelId: 1, channelPosition: .5, rackId: 3 },
        ...rackPlacements,
    ]

    saveNetworkRoomPlanDraft(key, placements, [], 100)
    const restored = readNetworkRoomPlanDraft(key, devices)
    expect(restored.placements[1]).toEqual({
        device: outlet, id: 2, rotation: 0, channelId: 1, channelPosition: .5,
    })
    expect(restored.placements.slice(2)).toEqual(rackPlacements)
})
