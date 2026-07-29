import { defineStore } from 'pinia'
import {
    checkQueueTest as checkQueueTestRoute,
    status as healthStatusRoute,
    testQueue as testQueueRoute,
} from '@/actions/App/Http/Controllers/Admin/HealthController'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useHealthStore = defineStore('AdminHealthStore', {
    state: () => ({
        scheduler: null,
        worker: null,
        is_healthy: null,
        queue_test: null,
    }),

    actions: {
        async fetchStatus({ notifyOnError = true } = {}) {
            try {
                const response = await axios.get(healthStatusRoute.url())
                this.scheduler = response.data.scheduler
                this.worker = response.data.worker
                this.is_healthy = response.data.is_healthy
                return response.data
            } catch (error) {
                if (notifyOnError) {
                    useNotificationStore().notify({
                        status: error.response?.status,
                        message: error.response?.data?.message || 'Health-Status konnte nicht abgerufen werden.',
                        type: 'error',
                        timeout: 3000,
                    })
                }
                return null
            }
        },

        async testQueue() {
            try {
                const response = await axios.post(testQueueRoute.url())
                this.queue_test = {
                    test_id: response.data.test_id,
                    status: 'dispatched',
                    dispatched_at: response.data.dispatched_at,
                    is_completed: false,
                    duration_seconds: null,
                }
                return response.data
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Queue-Test konnte nicht gestartet werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            }
        },

        async checkQueueTest(testId) {
            try {
                const query = testId ? { test_id: testId } : {}
                const response = await axios.get(checkQueueTestRoute.url({ query }))
                this.queue_test = response.data
                return response.data
            } catch (error) {
                return null
            }
        },
    },
})
