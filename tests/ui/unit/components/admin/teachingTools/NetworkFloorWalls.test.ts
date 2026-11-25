import { describe, expect, it } from 'vitest'
import { channelCorners, outletMountPoint, snapToChannel } from '@/pages/admin/teachingTools/networkFloorWalls'

const bounds = { width: 800, height: 450 }
const north = { id: 'north-office', axis: 'horizontal', line: 0, start: 0, end: .5, side: 'north' }
const west = { id: 'west-office', axis: 'vertical', line: 0, start: 0, end: .33, side: 'west' }
const northFoyer = { ...north, id: 'north-foyer', start: .5, end: 1 }
const upperPartition = { id: 'above-door', axis: 'vertical', line: .5, start: 0, end: .12, side: 'partition' }
const lowerPartition = { ...upperPartition, id: 'below-door', start: .2, end: .33 }

describe('Room plan channel placement', () => {
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
