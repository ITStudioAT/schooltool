<script lang="ts">
import { defineComponent } from 'vue'
import { useEcho } from '@laravel/echo-vue'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

type ImportCompletionPayload = {
    status: number
    message: string
    data: Record<string, unknown>
}

export default defineComponent({
    props: {
        userId: {
            type: Number,
            required: true,
        },
    },

    setup(props) {
        const notification = useNotificationStore()
        const channelName = `user.${props.userId}`

        function notifyImportCompletion(payload: ImportCompletionPayload) {
            notification.notify({
                message: payload.message,
                type: payload.status === 200 ? 'success' : 'error',
                persistent: true,
            })
        }

        useEcho<ImportCompletionPayload>(
            channelName,
            'TeachersListImportFinishedEvent',
            (payload) => {
                notifyImportCompletion(payload)
                window.dispatchEvent(new CustomEvent('teachers-list-import-finished', { detail: payload }))
            },
        )

        useEcho<ImportCompletionPayload>(
            channelName,
            'Import116FinishedEvent',
            (payload) => {
                notifyImportCompletion(payload)
                window.dispatchEvent(new CustomEvent('import116-finished', { detail: payload }))
            },
        )

        return () => null
    },
})
</script>
