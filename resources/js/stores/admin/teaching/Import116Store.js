import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useImport116Store = defineStore('AdminImport116Store', {
    state: () => {
        return {
            import116_students: [],
            classes: [],
        }
    },

    actions: {
        async loadClassStudents(schoolclasses) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get(`/api/admin/teaching/import116/load_class_students`, {
                    params: { schoolclasses },
                })
                this.import116_students = response.data.data
                this.classes = response.data.classes
                return true
            } catch (error) {
                notification.notify({
                    status: error?.response?.status,
                    message: error?.response?.data?.message || 'Fehler passiert.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
