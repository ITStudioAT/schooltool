import { afterEach, expect, it } from 'vitest'
import { readNetworkRoomPlanDraft, saveNetworkRoomPlanDraft } from '@/pages/admin/teachingTools/networkRoomPlanDraft'

const key = 'test-network-room-plan-draft'
const duct = { id: 'cable-duct', wallMounted: true }
const outlet = { id: 'bundle-outlet', channelMounted: true, bundleEnd: true }
const rack = { id: 'server-rack' }
const devices = [duct, outlet, rack]

afterEach(() => window.localStorage.removeItem(key))

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
