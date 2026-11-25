import { computed, ref, shallowRef, watch } from 'vue'

export function useNetworkPatchCables({ layer, placements, markerElements, move, clearSelection }) {
    const active = ref(false)
    const firstEnd = shallowRef(null)
    const hover = shallowRef(null)
    const status = ref('')
    const connections = ref([])
    const selectedConnectionId = ref(null)
    const positions = shallowRef(new Map())
    let nextId = 1
    let paletteGesture = null
    let handledButton = null

    watch(() => placements.value.map(({ id, x, y, rotation, wallId, channelId, channelPosition }) =>
        [id, x, y, rotation, wallId, channelId, channelPosition]), refreshPositions, { flush: 'post' })

    const visibleConnections = computed(() => connections.value.flatMap(connection => {
        const start = endPoint(connection.start, true)
        const end = endPoint(connection.end, true)
        const label = `Patchkabel zwischen ${endLabel(connection.start)} und ${endLabel(connection.end)}`
        return start && end ? [{ id: connection.id, label, start, end }] : []
    }))

    const previewLine = computed(() => {
        const start = firstEnd.value && endPoint(firstEnd.value)
        const target = hover.value
        const end = target?.port ? endPoint(target.port) : target?.point
        return start && end ? { start, end, invalid: !target.port } : null
    })

    function refreshPositions() {
        const surface = layer.value?.getBoundingClientRect()
        if (!surface?.width) return

        const measured = new Map()
        for (const placement of placements.value) {
            const marker = markerElements.get(placement.id)
            if (!marker || !placement.device.ports) continue
            const sockets = [...marker.querySelectorAll('.network-outlet-icon > span')]
            const equipment = marker.querySelector('.network-floor__workstation-graphic, .network-standing-table-icon__laptop')
                ?? marker.querySelector('.network-floor__device-drawing')
            measured.set(placement.id, (sockets.length ? sockets : [equipment ?? marker]).map(node => {
                const rect = node.getBoundingClientRect()
                return normalizedPoint(surface, rect.left + rect.width / 2, rect.top + rect.height / 2)
            }))
        }
        positions.value = measured
    }

    function endPoint({ placementId, index }, followDrag = false) {
        const gesture = move.value
        if (followDrag && gesture?.placementId === placementId && gesture.moved && gesture.position) {
            return normalizedPoint(layer.value.getBoundingClientRect(), gesture.x, gesture.y)
        }
        const points = positions.value.get(placementId)
        return points?.[index] ?? points?.[0]
    }

    function endLabel({ placementId, index }) {
        const device = placements.value.find(item => item.id === placementId)?.device
        return device?.ports === 1 ? device.label : `${device?.label}, Anschluss ${index + 1}`
    }

    function availablePort(placement, point) {
        if (!placement?.device.ports || firstEnd.value?.placementId === placement.id) return null
        const used = new Set(connections.value.flatMap(({ start, end }) => [start, end])
            .filter(port => port.placementId === placement.id).map(port => port.index))
        const limit = placement.device.ports === 'multiple' ? used.size + 1 : placement.device.ports
        const available = Array.from({ length: limit }, (_, index) => index).filter(index => !used.has(index))
        if (!available.length) return null

        const index = available.length === 2 && point && positions.value.get(placement.id)?.length === 2
            && point.x > (positions.value.get(placement.id)[0].x + positions.value.get(placement.id)[1].x) / 2
            ? available[1] : available[0]
        return { placementId: placement.id, index }
    }

    function normalizedPoint(bounds, x, y) {
        return { x: (x - bounds.left) / bounds.width, y: (y - bounds.top) / bounds.height }
    }

    function pointerTarget(event) {
        const surface = layer.value
        const target = document.elementFromPoint(event.clientX, event.clientY)
        if (!surface?.contains(target)) return null

        const point = normalizedPoint(surface.getBoundingClientRect(), event.clientX, event.clientY)
        const id = Number(target.closest('[data-placement-id]')?.dataset.placementId)
        const placement = placements.value.find(item => item.id === id)
        return { point, port: availablePort(placement, point) }
    }

    function activate() {
        clearSelection()
        active.value = true
        selectedConnectionId.value = null
        refreshPositions()
        guide()
    }

    function guide() {
        const device = firstEnd.value && placements.value.find(item => item.id === firstEnd.value.placementId)?.device.label
        status.value = device ? `${device} verbunden. Wähle den zweiten Anschluss.`
            : 'Wähle den ersten Anschluss für das Patchkabel.'
    }

    function cancel() {
        active.value = false
        firstEnd.value = null
        hover.value = null
        status.value = ''
        paletteGesture = null
    }

    function choose(port) {
        if (!port) {
            if (firstEnd.value) {
                cancel()
                status.value = 'Begonnene Patchkabelverbindung abgebrochen.'
                return
            }
            status.value = 'Wähle einen freien Netzwerkanschluss an einem passenden Gerät.'
            return
        }
        if (!firstEnd.value) {
            firstEnd.value = port
            guide()
            return
        }
        connections.value.push({ id: nextId++, start: firstEnd.value, end: port })
        cancel()
        status.value = 'Patchkabel verbunden.'
    }

    function chooseMarker(placement, event) {
        const point = event.detail === 0 && event.type === 'click' ? null : pointerTarget(event)?.point
        choose(availablePort(placement, point))
    }

    function startPaletteDrag(event) {
        if (!event.isPrimary || event.button !== 0) return
        handledButton = null
        paletteGesture = { id: event.pointerId, x: event.clientX, y: event.clientY, wasActive: active.value,
            button: event.currentTarget, moved: false }
        event.currentTarget.setPointerCapture(event.pointerId)
        if (!active.value) activate()
    }

    function pointerMove(event) {
        if (!active.value || !event.isPrimary) return
        if (paletteGesture?.id === event.pointerId) {
            paletteGesture.moved ||= Math.hypot(event.clientX - paletteGesture.x, event.clientY - paletteGesture.y) >= 6
        }
        hover.value = pointerTarget(event)
    }

    function finishPaletteDrag(event) {
        const gesture = paletteGesture
        if (gesture?.id !== event.pointerId) return
        pointerMove(event)
        paletteGesture = null
        handledButton = gesture.button
        if (gesture.moved) choose(hover.value?.port)
        else if (gesture.wasActive) cancel()
    }

    function cancelPaletteDrag(event) {
        if (paletteGesture?.id === event.pointerId) cancel()
    }

    function clickPalette(event) {
        if (event.detail > 0 && handledButton === event.currentTarget) {
            handledButton = null
            return
        }
        if (active.value) cancel()
        else activate()
    }

    function selectConnection(id) {
        clearSelection()
        selectedConnectionId.value = selectedConnectionId.value === id ? null : id
    }

    function removeConnection(id) {
        connections.value = connections.value.filter(connection => connection.id !== id)
        selectedConnectionId.value = null
        status.value = 'Patchkabel entfernt.'
    }

    function removeDeviceConnections(placementId) {
        if (firstEnd.value?.placementId === placementId) cancel()
        const before = connections.value.length
        connections.value = connections.value.filter(({ start, end }) =>
            start.placementId !== placementId && end.placementId !== placementId)
        if (connections.value.length !== before) {
            selectedConnectionId.value = null
            status.value = 'Zugehörige Patchkabel entfernt.'
        }
    }

    return { active, status, firstEnd, hover, visibleConnections, previewLine, selectedConnectionId, refreshPositions,
        availablePort, chooseMarker, startPaletteDrag, finishPaletteDrag, cancelPaletteDrag, pointerMove, clickPalette,
        cancel, selectConnection, removeConnection, removeDeviceConnections, rejectTarget: () => choose(null) }
}
