import { defineStore } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

function normalizeYear(year) {
    const value = Number(year)

    if (! Number.isInteger(value) || value < 2000 || value > 2100) {
        return new Date().getFullYear()
    }

    return value
}

function sortByDate(items = []) {
    return [...items].sort((left, right) => {
        return String(left?.free_date || '').localeCompare(String(right?.free_date || ''))
    })
}

function removeDate(items, isoDate) {
    return items.filter((entry) => entry !== isoDate)
}

function addUniqueDate(items, isoDate) {
    return items.includes(isoDate) ? items : [...items, isoDate]
}

function expandDateRange(startDate, endDate) {
    const dates = []
    const currentDate = new Date(`${startDate}T00:00:00`)
    const finalDate = new Date(`${endDate}T00:00:00`)

    while (currentDate <= finalDate) {
        const year = currentDate.getFullYear()
        const month = String(currentDate.getMonth() + 1).padStart(2, '0')
        const day = String(currentDate.getDate()).padStart(2, '0')
        dates.push(`${year}-${month}-${day}`)
        currentDate.setDate(currentDate.getDate() + 1)
    }

    return dates
}

export const useFreeDayStore = defineStore('AdminRestaurantFreeDayStore', {
    state: () => ({
        year: new Date().getFullYear(),
        freeDays: [],
        isLoaded: false,
        pendingSetDates: [],
        pendingUnsetDates: [],
    }),

    getters: {
        freeDayCount: (state) => state.freeDays.length,
        pendingChangeCount: (state) => state.pendingSetDates.length + state.pendingUnsetDates.length,
        freeDaysByDate: (state) => {
            return state.freeDays.reduce((carry, freeDay) => {
                carry[freeDay.free_date] = freeDay

                return carry
            }, {})
        },
        displayFreeDayCount(state) {
            const yearPrefix = `${state.year}-`
            const persistedDates = new Set(state.freeDays.map((freeDay) => freeDay.free_date))
            const addedCount = state.pendingSetDates.filter((date) => date.startsWith(yearPrefix) && !persistedDates.has(date)).length
            const removedCount = state.pendingUnsetDates.filter((date) => date.startsWith(yearPrefix) && persistedDates.has(date)).length

            return state.freeDays.length + addedCount - removedCount
        },
    },

    actions: {
        async loadYear(year = this.year, showLoader = true) {
            const normalizedYear = normalizeYear(year)
            const adminStore = useAdminStore()
            const notification = useNotificationStore()

            if (showLoader) {
                adminStore.is_loading++
            }

            try {
                const response = await axios.get('/api/admin/restaurant/free-days', {
                    params: {
                        year: normalizedYear,
                    },
                })

                this.year = Number(response?.data?.meta?.year || normalizedYear)
                this.freeDays = sortByDate(response?.data?.data || [])
                this.isLoaded = true

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Freie Tage konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })

                return false
            } finally {
                if (showLoader) {
                    adminStore.is_loading--
                }
            }
        },

        isDateFree(isoDate) {
            if (this.pendingUnsetDates.includes(isoDate)) {
                return false
            }

            if (this.pendingSetDates.includes(isoDate)) {
                return true
            }

            return this.freeDaysByDate[isoDate] !== undefined
        },

        resetPendingChanges() {
            this.pendingSetDates = []
            this.pendingUnsetDates = []
        },

        stageDateAsSet(isoDate) {
            const isPersistedFree = this.freeDaysByDate[isoDate] !== undefined

            this.pendingUnsetDates = removeDate(this.pendingUnsetDates, isoDate)

            if (! isPersistedFree) {
                this.pendingSetDates = addUniqueDate(this.pendingSetDates, isoDate)
            }
        },

        toggleDay(isoDate) {
            const isPersistedFree = this.freeDaysByDate[isoDate] !== undefined
            const isEffectivelyFree = this.isDateFree(isoDate)

            if (isEffectivelyFree) {
                this.pendingSetDates = removeDate(this.pendingSetDates, isoDate)

                if (isPersistedFree) {
                    this.pendingUnsetDates = addUniqueDate(this.pendingUnsetDates, isoDate)
                }

                return
            }

            this.pendingUnsetDates = removeDate(this.pendingUnsetDates, isoDate)

            if (! isPersistedFree) {
                this.pendingSetDates = addUniqueDate(this.pendingSetDates, isoDate)
            }
        },

        stageRange(startDate, endDate) {
            if (! startDate || ! endDate || endDate < startDate) {
                return false
            }

            expandDateRange(startDate, endDate).forEach((isoDate) => {
                this.stageDateAsSet(isoDate)
            })

            return true
        },

        async saveChanges() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()

            if (this.pendingChangeCount === 0) {
                notification.notify({
                    message: 'Es gibt keine ungespeicherten Aenderungen.',
                    type: 'warning',
                    timeout: 2500,
                })

                return false
            }

            adminStore.is_loading++

            try {
                const payload = {}

                if (this.pendingSetDates.length) {
                    payload.set_dates = this.pendingSetDates
                }

                if (this.pendingUnsetDates.length) {
                    payload.unset_dates = this.pendingUnsetDates
                }

                const response = await axios.post('/api/admin/restaurant/free-days', payload)
                const reloaded = await this.loadYear(this.year, false)

                if (! reloaded) {
                    return false
                }

                this.resetPendingChanges()

                notification.notify({
                    message: response?.data?.message || 'Aenderungen wurden gespeichert.',
                    type: 'success',
                    timeout: 2200,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Freie Tage konnten nicht gespeichert werden.',
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
