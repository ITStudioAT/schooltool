import { describe, expect, it } from 'vitest'
import {
    channelCorners, firstWallIntersection, measureNetworkWalls, outletMountPoint, snapToChannel, snapToNetworkWall,
} from '@/pages/admin/teachingTools/networkFloorWalls'

const bounds = { width: 800, height: 450 }
const north = { id: 'north-office', axis: 'horizontal', line: 0, start: 0, end: .5, side: 'north' }
const west = { id: 'west-office', axis: 'vertical', line: 0, start: 0, end: .33, side: 'west' }
const northFoyer = { ...north, id: 'north-foyer', start: .5, end: 1 }
const upperPartition = { id: 'above-door', axis: 'vertical', line: .5, start: 0, end: .12, side: 'partition' }
const lowerPartition = { ...upperPartition, id: 'below-door', start: .2, end: .33 }

function measureFloor() {
    const plan = document.createElement('div')
    const surface = plan.appendChild(document.createElement('div'))
    surface.getBoundingClientRect = () => new DOMRect(0, 0, bounds.width, bounds.height)
    for (const [roomBounds, doorBounds] of [
        [[0, 0, 399, 148], [369, 59, 30, 30]],
        [[0, 150, 399, 149], [369, 209, 30, 30]],
        [[0, 301, 399, 149], [369, 361, 30, 30]],
        [[401, 0, 399, 450], [585, 420, 30, 30]],
    ]) {
        const room = plan.appendChild(document.createElement('div'))
        room.className = 'network-floor__room'
        room.getBoundingClientRect = () => new DOMRect(...roomBounds)
        const door = room.appendChild(document.createElement('span'))
        door.className = 'network-floor__door'
        door.getBoundingClientRect = () => new DOMRect(...doorBounds)
    }
    return measureNetworkWalls(surface)
}

describe('Room plan channel placement', () => {
    it('snaps to either face of vertical and horizontal shared walls and leaves door gaps clear', () => {
        const walls = measureFloor()
        const snap = (x: number, y: number) => snapToNetworkWall(walls, bounds, { x: x / bounds.width, y: y / bounds.height })
        expect(snap(389, 116)).toMatchObject({ wallId: 'partition-office-1-bottom', x: 399 / 800 })
        expect(snap(411, 116)).toMatchObject({ wallId: 'partition-office-1-bottom-foyer', x: 401 / 800 })
        expect(snap(220, 138)).toMatchObject({ wallId: 'offices-divider-office-1', y: 148 / 450 })
        expect(snap(220, 160)).toMatchObject({ wallId: 'offices-divider-office-2', y: 150 / 450 })
        expect(snap(220, 289)).toMatchObject({ wallId: 'server-divider-office-2', y: 299 / 450 })
        expect(snap(220, 311)).toMatchObject({ wallId: 'server-divider-server', y: 301 / 450 })
        expect(snap(400, 116)).toBeNull()
        expect(snap(397, 74)).toMatchObject({ x: 399 / 800, y: 89 / 450 })
    })

    it('mounts an outlet on the approached wall face without reaching through the wall', () => {
        const walls = new Map(measureFloor().map(wall => [wall.id, wall]))
        const channels = [
            { id: 1, wallId: 'partition-office-1-bottom' },
            { id: 2, wallId: 'partition-office-1-bottom-foyer' },
        ]
        const office = { x: 394 / 800, y: 116 / 450 }
        const foyer = { x: 406 / 800, y: 116 / 450 }
        expect(snapToChannel(channels, walls, bounds, office)).toMatchObject({ channelId: 1 })
        expect(snapToChannel(channels, walls, bounds, foyer)).toMatchObject({ channelId: 2 })
        expect(snapToChannel(channels.slice(0, 1), walls, bounds, foyer)).toBeNull()
    })

    it('connects only coincident perpendicular endpoints in the preview and the placed plan', () => {
        expect(channelCorners([north, west], west.id)).toEqual([
            expect.objectContaining({ x: 0, y: 0, arms: ['right', 'down'], preview: true }),
        ])
        expect(channelCorners([north, west])).toEqual([
            expect.objectContaining({ x: 0, y: 0, preview: false }),
        ])
        expect(channelCorners([north, northFoyer, lowerPartition])).toEqual([])
        expect(channelCorners([north])).toEqual([])
    })

    it('mounts onto a placed channel along its usable length and excludes endpoints, door gaps and bare walls', () => {
        const walls = new Map([north, west, upperPartition, lowerPartition].map(wall => [wall.id, wall]))
        const channels = [{ id: 1, wallId: west.id }, { id: 2, wallId: upperPartition.id }, { id: 3, wallId: lowerPartition.id }]
        const point = { x: .02, y: .165 }
        const mount = snapToChannel(channels, walls, bounds, point)

        expect(mount).toEqual({ channelId: 1, channelPosition: .5 })
        expect(outletMountPoint(west, mount!.channelPosition)).toEqual({ x: 0, y: .165 })
        expect(outletMountPoint(north, mount!.channelPosition)).toEqual({ x: .25, y: 0 })

        expect(snapToChannel([], walls, bounds, point)).toBeNull()
        expect(snapToChannel(channels, walls, bounds, { x: .25, y: .01 })).toBeNull()
        expect(snapToChannel(channels, walls, bounds, { x: .5, y: .16 })).toBeNull()
        expect(snapToChannel(channels, walls, bounds, { x: .01, y: .01 })).toBeNull()
    })
})

it('stops the patch cable at the first wall face and leaves the doorway open', () => {
    const walls = measureFloor()
    const hit = (x1: number, y1: number, x2: number, y2: number) => firstWallIntersection(walls,
        { x: x1 / bounds.width, y: y1 / bounds.height }, { x: x2 / bounds.width, y: y2 / bounds.height })

    expect(hit(300, 115, 500, 115)).toBeCloseTo(99 / 200)
    expect(hit(500, 115, 300, 115)).toBeCloseTo(99 / 200)
    expect(hit(200, 100, 200, 350)).toBeCloseTo(48 / 250)
    expect(hit(300, 74, 500, 74)).toBeNull()
    expect(hit(300, 74, 380, 115)).toBeNull()
    expect(hit(398, 57, 402, 62)).toBeCloseTo(1 / 4)
})
