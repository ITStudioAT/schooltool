import { computed, ref, shallowRef, watch } from 'vue'
import { isNumberedDevice, nextDeviceNumber } from './networkRackConnectivity'

const dragThreshold = 6

export function useNetworkDevicePlacement(resolvePlacement, initialPlacements) {
    const layer = ref(null)
    const placements = ref(initialPlacements)
    const selection = ref(null)
    const drag = ref(null)
    const placementPointer = shallowRef(null)
    let nextPlacementId = 1 + Math.max(0, ...initialPlacements.map(placement => placement.id))
    let handledButton = null
    let planTap = null
    let handledPlanTap = false
    const selectedPlacement = computed(() => placements.value.find(item => item.id === selection.value?.placementId))
    const selectedWorkstation = computed(() => selectedPlacement.value?.device.id === 'workstation'
        ? selectedPlacement.value
        : undefined)
    const canPlaceBySelection = computed(() => selection.value?.placementId === null)
    const placementPreview = computed(() => {
        const active = drag.value
        if (active?.moved) {
            return active.position && { ...active.position, device: active.device, rotation: active.rotation }
        }
        if (!canPlaceBySelection.value || !placementPointer.value) return null

        const { device } = selection.value
        const point = pointInPlan(placementPointer.value, device)
        const position = point && resolvePlacement(device, point, 0)
        return position && { ...position, device, rotation: 0 }
    })
    watch(selection, clearPlacementPreview)

    function previewPlacement(event) {
        if (canPlaceBySelection.value && !drag.value && event.pointerType !== 'touch' && event.target === layer.value) {
            placementPointer.value = { clientX: event.clientX, clientY: event.clientY }
        } else {
            clearPlacementPreview()
        }
    }

    function clearPlacementPreview() {
        placementPointer.value = null
    }

    function remaining(device) {
        return device.limit === undefined
            ? null
            : device.limit - placements.value.filter(item => item.device.id === device.id).length
    }

    function startDrag(event, device, placementId = null, rotation = 0) {
        if (!event.isPrimary || event.button !== 0 || drag.value || (placementId === null && remaining(device) === 0)) {
            return
        }

        handledButton = null
        handledPlanTap = false
        const wasSelected = placementId !== null && selection.value?.placementId === placementId
        if (placementId !== null) {
            selectPlacement(device, placementId)
        }
        const button = event.currentTarget
        button.setPointerCapture(event.pointerId)
        drag.value = {
            pointerId: event.pointerId,
            button,
            device,
            placementId,
            rotation,
            wasSelected,
            startX: event.clientX,
            startY: event.clientY,
            x: event.clientX,
            y: event.clientY,
            moved: false,
        }
    }

    function moveDrag(event) {
        const active = drag.value
        if (active?.pointerId !== event.pointerId) {
            return
        }

        active.x = event.clientX
        active.y = event.clientY
        active.moved ||= Math.hypot(active.x - active.startX, active.y - active.startY) >= dragThreshold
        if (active.moved) {
            const point = pointInPlan(event, active.device)
            active.position = point && resolvePlacement(active.device, point, active.rotation, false, active.placementId)
        }
    }

    function finishDrag(event) {
        const active = drag.value
        if (active?.pointerId !== event.pointerId) {
            return
        }

        moveDrag(event)
        const point = active.moved ? pointInPlan(event, active.device) : null
        drag.value = null
        if (active.moved) {
            handledButton = active.button
            if (point) {
                place(active, point)
            }
        } else if (active.placementId !== null) {
            handledButton = active.button
            if (active.wasSelected) {
                selection.value = null
            }
        } else if (event.pointerType === 'touch') {
            handledButton = active.button
            toggleDeviceSelection(active.device, active.placementId)
        }
    }

    function cancelDrag(event) {
        if (drag.value?.pointerId === event.pointerId) {
            drag.value = null
        }
        if (planTap?.pointerId === event.pointerId) {
            planTap = null
        }
    }

    function selectDevice(event, device, placementId = null) {
        // A touch placement can render its new marker under the browser's follow-up click.
        if (event.detail > 0 && placementId !== null && handledPlanTap) {
            handledPlanTap = false
            return
        }

        if (event.detail > 0 && event.currentTarget === handledButton) {
            handledButton = null
            return
        }

        toggleDeviceSelection(device, placementId)
    }

    function toggleDeviceSelection(device, placementId) {
        const current = selection.value
        selection.value = current?.device.id === device.id && current.placementId === placementId
            ? null
            : { device, placementId }
    }

    function selectPlacement(device, placementId) {
        selection.value = { device, placementId }
    }

    function clickPlan(event) {
        if (handledPlanTap && event.detail > 0) {
            handledPlanTap = false
            return
        }

        activatePlan(event)
    }

    function activatePlan(event) {
        if (canPlaceBySelection.value) {
            const point = pointInPlan(event, selection.value.device)
            if (point) {
                place(selection.value, point)
            }
        } else {
            selection.value = null
        }
    }

    function startPlanTap(event) {
        handledPlanTap = false
        if (event.isPrimary && event.pointerType === 'touch' && selection.value) {
            planTap = { pointerId: event.pointerId, x: event.clientX, y: event.clientY }
        }
    }

    function finishPlanTap(event) {
        if (planTap?.pointerId === event.pointerId) {
            const tapped = Math.hypot(event.clientX - planTap.x, event.clientY - planTap.y) < dragThreshold
            planTap = null
            if (tapped) {
                handledPlanTap = true
                activatePlan(event)
            }
        }
    }

    function placeAtCenter() {
        if (canPlaceBySelection.value) {
            place(selection.value, { x: .5, y: .5 })
        }
    }

    function rotateWorkstation() {
        const workstation = selectedWorkstation.value
        if (workstation) {
            const rotation = (workstation.rotation + 90) % 360
            if (resolvePlacement(workstation.device, workstation, rotation, true)) {
                workstation.rotation = rotation
            }
        }
    }

    function removeSelectedPlacement() {
        const selected = selectedPlacement.value
        if (!selected?.device.removable) {
            return
        }

        placements.value = placements.value.filter(placement => placement.id !== selected.id)
        selection.value = null
        if (drag.value?.placementId === selected.id) {
            drag.value = null
        }
    }

    function pointInPlan({ clientX, clientY }, device) {
        const surface = layer.value
        const target = document.elementFromPoint(clientX, clientY)
        const boundary = device.wallMounted || device.channelMounted ? surface?.parentElement : surface
        const underActions = drag.value?.moved && target?.closest('#network-floor-marker-actions')
            && document.elementsFromPoint(clientX, clientY).some(element => boundary?.contains(element))
        if (!boundary?.contains(target) && !underActions) {
            return null
        }

        const bounds = surface.getBoundingClientRect()
        return { x: (clientX - bounds.left) / bounds.width, y: (clientY - bounds.top) / bounds.height }
    }

    function place({ device, placementId, rotation = 0 }, point) {
        const position = resolvePlacement(device, point, rotation, true, placementId)
        if (!position) {
            return
        }

        if (placementId === null) {
            if (remaining(device) === 0) {
                return
            }
            const placement = { id: nextPlacementId++, device, rotation: 0, ...position }
            if (isNumberedDevice(device)) placement.deviceNumber = nextDeviceNumber(device, placements.value)
            placements.value.push(placement)
            selectPlacement(device, placement.id)
        } else {
            const placement = placements.value.find(item => item.id === placementId)
            if (placement) {
                Object.assign(placement, position)
                selectPlacement(device, placementId)
            }
        }
    }

    return { layer, placements, selection, selectedPlacement, selectedWorkstation, canPlaceBySelection, drag, placementPreview,
        previewPlacement, clearPlacementPreview, remaining, startDrag, moveDrag, finishDrag, cancelDrag, selectDevice, clickPlan,
        startPlanTap, finishPlanTap, placeAtCenter, rotateWorkstation, removeSelectedPlacement }
}
