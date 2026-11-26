import { afterEach, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { useNetworkPatchCables } from '@/pages/admin/teachingTools/useNetworkPatchCables'

afterEach(() => vi.restoreAllMocks())

it('stops a patch cable at the wall, allows a clear opening, and disconnects it if moved behind the wall', async () => {
    const outlet = { id: 1, device: { label: 'Netzwerkdose', ports: 1, channelMounted: true }, x: .2, y: .4 }
    const blocked = { id: 2, device: { label: 'Arbeitsplatz', ports: 1 }, x: .8, y: .9 }
    const reachable = { id: 3, device: { label: 'Access Point', ports: 1 }, x: .8, y: .4 }
    const placements = ref([outlet, blocked, reachable])
    const wall = { axis: 'vertical', band: [.49, .51], start: .6, end: 1 }
    const wallsById = ref(new Map([['wall', wall]]))
    const layer = ref(document.createElement('div'))
    layer.value.getBoundingClientRect = () => new DOMRect(0, 0, 100, 100)
    const markerElements = new Map(placements.value.map(placement => {
        const marker = layer.value.appendChild(document.createElement('button'))
        marker.dataset.placementId = String(placement.id)
        marker.getBoundingClientRect = () => new DOMRect(placement.x * 100, placement.y * 100, 0, 0)
        return [placement.id, marker]
    }))
    const patch = useNetworkPatchCables({ layer, wallsById, placements, markerElements,
        move: ref(null), clearSelection: vi.fn(), initialConnections: [] })
    const click = new MouseEvent('click')
    vi.spyOn(document, 'elementFromPoint').mockReturnValue(markerElements.get(2)!)

    patch.clickPalette(click)
    patch.chooseMarker(outlet, click)
    patch.pointerMove(new PointerEvent('pointermove', { isPrimary: true, pointerId: 1, clientX: 80, clientY: 90 }))
    expect(patch.previewLine.value?.invalid).toBe(true)
    expect(patch.previewLine.value?.end.x).toBeCloseTo(.49)
    expect(patch.availablePort(blocked)).toBeNull()
    patch.chooseMarker(blocked, click)
    expect(patch.status.value).toContain('nicht durch eine Wand')
    expect(patch.connections.value).toHaveLength(0)

    patch.chooseMarker(reachable, click)
    expect(patch.visibleConnections.value).toHaveLength(1)

    wallsById.value = new Map([['wall', { ...wall, start: .3 }]])
    await nextTick()
    expect(patch.connections.value).toHaveLength(1)
    expect(patch.visibleConnections.value).toHaveLength(1)

    wallsById.value = new Map([['wall', wall]])
    placements.value[2].y = .9
    await nextTick()
    expect(patch.visibleConnections.value).toHaveLength(0)
    expect(patch.connections.value).toHaveLength(0)
    expect(patch.status.value).toContain('wurden getrennt')
})
