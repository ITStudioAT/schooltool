import { defineStore } from 'pinia'
import axios from 'axios'
import { materialsV2Api } from '@/domains/materialsV2/api'

export const useMaterialsV2Store = defineStore('AdminMaterialsV2Store', {
    state: () => ({
        items: [],
        categoryDetails: [],
        clusterDetails: [],
        maxUploadSizeKb: 20480,
        loading: false,
        loadError: '',
        meta: {
            total: 0,
            current_page: 1,
            last_page: 1,
        },
        itemsRequestGeneration: 0,
        configRequestGeneration: 0,
    }),

    actions: {
        resetPage() {
            this.itemsRequestGeneration += 1
            this.configRequestGeneration += 1
            this.items = []
            this.categoryDetails = []
            this.clusterDetails = []
            this.maxUploadSizeKb = 20480
            this.loading = false
            this.loadError = ''
            this.meta = {
                total: 0,
                current_page: 1,
                last_page: 1,
            }
        },

        async loadItems({
            search = '',
            category,
            clusterId,
            page = 1,
            calendarRange = null,
            reminderCategory,
            errorMessage = 'Die Materialien konnten nicht geladen werden.',
        } = {}) {
            const requestGeneration = ++this.itemsRequestGeneration
            this.loading = true
            this.loadError = ''

            try {
                const isCalendarView = calendarRange !== null
                const requestPage = isCalendarView ? 1 : page
                const perPage = isCalendarView ? 48 : 18
                const rangeParams = isCalendarView
                    ? {
                        reminder_from: calendarRange.start,
                        reminder_to: calendarRange.end,
                    }
                    : {}
                const response = await axios.get(materialsV2Api.items(), {
                    params: {
                        search: search.trim() || undefined,
                        category,
                        ...(clusterId ? { cluster_id: clusterId } : {}),
                        ...rangeParams,
                        page: requestPage,
                        per_page: perPage,
                    },
                })
                const responseMeta = response.data?.meta || {}
                const loadedItems = [...(response.data?.data || [])]

                if (isCalendarView) {
                    for (let calendarPage = 2; calendarPage <= Number(responseMeta.last_page || 1); calendarPage += 1) {
                        if (requestGeneration !== this.itemsRequestGeneration) {
                            return false
                        }

                        const additionalResponse = await axios.get(materialsV2Api.items(), {
                            params: {
                                search: search.trim() || undefined,
                                category: reminderCategory,
                                ...(clusterId ? { cluster_id: clusterId } : {}),
                                ...rangeParams,
                                page: calendarPage,
                                per_page: perPage,
                            },
                        })

                        loadedItems.push(...(additionalResponse.data?.data || []))
                    }
                }

                if (requestGeneration !== this.itemsRequestGeneration) {
                    return false
                }

                this.items = loadedItems
                this.meta = isCalendarView
                    ? {
                        total: Number(responseMeta.total || loadedItems.length),
                        current_page: 1,
                        last_page: 1,
                    }
                    : response.data?.meta || {
                        total: loadedItems.length,
                        current_page: 1,
                        last_page: 1,
                    }

                return true
            } catch (error) {
                if (requestGeneration !== this.itemsRequestGeneration) {
                    return false
                }

                this.items = []
                this.loadError = error.response?.data?.message || errorMessage
                this.meta = {
                    total: 0,
                    current_page: 1,
                    last_page: 1,
                }

                return false
            } finally {
                if (requestGeneration === this.itemsRequestGeneration) {
                    this.loading = false
                }
            }
        },

        async loadConfig() {
            const requestGeneration = ++this.configRequestGeneration

            try {
                const response = await axios.get(materialsV2Api.config())
                if (requestGeneration !== this.configRequestGeneration) {
                    return false
                }

                const categories = Array.isArray(response.data?.categories) ? response.data.categories : []

                this.categoryDetails = Array.isArray(response.data?.category_details)
                    ? response.data.category_details
                    : categories.map((name) => ({ name, items_count: null }))
                this.clusterDetails = Array.isArray(response.data?.cluster_details)
                    ? response.data.cluster_details
                    : []
                this.maxUploadSizeKb = Number(response.data?.max_file_upload_size_kb) > 0
                    ? Number(response.data.max_file_upload_size_kb)
                    : 20480

                return true
            } catch {
                if (requestGeneration !== this.configRequestGeneration) {
                    return false
                }

                this.categoryDetails = []
                this.clusterDetails = []
                this.maxUploadSizeKb = 20480

                return false
            }
        },

        clearItems() {
            this.itemsRequestGeneration += 1
            this.items = []
            this.loading = false
            this.loadError = ''
            this.meta = {
                total: 0,
                current_page: 1,
                last_page: 1,
            }
        },
    },
})
