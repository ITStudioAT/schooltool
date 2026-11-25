const tapThreshold = 6

export function useNetworkTapAction() {
    let tap = null
    let handledTarget = null

    function startActionTap(event) {
        handledTarget = null
        if (event.isPrimary && event.pointerType === 'touch') {
            tap = { pointerId: event.pointerId, x: event.clientX, y: event.clientY }
        }
    }

    function finishActionTap(event, action) {
        if (tap?.pointerId !== event.pointerId) {
            return
        }

        const tapped = Math.hypot(event.clientX - tap.x, event.clientY - tap.y) < tapThreshold
        tap = null
        if (tapped) {
            handledTarget = event.currentTarget
            action()
        }
    }

    function cancelActionTap(event) {
        if (tap?.pointerId === event.pointerId) {
            tap = null
        }
    }

    function clickAction(event, action) {
        if (event.detail > 0 && event.currentTarget === handledTarget) {
            handledTarget = null
            return
        }
        action()
    }

    return { startActionTap, finishActionTap, cancelActionTap, clickAction }
}
