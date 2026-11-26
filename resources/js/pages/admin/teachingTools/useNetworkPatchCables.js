import { computed, ref, shallowRef, watch } from 'vue'
import { firstWallIntersection } from './networkFloorWalls'
import { firstFreeRackPort, patchDeviceLabel } from './networkRackConnectivity'

export function canConnectPatchDevices(first, second) {
    return Boolean(first?.ports && second?.ports) && Boolean(first.channelMounted) !== Boolean(second.channelMounted)
}

export function useNetworkPatchCables({ layer, wallsById, placements, markerElements, move, clearSelection, initialConnections }) {
    const active = ref(false)
    const firstEnd = shallowRef(null)
    const hover = shallowRef(null)
    const status = ref('')
    const connections = ref(initialConnections)
    const selectedConnectionId = ref(null)
    const positions = shallowRef(new Map())
    let nextId = 1 + Math.max(0, ...initialConnections.map(connection => connection.id))
    let paletteGesture = null
    let handledButton = null

    watch(wallsById, refreshPositions, { flush: 'post' })
    watch(() => placements.value.map(({ id, x, y, rotation, wallId, channelId, channelPosition }) =>
        [id, [x, y, rotation, wallId, channelId, channelPosition]]), (current, previous) => {
        refreshPositions()
        const previousPositions = new Map(previous)
        const movedPlacements = new Set(current.filter(([id, position]) =>
            previousPositions.get(id)?.some((value, index) => value !== position[index])).map(([id]) => id))
        const affectedPlacements = new Set(placements.value.filter(({ id, channelId }) =>
            movedPlacements.has(id) || movedPlacements.has(channelId)).map(({ id }) => id))
        const clearConnections = connections.value.filter(({ start, end }) =>
            (!affectedPlacements.has(start.placementId) && !affectedPlacements.has(end.placementId)) || !isBlocked(start, end))
        if (clearConnections.length === connections.value.length) return

        connections.value = clearConnections
        selectedConnectionId.value = null
        status.value = 'Patchkabel, die durch eine Wand führen würden, wurden getrennt.'
    }, { flush: 'post' })

    const visibleConnections = computed(() => connections.value.flatMap(connection => {
        const start = endPoint(connection.start, true)
        const end = endPoint(connection.end, true)
        if (!start || !end) return []
        const movingId = move.value?.placementId
        if (move.value?.moved && (movingId === connection.start.placementId || movingId === connection.end.placementId)
            && firstWallIntersection(wallsById.value.values(), start, end) !== null) return []

        const label = `Patchkabel zwischen ${endLabel(connection.start)} und ${endLabel(connection.end)}`
        return [{ id: connection.id, label, start, end }]
    }))

    const previewLine = computed(() => {
        const start = firstEnd.value && endPoint(firstEnd.value)
        const target = hover.value
        const end = target?.port ? endPoint(target.port) : target?.point
        if (!start || !end) return null

        const wallHit = firstWallIntersection(wallsById.value.values(), start, end)
        return wallHit === null ? { start, end, invalid: !target.port } : {
            start,
            end: { x: start.x + (end.x - start.x) * wallHit, y: start.y + (end.y - start.y) * wallHit },
            invalid: true,
        }
    })

    function refreshPositions() {
        const surface = layer.value?.getBoundingClientRect()
        if (!surface?.width) return

        const measured = new Map()
        for (const placement of placements.value) {
            const marker = markerElements.get(placement.id)
            if (!marker || (!placement.device.ports && !placement.device.bundleEnd && placement.device.id !== 'server-rack')) continue
            const rack = marker.querySelector('.network-rack-icon')
            if (rack) {
                const rect = rack.getBoundingClientRect()
                const markerRect = marker.getBoundingClientRect()
                const centerX = rect.left + rect.width / 2
                measured.set(placement.id, {
                    points: [normalizedPoint(surface, centerX, rect.top)],
                    rackHalfWidth: rect.width / (2 * surface.width),
                    dragOffset: { x: centerX - (markerRect.left + markerRect.width / 2),
                        y: rect.top - (markerRect.top + markerRect.height / 2) },
                })
                continue
            }
            const sockets = [...marker.querySelectorAll('.network-outlet-icon > span')]
            const equipment = marker.querySelector('.network-floor__workstation-graphic, .network-standing-table-icon__laptop')
                ?? marker.querySelector('.network-floor__device-drawing')
            const points = (sockets.length ? sockets : [equipment ?? marker]).map(node => {
                const rect = node.getBoundingClientRect()
                return normalizedPoint(surface, rect.left + rect.width / 2, rect.top + rect.height / 2)
            })
            measured.set(placement.id, { points })
        }
        positions.value = measured
    }

    function endPoint({ placementId, index }, followDrag = false) {
        const position = positions.value.get(placementId)
        const points = position?.points
        let point = points?.[index] ?? points?.[0]
        const gesture = move.value
        if (followDrag && gesture?.placementId === placementId && gesture.moved && gesture.position) {
            const offset = position?.dragOffset
            point = normalizedPoint(layer.value.getBoundingClientRect(),
                gesture.x + (offset?.x ?? 0), gesture.y + (offset?.y ?? 0))
        }
        return point && position?.rackHalfWidth != null ? { ...point, rackHalfWidth: position.rackHalfWidth } : point
    }

    function endLabel({ placementId, index }) {
        const placement = placements.value.find(item => item.id === placementId)
        if (!placement) return ''

        const label = patchDeviceLabel(placement)
        return placement.device.ports === 1 ? label : `${label}, Anschluss ${index + 1}`
    }

    function isBlocked(startPort, endPort) {
        const start = startPort && endPoint(startPort)
        const end = endPort && endPoint(endPort)
        return start && end && firstWallIntersection(wallsById.value.values(), start, end) !== null
    }

    function availablePort(placement) {
        const port = candidatePort(placement)
        return isBlocked(firstEnd.value, port) ? null : port
    }

    function candidatePort(placement, point) {
        if (!placement?.device.ports) return null
        if (firstEnd.value) {
            const start = placements.value.find(item => item.id === firstEnd.value.placementId)?.device
            if (!canConnectPatchDevices(start, placement.device)) return null
        }
        const used = new Set(connections.value.flatMap(({ start, end }) => [start, end])
            .filter(port => port.placementId === placement.id).map(port => port.index))
        const available = Array.from({ length: placement.device.ports }, (_, index) => index).filter(index => !used.has(index))
        if (!available.length) return null

        const points = positions.value.get(placement.id)?.points
        const index = available.length === 2 && point && points?.length === 2 && point.x > (points[0].x + points[1].x) / 2
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
        return { point, port: candidatePort(placement, point) }
    }

    function activate() {
        clearSelection()
        active.value = true
        selectedConnectionId.value = null
        refreshPositions()
        guide()
    }

    function guide() {
        const device = firstEnd.value && placements.value.find(item => item.id === firstEnd.value.placementId)?.device
        if (!device) {
            status.value = 'Wähle eine Netzwerkdose, einen Arbeitsplatz, einen Stehtisch oder einen Access Point.'
        } else if (device.channelMounted) {
            status.value = 'Wähle einen Arbeitsplatz, einen Stehtisch oder einen Access Point mit freiem Anschluss.'
        } else {
            status.value = 'Wähle eine Netzwerkdose mit freiem Anschluss.'
        }
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
        if (isBlocked(firstEnd.value, port)) {
            status.value = 'Ein Patchkabel darf nicht durch eine Wand führen. Wähle einen erreichbaren Anschluss.'
            return
        }
        const rackPort = firstFreeRackPort(new Set(connections.value.map(connection => connection.rackPort)))
        connections.value.push({ id: nextId++, start: firstEnd.value, end: port, rackPort })
        cancel()
        status.value = 'Patchkabel verbunden.'
    }

    function chooseMarker(placement, event) {
        const point = event.detail === 0 && event.type === 'click' ? null : pointerTarget(event)?.point
        const port = candidatePort(placement, point)
        if (port) choose(port)
        else guide()
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

    return { active, status, firstEnd, hover, connections, visibleConnections, previewLine, selectedConnectionId, refreshPositions, endPoint,
        availablePort, chooseMarker, startPaletteDrag, finishPaletteDrag, cancelPaletteDrag, pointerMove, clickPalette,
        cancel, selectConnection, removeConnection, removeDeviceConnections, rejectTarget: () => choose(null) }
}
