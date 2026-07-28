import { onBeforeUnmount } from 'vue'

export function useMaterialsV2Polling(refresh, intervalMilliseconds = 5000) {
    let pollingTimer = null

    function stopPolling() {
        window.clearInterval(pollingTimer)
        pollingTimer = null
    }

    function configurePolling(isProcessing) {
        if (!isProcessing) {
            stopPolling()

            return
        }

        if (pollingTimer !== null) {
            return
        }

        pollingTimer = window.setInterval(refresh, intervalMilliseconds)
    }

    onBeforeUnmount(stopPolling)

    return {
        configurePolling,
        stopPolling,
    }
}
