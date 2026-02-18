import { defineStore } from 'pinia'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

export const useMaterialCardStore = defineStore('AdminMaterialCardStore', {
    state: () => ({
        config: null,
        cards: [],
        meta: null,
        selected_card: null,
        filters: {
            search: '',
            status: '',
            subject: '',
            area: '',
            unit: '',
            type: '',
        },
    }),

    actions: {
        normalizeName(value) {
            return String(value ?? '').trim()
        },

        ensureConfigObject() {
            if (!this.config || typeof this.config !== 'object') {
                this.config = {}
            }
        },

        normalizeClassificationRows(input) {
            const list = Array.isArray(input) ? input : []
            const result = []
            const seen = new Set()

            for (const row of list) {
                const subject = this.normalizeName(row?.subject)
                const topic = this.normalizeName(row?.topic)
                let unit = this.normalizeName(row?.unit)
                if (!subject) continue
                if (!topic) {
                    unit = ''
                }
                const key = `${subject.toLocaleLowerCase()}|${topic.toLocaleLowerCase()}|${unit.toLocaleLowerCase()}`
                if (seen.has(key)) continue
                seen.add(key)
                result.push({ subject, topic, unit })
            }

            return result
        },

        extractClassificationsFromCard(card) {
            return this.normalizeClassificationRows(card?.classifications)
        },

        ensureClassificationTreeRows(rows) {
            this.ensureConfigObject()
            const incomingRows = this.normalizeClassificationRows(rows)
            const tree = Array.isArray(this.config.classification_tree)
                ? JSON.parse(JSON.stringify(this.config.classification_tree))
                : []

            const findByName = (items, value) =>
                (items || []).find((item) => String(item?.name || '').toLocaleLowerCase() === value.toLocaleLowerCase())

            for (const row of incomingRows) {
                let subjectNode = findByName(tree, row.subject)
                if (!subjectNode) {
                    subjectNode = {
                        id: null,
                        name: row.subject,
                        topics: [],
                    }
                    tree.push(subjectNode)
                }

                if (!Array.isArray(subjectNode.topics)) {
                    subjectNode.topics = []
                }

                if (!row.topic) {
                    continue
                }

                let topicNode = findByName(subjectNode.topics, row.topic)
                if (!topicNode) {
                    topicNode = {
                        id: null,
                        name: row.topic,
                        units: [],
                    }
                    subjectNode.topics.push(topicNode)
                }

                if (!Array.isArray(topicNode.units)) {
                    topicNode.units = []
                }

                if (!row.unit) {
                    continue
                }

                const unitExists = topicNode.units.some(
                    (unitNode) => String(unitNode?.name || '').toLocaleLowerCase() === row.unit.toLocaleLowerCase()
                )
                if (!unitExists) {
                    topicNode.units.push({
                        id: null,
                        name: row.unit,
                    })
                }
            }

            const sortByName = (a, b) =>
                String(a?.name || '').localeCompare(String(b?.name || ''), undefined, { sensitivity: 'base' })

            tree.sort(sortByName)
            for (const subjectNode of tree) {
                if (Array.isArray(subjectNode.topics)) {
                    subjectNode.topics.sort(sortByName)
                    for (const topicNode of subjectNode.topics) {
                        if (Array.isArray(topicNode.units)) {
                            topicNode.units.sort(sortByName)
                        } else {
                            topicNode.units = []
                        }
                    }
                } else {
                    subjectNode.topics = []
                }
            }

            this.config.classification_tree = tree
        },

        syncClassificationTreeFromCard(card) {
            this.ensureClassificationTreeRows(this.extractClassificationsFromCard(card))
        },

        syncClassificationTreeFromCards(cards) {
            const list = Array.isArray(cards) ? cards : []
            const rows = []
            for (const card of list) {
                rows.push(...this.extractClassificationsFromCard(card))
            }
            this.ensureClassificationTreeRows(rows)
        },

        async loadConfig() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/config')
                this.config = response.data
                this.ensureClassificationTreeRows([])
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarten-Konfiguration.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async index(page = 1) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const params = { page }
                for (const [key, value] of Object.entries(this.filters || {})) {
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        params[key] = value
                    }
                }

                const response = await axios.get('/api/admin/materials/cards', { params })
                this.cards = response.data.data || []
                this.meta = response.data.meta || null
                this.syncClassificationTreeFromCards(this.cards)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarten.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async indexAll() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const baseParams = {}
                for (const [key, value] of Object.entries(this.filters || {})) {
                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                        baseParams[key] = value
                    }
                }

                const allCards = []
                let page = 1
                let hasMorePages = true
                let lastMeta = null

                while (hasMorePages) {
                    const response = await axios.get('/api/admin/materials/cards', {
                        params: {
                            ...baseParams,
                            page,
                        },
                    })

                    const pageCards = response.data?.data || []
                    const pageMeta = response.data?.meta || null

                    allCards.push(...pageCards)
                    lastMeta = pageMeta

                    const currentPage = Number(pageMeta?.current_page || page)
                    const lastPage = Number(pageMeta?.last_page || currentPage)

                    if (!pageMeta || !Number.isFinite(lastPage) || currentPage >= lastPage) {
                        hasMorePages = false
                    } else {
                        page = currentPage + 1
                    }
                }

                this.cards = allCards
                this.meta = lastMeta
                this.syncClassificationTreeFromCards(this.cards)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden aller Materialkarten.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async show(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/cards/' + id)
                this.selected_card = response.data
                this.syncClassificationTreeFromCard(this.selected_card)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async store(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/cards', { data })
                this.selected_card = response.data
                this.syncClassificationTreeFromCard(this.selected_card)
                notification.notify({
                    message: 'Materialkarte gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async quickStore(data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/cards/quick_store', { data })
                this.selected_card = response.data
                this.syncClassificationTreeFromCard(this.selected_card)
                notification.notify({
                    message: 'Materialkarte schnell gemerkt.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Schnell-Merken.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async update(id, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/cards/' + id, { data })
                this.selected_card = response.data
                this.syncClassificationTreeFromCard(this.selected_card)
                notification.notify({
                    message: 'Materialkarte aktualisiert.',
                    type: 'success',
                    timeout: 2000,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async destroy(id) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/cards/' + id)
                this.selected_card = null
                notification.notify({
                    message: 'Materialkarte gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen der Materialkarte.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async addLinkAttachment(cardId, data) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/cards/' + cardId + '/attachments/link', { data })
                notification.notify({
                    message: 'Link-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Hinzufügen des Link-Anhangs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async addFileAttachment(cardId, file, name = '') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const formData = new FormData()
                formData.append('file', file)
                if (name) {
                    formData.append('name', name)
                }

                await axios.post('/api/admin/materials/cards/' + cardId + '/attachments/file', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                })

                notification.notify({
                    message: 'Datei-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Datei-Upload.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteAttachment(attachmentId, cardId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/attachments/' + attachmentId)
                notification.notify({
                    message: 'Anhang gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Anhangs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async renameAttachment(attachmentId, cardId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(attachmentId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Anhang.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Dateititel eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.patch('/api/admin/materials/attachments/' + id, {
                    data: {
                        name: normalizedName,
                    },
                })

                notification.notify({
                    message: 'Anhang umbenannt.',
                    type: 'success',
                    timeout: 2000,
                })

                if (cardId) {
                    await this.show(cardId)
                }

                return response?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Umbenennen des Anhangs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async createType(name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Typ-Namen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/types', {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()

                notification.notify({
                    message: 'Typ hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des Typs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateType(typeId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedName = String(name ?? '').trim().slice(0, 255)
            const id = Number(typeId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Typ.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Typ-Namen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/types/' + id, {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Typ aktualisiert.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren des Typs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteType(typeId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(typeId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Typ.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/types/' + id)
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Typ gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Typs.',
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
