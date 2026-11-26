import { afterEach, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useNetworkBundleWiring } from '@/pages/admin/teachingTools/useNetworkBundleWiring'
import { useNetworkPatchCables } from '@/pages/admin/teachingTools/useNetworkPatchCables'

function setupRackDrag() {
    const placements = ref([
        { id: 1, device: { id: 'bundle-outlet', bundleEnd: true }, rackId: undefined },
        { id: 2, device: { id: 'server-rack' }, rackModules: ['router'] },
        { id: 3, device: { id: 'server-rack' }, rackModules: [null, 'patch-panel'] },
    ])
    const surface = document.createElement('div')
    surface.getBoundingClientRect = () => new DOMRect(0, 0, 500, 400)
    const [outlet, ...racks] = placements.value.map(placement => {
        const marker = surface.appendChild(document.createElement('button'))
        marker.dataset.placementId = String(placement.id)
        marker.setPointerCapture = vi.fn()
        return marker
    })
    const source = placements.value[0]
    const event = (clientX: number, clientY: number) => ({
        pointerId: 1, isPrimary: true, button: 0, currentTarget: outlet, clientX, clientY,
    })
    const wiring = useNetworkBundleWiring({
        layer: ref(surface),
        placements,
        endPoint: ({ placementId }) => placementId === 1 ? { x: .2, y: .75 } : { x: .6, y: .5 },
    })
    const hitTest = vi.spyOn(document, 'elementFromPoint')
    return { wiring, source, racks, event, hitTest, placements }
}

afterEach(() => vi.restoreAllMocks())

it('previews a dragged bundle, rejects a rack without a patch panel, and connects to a fitted rack', () => {
    const { wiring, source, racks, event, hitTest } = setupRackDrag()
    expect(wiring.begin()).toBe(true)

    wiring.startDraw(event(100, 300), source)
    hitTest.mockReturnValue(racks[0])
    wiring.moveDraw(event(200, 180))
    expect(wiring.preview.value).toEqual({ start: { x: .2, y: .75 }, end: { x: .4, y: .45 }, invalid: true })
    wiring.finishDraw(event(200, 180))
    expect(source.rackId).toBeUndefined()
    expect(wiring.active.value).toBe(true)
    expect(wiring.preview.value).toBeNull()

    wiring.startDraw(event(100, 300), source)
    hitTest.mockReturnValue(racks[1])
    wiring.finishDraw(event(302, 203))
    expect(source.rackId).toBe(3)
    expect(wiring.active.value).toBe(false)
    expect(wiring.connections.value).toEqual([{ id: 1, start: { x: .2, y: .75 }, end: { x: .6, y: .5 } }])
})

it('requires a rack with a patch panel before bundle wiring can start', () => {
    const { wiring, placements } = setupRackDrag()
    placements.value.pop()
    expect(wiring.begin()).toBe(false)
    expect(wiring.active.value).toBe(false)
})

it('routes a bundle down into the rack top and keeps that anchor while the rack moves', () => {
    const surface = document.createElement('div')
    surface.getBoundingClientRect = () => new DOMRect(0, 0, 500, 400)
    const marker = surface.appendChild(document.createElement('button'))
    marker.getBoundingClientRect = () => new DOMRect(200, 100, 64, 100)
    const rack = marker.appendChild(document.createElement('span'))
    rack.className = 'network-rack-icon'
    rack.getBoundingClientRect = () => new DOMRect(203, 103, 58, 82)
    const layer = ref(surface)
    const placements = ref([{ id: 3, device: { id: 'server-rack' }, rackModules: ['patch-panel'] }])
    const move = ref<{ placementId: number; moved: boolean; position: { x: number; y: number }; x: number; y: number } | null>(null)
    const geometry = useNetworkPatchCables({
        layer, wallsById: ref(new Map()), placements, markerElements: new Map([[3, marker]]), move,
        clearSelection: vi.fn(), initialConnections: [],
    })
    const wiring = useNetworkBundleWiring({ layer, placements, endPoint: geometry.endPoint })
    geometry.refreshPositions()

    const port = { placementId: 3, index: 0 }
    expect(geometry.endPoint(port)).toEqual({ x: 232 / 500, y: 103 / 400, rackHalfWidth: 29 / 500 })

    move.value = { placementId: 3, moved: true, position: { x: .7, y: .6 }, x: 350, y: 240 }
    const end = geometry.endPoint(port, true)
    expect(end).toEqual({ x: .7, y: 193 / 400, rackHalfWidth: 29 / 500 })
    const points = wiring.cablePoints({ start: { x: .7, y: .8 }, end })
        .split(' ').map(point => point.split(',').map(Number))
    expect(points[1][0]).toBeCloseTo((350 - 29 - 12) / 5)
    expect(points.at(-2)![0]).toBeCloseTo(70)
    expect(points.at(-2)![1]).toBeCloseTo((193 - 12) / 4)
    expect(points.at(-1)![1]).toBeCloseTo(193 / 4)
})
