import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

export const useHealthStore = defineStore('AdminHealthStore', {
    state: () => ({
        scheduler: null,
        worker: null,
        is_healthy: null,
        queue_test: null,
    }),

    actions: {
        async fetchStatus() {
            try {
                const response = await axios.get('/api/admin/health/status')
                this.scheduler = response.data.scheduler
                this.worker = response.data.worker
                this.is_healthy = response.data.is_healthy
                return response.data
            } catch (error) {
                useNotificationStore().notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Health-Status konnte nicht abgerufen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            }
        },

        async testQueue() {
            try {
                const response = await axios.get('/api/admin/health/test-queue')
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
                const params = testId ? { test_id: testId } : {}
                const response = await axios.get('/api/admin/health/test-queue/check', { params })
                this.queue_test = response.data
                return response.data
            } catch (error) {
                return null
            }
        },
    },
})
