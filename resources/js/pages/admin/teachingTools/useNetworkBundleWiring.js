import { computed, ref, shallowRef } from 'vue'
import { rackHasPatchPanel } from './networkRackModules'

const dragThreshold = 6
const rackClearance = 12

export function useNetworkBundleWiring({ layer, placements, endPoint }) {
    const active = ref(false)
    const status = ref('')
    const outletId = ref(null)
    const cursor = shallowRef(null)
    const outlet = computed(() => placements.value.find(placement => placement.id === outletId.value))
    let gesture = null

    const connections = computed(() => placements.value.flatMap(placement => {
        if (!placement.rackId) return []
        const start = pointFor(placement.id, true)
        const end = pointFor(placement.rackId, true)
        return start && end ? [{ id: placement.id, start, end }] : []
    }))

    const preview = computed(() => {
        if (!cursor.value || !outlet.value) return null
        const start = pointFor(outlet.value.id)
        const { point, target } = cursor.value
        const ready = rackHasPatchPanel(target)
        const end = ready ? pointFor(target.id) ?? point : point
        return start ? { start, end, invalid: target && !ready } : null
    })

    function pointFor(placementId, followDrag = false) {
        return endPoint({ placementId, index: 0 }, followDrag)
    }

    function cablePoints({ start, end }) {
        const points = [start]
        if (end.rackHalfWidth != null) {
            const bounds = layer.value.getBoundingClientRect()
            const aboveRack = Math.max(0, end.y - rackClearance / bounds.height)
            const sideClearance = end.rackHalfWidth + rackClearance / bounds.width
            const besideRack = start.x <= end.x
                ? Math.min(start.x, Math.max(0, end.x - sideClearance))
                : Math.max(start.x, Math.min(1, end.x + sideClearance))
            points.push({ x: besideRack, y: start.y }, { x: besideRack, y: aboveRack }, { x: end.x, y: aboveRack })
        }
        points.push(end)
        return points.map(point => `${point.x * 100},${point.y * 100}`).join(' ')
    }

    function availableOutlet(placement) {
        return placement?.device.bundleEnd && !placement.rackId
    }

    function begin(source = null) {
        if (!placements.value.some(rackHasPatchPanel)) {
            status.value = 'Baue zuerst ein Patchpanel in einen Serverschrank ein.'
            return false
        }
        if (!source && !placements.value.some(availableOutlet)) {
            status.value = 'Setze zuerst einen freien Kabelstrang-Ausgang an einen Kabelkanal im Serverraum.'
            return false
        }
        active.value = true
        outletId.value = source?.id ?? null
        guide()
        return true
    }

    function guide() {
        status.value = outlet.value ? 'Wähle einen Serverschrank mit Patchpanel.'
            : 'Ziehe vom Kabelstrang-Ausgang zu einem Serverschrank mit Patchpanel.'
    }

    function choose(placement) {
        if (availableOutlet(placement)) {
            outletId.value = placement.id
        } else if (connect(placement)) {
            return
        }
        guide()
    }

    function connect(rack) {
        const source = outlet.value
        if (!availableOutlet(source) || !rackHasPatchPanel(rack)) return false

        source.rackId = rack.id
        cancel()
        status.value = 'Kabelstrang mit dem Serverschrank verbunden.'
        return true
    }

    function unlink(placement) {
        delete placement.rackId
        status.value = 'Kabelstrang vom Serverschrank getrennt.'
    }

    function startDraw(event, placement) {
        if (!active.value || !availableOutlet(placement) || !event.isPrimary || event.button !== 0 || gesture) return false

        gesture = { pointerId: event.pointerId, outletId: placement.id, x: event.clientX, y: event.clientY }
        event.currentTarget.setPointerCapture(event.pointerId)
        return true
    }

    function moveDraw(event) {
        if (gesture?.pointerId !== event.pointerId) return
        if (!cursor.value && Math.hypot(event.clientX - gesture.x, event.clientY - gesture.y) < dragThreshold) return

        const surface = layer.value
        const marker = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-placement-id]')
        const target = marker && surface.contains(marker)
            ? placements.value.find(placement => placement.id === Number(marker.dataset.placementId)) : null
        const bounds = surface.getBoundingClientRect()
        outletId.value = gesture.outletId
        cursor.value = {
            target,
            point: { x: (event.clientX - bounds.left) / bounds.width, y: (event.clientY - bounds.top) / bounds.height },
        }
    }

    function finishDraw(event) {
        if (gesture?.pointerId !== event.pointerId) return false
        moveDraw(event)
        const drop = cursor.value
        outletId.value = gesture.outletId
        cancelDraw(event)
        if (drop && connect(drop.target)) return true
        guide()
        return true
    }

    function cancelDraw(event) {
        if (gesture?.pointerId !== event.pointerId) return
        gesture = null
        cursor.value = null
    }

    function cancel() {
        active.value = false
        outletId.value = null
        cursor.value = null
        gesture = null
        status.value = ''
    }

    return { active, status, outlet, connections, preview, cablePoints, availableOutlet, begin, guide, choose, unlink, startDraw,
        moveDraw, finishDraw, cancelDraw, cancel }
}
