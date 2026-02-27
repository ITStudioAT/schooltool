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
            topic: '',
            area: '',
            unit: '',
            type: '',
        },
    }),

    actions: {
        normalizeName(value) {
            return String(value ?? '').trim()
        },
        normalizeTypeColor(value) {
            const text = String(value ?? '').trim()
            if (!text) return null
            if (!/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(text)) return null
            if (text.length === 4) {
                return `#${text[1]}${text[1]}${text[2]}${text[2]}${text[3]}${text[3]}`.toLowerCase()
            }
            return text.toLowerCase()
        },
        normalizeStatusColor(value) {
            return this.normalizeTypeColor(value)
        },
        normalizeMoveDirection(value) {
            const direction = String(value ?? '').trim().toLowerCase()
            if (direction === 'up' || direction === 'down') return direction
            return ''
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
                        can_delete: false,
                        topics: [],
                    }
                    tree.push(subjectNode)
                } else if (subjectNode && typeof subjectNode === 'object' && !Object.prototype.hasOwnProperty.call(subjectNode, 'can_delete')) {
                    subjectNode.can_delete = false
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
                        can_delete: false,
                        units: [],
                    }
                    subjectNode.topics.push(topicNode)
                } else if (topicNode && typeof topicNode === 'object' && !Object.prototype.hasOwnProperty.call(topicNode, 'can_delete')) {
                    topicNode.can_delete = false
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
                        can_delete: false,
                    })
                } else {
                    const existingUnit = topicNode.units.find(
                        (unitNode) => String(unitNode?.name || '').toLocaleLowerCase() === row.unit.toLocaleLowerCase()
                    )
                    if (existingUnit && typeof existingUnit === 'object' && !Object.prototype.hasOwnProperty.call(existingUnit, 'can_delete')) {
                        existingUnit.can_delete = false
                    }
                }
            }

            for (const subjectNode of tree) {
                if (Array.isArray(subjectNode.topics)) {
                    for (const topicNode of subjectNode.topics) {
                        if (!Array.isArray(topicNode.units)) {
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

        buildFilterParams(sourceFilters) {
            const params = {}
            for (const [key, value] of Object.entries(sourceFilters || {})) {
                if (value !== null && value !== undefined && String(value).trim() !== '') {
                    params[key] = value
                }
            }
            return params
        },

        async fetchAllCardsPages(baseParams = {}) {
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

            return {
                cards: allCards,
                meta: lastMeta,
            }
        },

        async indexAll() {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const baseParams = this.buildFilterParams(this.filters)
                const result = await this.fetchAllCardsPages(baseParams)
                this.cards = result.cards
                this.meta = result.meta
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

        async listAllCardsSnapshot(filters = {}) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++

            try {
                const baseParams = this.buildFilterParams(filters)
                const result = await this.fetchAllCardsPages(baseParams)
                return result.cards
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Laden der Materialkarten.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
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
                await this.loadConfig()
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
                await this.loadConfig()
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
                await this.loadConfig()
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
                await this.loadConfig()
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

        async restoreLastDeleted() {
            return this.restoreDeletedById(null)
        },

        async restoreDeletedById(cardId = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const normalizedId = Number(cardId)
                const url = Number.isFinite(normalizedId) && normalizedId > 0
                    ? '/api/admin/materials/cards/restore-deleted/' + normalizedId
                    : '/api/admin/materials/cards/restore-last-deleted'
                const response = await axios.post(url)
                this.selected_card = response.data
                this.syncClassificationTreeFromCard(this.selected_card)
                await this.loadConfig()
                notification.notify({
                    message: 'Gelöschtes Material wiederhergestellt.',
                    type: 'success',
                    timeout: 2500,
                })
                return response.data
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Wiederherstellung des zuletzt gelöschten Materials fehlgeschlagen.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async getDeletedRestoreList() {
            const adminStore = useAdminStore()
            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/cards/deleted-restore-list')
                return {
                    items: Array.isArray(response.data?.data) ? response.data.data : [],
                    limit: Math.max(1, Number(response.data?.meta?.limit || 1) || 1),
                }
            } catch (error) {
                const notification = useNotificationStore()
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Hinweise zu gelöschten Materialien konnten nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return {
                    items: [],
                    limit: 1,
                }
            } finally {
                adminStore.is_loading--
            }
        },

        async getLastDeletedRestoreInfo() {
            const result = await this.getDeletedRestoreList()
            return result.items[0] || null
        },

        async purgeDeletedById(cardId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedId = Number(cardId)

            if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
                notification.notify({
                    message: 'Ungültiges gelöschtes Material.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/cards/deleted/' + normalizedId)
                await this.loadConfig()
                notification.notify({
                    message: 'Gelöschtes Material endgültig gelöscht.',
                    type: 'success',
                    timeout: 2500,
                })
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Endgültiges Löschen des Materials fehlgeschlagen.',
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

        async addImageUrlAttachment(cardId, url, name = '') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedUrl = String(url ?? '').trim().slice(0, 2048)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!normalizedUrl) {
                notification.notify({
                    message: 'Ungültige Bild-URL.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/cards/' + cardId + '/attachments/image-url', {
                    data: {
                        url: normalizedUrl,
                        name: normalizedName || null,
                    },
                })

                notification.notify({
                    message: 'Bild-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                await this.show(cardId)
                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Importieren des Bildes.',
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

        async addTempFileAttachment(cardId, uploadId, name = '') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(cardId)
            const normalizedUploadId = String(uploadId ?? '').trim()
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0 || normalizedUploadId === '') {
                notification.notify({
                    message: 'Ungültige Upload-Daten.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/cards/' + id + '/attachments/file-temp', {
                    data: {
                        upload_id: normalizedUploadId,
                        name: normalizedName || null,
                    },
                })

                notification.notify({
                    message: 'Datei-Anhang hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })
                await this.show(id)
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

        async deleteTempUpload(uploadId, notify = false) {
            const notification = useNotificationStore()
            const normalizedUploadId = String(uploadId ?? '').trim()
            if (!normalizedUploadId) return false

            try {
                await axios.delete('/api/admin/materials/uploads/chunk/' + encodeURIComponent(normalizedUploadId))
                if (notify) {
                    notification.notify({
                        message: 'Temporärer Upload gelöscht.',
                        type: 'success',
                        timeout: 2000,
                    })
                }
                return true
            } catch (error) {
                if (notify) {
                    notification.notify({
                        status: error.response?.status,
                        message: error.response?.data?.message || 'Temporärer Upload konnte nicht gelöscht werden.',
                        type: 'error',
                        timeout: 3000,
                    })
                }
                return false
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

        async fetchTextAttachmentContent(attachmentId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(attachmentId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Anhang.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.get('/api/admin/materials/attachments/' + id + '/text-content')
                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Text-Anhang konnte nicht geladen werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateTextAttachmentContent(attachmentId, cardId, contentHtml, name = '') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(attachmentId)
            const normalizedContent = String(contentHtml ?? '').trim()
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Anhang.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedContent) {
                notification.notify({
                    message: 'Bitte Textinhalt eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.patch('/api/admin/materials/attachments/' + id + '/text-content', {
                    data: {
                        content_html: normalizedContent,
                        name: normalizedName || null,
                    },
                })

                notification.notify({
                    message: 'Text-Anhang aktualisiert.',
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
                    message: error.response?.data?.message || 'Text-Anhang konnte nicht gespeichert werden.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async importDefaultTypes(types) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const incoming = Array.isArray(types) ? types : [types]
            const normalizedTypes = []
            const seen = new Set()

            for (const rawType of incoming) {
                const normalizedName = String(
                    typeof rawType === 'object' && rawType !== null ? rawType.name : rawType
                ).trim().slice(0, 255)
                const normalizedIcon = String(
                    typeof rawType === 'object' && rawType !== null ? (rawType.icon ?? '') : ''
                ).trim().slice(0, 100)
                const normalizedColor = this.normalizeTypeColor(
                    typeof rawType === 'object' && rawType !== null ? (rawType.color ?? '') : ''
                )
                if (!normalizedName) continue
                const key = normalizedName.toLocaleLowerCase()
                if (seen.has(key)) continue
                seen.add(key)
                normalizedTypes.push({
                    name: normalizedName,
                    icon: normalizedIcon || null,
                    color: normalizedColor,
                })
            }

            if (!normalizedTypes.length) {
                notification.notify({
                    message: 'Keine Standardtypen ausgewählt.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                for (const type of normalizedTypes) {
                    await axios.post('/api/admin/materials/types', {
                        data: {
                            name: type.name,
                            icon: type.icon,
                            color: type.color,
                        },
                    })
                }

                await this.loadConfig()

                notification.notify({
                    message: 'Standardtypen übernommen.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Übernehmen der Standardtypen.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async createType(name, icon = null, color = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedName = String(name ?? '').trim().slice(0, 255)
            const normalizedIcon = String(icon ?? '').trim().slice(0, 100)
            const normalizedColor = this.normalizeTypeColor(color)

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
                        icon: normalizedIcon || null,
                        color: normalizedColor,
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

        async updateType(typeId, name, icon = null, color = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedName = String(name ?? '').trim().slice(0, 255)
            const normalizedIcon = String(icon ?? '').trim().slice(0, 100)
            const normalizedColor = this.normalizeTypeColor(color)
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
                        icon: normalizedIcon || null,
                        color: normalizedColor,
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

        async createStatus(label, color = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedLabel = String(label ?? '').trim().slice(0, 255)
            const normalizedColor = this.normalizeStatusColor(color)

            if (!normalizedLabel) {
                notification.notify({
                    message: 'Bitte eine Statusbezeichnung eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/statuses', {
                    data: {
                        label: normalizedLabel,
                        color: normalizedColor,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Status hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des Status.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateStatus(statusId, label, color = null) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(statusId)
            const normalizedLabel = String(label ?? '').trim().slice(0, 255)
            const normalizedColor = this.normalizeStatusColor(color)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Status.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedLabel) {
                notification.notify({
                    message: 'Bitte eine Statusbezeichnung eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/statuses/' + id, {
                    data: {
                        label: normalizedLabel,
                        color: normalizedColor,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Status aktualisiert.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Aktualisieren des Status.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteStatus(statusId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(statusId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Status.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/statuses/' + id)
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Status gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Status.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async createSubject(name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Fachnamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/subjects', {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Fach hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateSubject(subjectId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(subjectId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Fach.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Fachnamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/subjects/' + id, {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Fach umbenannt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Umbenennen des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async createTopic(subjectId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(subjectId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Fach.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Themennamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/topics', {
                    data: {
                        subject_id: id,
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateTopic(topicId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(topicId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Thema.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Themennamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/topics/' + id, {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema umbenannt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Umbenennen des Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async createUnit(topicId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(topicId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Thema.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Bereichsnamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post('/api/admin/materials/units', {
                    data: {
                        topic_id: id,
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Bereich hinzugefügt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des Bereichs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateUnit(unitId, name) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(unitId)
            const normalizedName = String(name ?? '').trim().slice(0, 255)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiger Bereich.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            if (!normalizedName) {
                notification.notify({
                    message: 'Bitte einen Bereichsnamen eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/units/' + id, {
                    data: {
                        name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Bereich umbenannt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Umbenennen des Bereichs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteSubject(subjectId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(subjectId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Fach.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/subjects/' + id)
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Fach gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteTopic(topicId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(topicId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Thema.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/topics/' + id)
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen des Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async deleteUnit(unitId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(unitId)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültige Einheit.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.delete('/api/admin/materials/units/' + id)
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Einheit gelöscht.',
                    type: 'success',
                    timeout: 2000,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Löschen der Einheit.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async moveSubject(subjectId, direction = 'up') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(subjectId)
            const normalizedDirection = this.normalizeMoveDirection(direction)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Fach.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            if (!normalizedDirection) {
                notification.notify({
                    message: 'Ungültige Sortierrichtung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/subjects/' + id + '/move', {
                    data: {
                        direction: normalizedDirection,
                    },
                })
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Fach sortiert.',
                    type: 'success',
                    timeout: 1800,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Sortieren des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async moveTopic(topicId, direction = 'up') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(topicId)
            const normalizedDirection = this.normalizeMoveDirection(direction)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültiges Thema.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            if (!normalizedDirection) {
                notification.notify({
                    message: 'Ungültige Sortierrichtung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/topics/' + id + '/move', {
                    data: {
                        direction: normalizedDirection,
                    },
                })
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema sortiert.',
                    type: 'success',
                    timeout: 1800,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Sortieren des Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async moveUnit(unitId, direction = 'up') {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const id = Number(unitId)
            const normalizedDirection = this.normalizeMoveDirection(direction)

            if (!Number.isFinite(id) || id <= 0) {
                notification.notify({
                    message: 'Ungültige Einheit.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            if (!normalizedDirection) {
                notification.notify({
                    message: 'Ungültige Sortierrichtung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return false
            }

            adminStore.is_loading++
            try {
                await axios.post('/api/admin/materials/units/' + id + '/move', {
                    data: {
                        direction: normalizedDirection,
                    },
                })
                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Einheit sortiert.',
                    type: 'success',
                    timeout: 1800,
                })

                return true
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Sortieren der Einheit.',
                    type: 'error',
                    timeout: 3000,
                })
                return false
            } finally {
                adminStore.is_loading--
            }
        },

        async convertSubjectToTopic(subjectId, targetSubjectId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(subjectId)
            const targetId = Number(targetSubjectId)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !Number.isFinite(targetId) || targetId <= 0) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/subjects/${sourceId}/convert-to-topic`, {
                    data: {
                        target_subject_id: targetId,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Fach als Thema verschoben.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Verschieben des Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async moveTopicToSubject(topicId, targetSubjectId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(topicId)
            const targetId = Number(targetSubjectId)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !Number.isFinite(targetId) || targetId <= 0) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/topics/${sourceId}/move-to-subject`, {
                    data: {
                        target_subject_id: targetId,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema verschoben.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Verschieben des Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async convertTopicToUnit(topicId, targetTopicId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(topicId)
            const targetId = Number(targetTopicId)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !Number.isFinite(targetId) || targetId <= 0) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/topics/${sourceId}/convert-to-unit`, {
                    data: {
                        target_topic_id: targetId,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema als Einheit verschoben.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Verschieben des Themas als Einheit.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async convertTopicToSubject(topicId, newSubjectName) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(topicId)
            const normalizedName = String(newSubjectName ?? '').trim().slice(0, 255)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !normalizedName) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/topics/${sourceId}/convert-to-subject`, {
                    data: {
                        new_subject_name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Thema als neues Fach angelegt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des neuen Fachs.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async moveUnitToTopic(unitId, targetTopicId) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(unitId)
            const targetId = Number(targetTopicId)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !Number.isFinite(targetId) || targetId <= 0) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/units/${sourceId}/move-to-topic`, {
                    data: {
                        target_topic_id: targetId,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Einheit verschoben.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Verschieben der Einheit.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async convertUnitToTopic(unitId, targetSubjectId, newTopicName) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const sourceId = Number(unitId)
            const subjectId = Number(targetSubjectId)
            const normalizedName = String(newTopicName ?? '').trim().slice(0, 255)

            if (!Number.isFinite(sourceId) || sourceId <= 0 || !Number.isFinite(subjectId) || subjectId <= 0 || !normalizedName) {
                notification.notify({
                    message: 'Ungültige Verschiebung.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.post(`/api/admin/materials/units/${sourceId}/convert-to-topic`, {
                    data: {
                        target_subject_id: subjectId,
                        new_topic_name: normalizedName,
                    },
                })

                await this.loadConfig()
                if (Array.isArray(this.cards) && this.cards.length > 0) {
                    await this.indexAll()
                }

                notification.notify({
                    message: 'Einheit als neues Thema angelegt.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Anlegen des neuen Themas.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateFileSettings(maxUploadSizeKb) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const value = Number(maxUploadSizeKb)

            if (!Number.isFinite(value) || value <= 0) {
                notification.notify({
                    message: 'Bitte eine gültige Uploadgröße eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/file-settings', {
                    data: {
                        max_upload_size_kb: Math.round(value),
                    },
                })

                await this.loadConfig()

                notification.notify({
                    message: 'Dateieinstellungen gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Dateieinstellungen.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },

        async updateUserSettings(materialsPaginationNumber) {
            const notification = useNotificationStore()
            const adminStore = useAdminStore()
            const value = Number(materialsPaginationNumber)

            if (!Number.isFinite(value) || value <= 0) {
                notification.notify({
                    message: 'Bitte eine gültige Zahl für die Materialseiten eingeben.',
                    type: 'warning',
                    timeout: 2500,
                })
                return null
            }

            adminStore.is_loading++
            try {
                const response = await axios.put('/api/admin/materials/user-settings', {
                    data: {
                        materials_pagination_number: Math.max(1, Math.min(200, Math.round(value))),
                    },
                })

                await this.loadConfig()

                notification.notify({
                    message: 'Benutzereinstellungen gespeichert.',
                    type: 'success',
                    timeout: 2000,
                })

                return response?.data?.data || null
            } catch (error) {
                notification.notify({
                    status: error.response?.status,
                    message: error.response?.data?.message || 'Fehler beim Speichern der Benutzereinstellungen.',
                    type: 'error',
                    timeout: 3000,
                })
                return null
            } finally {
                adminStore.is_loading--
            }
        },
    },
})
