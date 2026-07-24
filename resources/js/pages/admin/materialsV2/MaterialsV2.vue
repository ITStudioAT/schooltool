<template>
    <v-container fluid class="materials-v2-page pa-3 pa-md-6">
        <div class="materials-v2-orb materials-v2-orb--one" />
        <div class="materials-v2-orb materials-v2-orb--two" />

        <div class="materials-v2-content">
            <header class="materials-v2-header">
                <div>
                    <div class="materials-v2-eyebrow">Einfach speichern. Intelligent finden.</div>
                    <h1 class="materials-v2-title">Materialien 2</h1>
                    <p class="materials-v2-subtitle">
                        Dein Material, seine Inhalte, flexible Kategorien und eine Suche, die mitdenkt.
                    </p>
                </div>

                <v-btn
                    color="primary"
                    size="large"
                    rounded="xl"
                    elevation="0"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog">
                    Material erstellen
                </v-btn>
            </header>

            <section class="materials-v2-search-panel">
                <v-text-field
                    v-model="search"
                    class="materials-v2-search"
                    variant="solo"
                    flat
                    rounded="xl"
                    clearable
                    hide-details
                    autocomplete="off"
                    prepend-inner-icon="mdi-magnify"
                    placeholder="Was suchst du? Mehrere Wörter, Wortteile und kleine Tippfehler sind erlaubt …"
                    :loading="loading"
                    @click:clear="clearSearch" />

                <div class="materials-v2-search-hints">
                    <span><v-icon size="16">mdi-auto-fix</v-icon> flexibel & fehlertolerant</span>
                    <span><v-icon size="16">mdi-file-document-search-outline</v-icon> durchsucht Dokumentinhalte</span>
                    <span><v-icon size="16">mdi-sort-descending</v-icon> beste Treffer zuerst</span>
                </div>
            </section>

            <div class="materials-v2-toolbar">
                <div>
                    <span class="text-subtitle-1 font-weight-bold">
                        {{ search.trim() ? 'Suchergebnisse' : 'Deine Materialien' }}
                    </span>
                    <span v-if="!loading" class="text-body-2 text-medium-emphasis ml-2">
                        {{ meta.total }} {{ meta.total === 1 ? 'Material' : 'Materialien' }}
                    </span>
                </div>

                <v-chip v-if="hasProcessingItems" color="info" variant="tonal" prepend-icon="mdi-progress-clock">
                    Inhalte werden analysiert
                </v-chip>
            </div>

            <v-alert
                v-if="loadError"
                class="mb-5"
                type="error"
                variant="tonal"
                rounded="xl"
                closable
                @click:close="loadError = ''">
                {{ loadError }}
            </v-alert>

            <v-row v-if="loading && !items.length" dense>
                <v-col v-for="index in 6" :key="index" cols="12" md="6" xl="4">
                    <v-skeleton-loader class="materials-v2-card" type="article, actions" />
                </v-col>
            </v-row>

            <v-row v-else-if="items.length" dense>
                <v-col v-for="item in items" :key="item.id" cols="12" md="6" xl="4">
                    <v-card class="materials-v2-card h-100" rounded="xl" elevation="0">
                        <v-card-text class="pa-5">
                            <div class="d-flex align-start justify-space-between ga-3">
                                <div class="min-width-0">
                                    <div class="d-flex align-center flex-wrap ga-2 mb-2">
                                        <v-chip
                                            v-if="search.trim() && item.search_score"
                                            size="x-small"
                                            color="primary"
                                            variant="flat">
                                            {{ Math.round(item.search_score) }} Punkte
                                        </v-chip>
                                        <v-chip
                                            size="x-small"
                                            :color="statusMeta(item.processing_status).color"
                                            variant="tonal"
                                            :prepend-icon="statusMeta(item.processing_status).icon">
                                            {{ statusMeta(item.processing_status).label }}
                                        </v-chip>
                                    </div>

                                    <div class="d-flex align-center flex-wrap ga-2">
                                        <h2 class="materials-v2-card-title">{{ item.title }}</h2>
                                        <v-chip
                                            v-if="item.category"
                                            size="x-small"
                                            color="secondary"
                                            variant="tonal"
                                            prepend-icon="mdi-shape-outline">
                                            {{ item.category }}
                                        </v-chip>
                                    </div>
                                </div>

                                <v-menu>
                                    <template #activator="{ props }">
                                        <v-btn v-bind="props" icon="mdi-dots-horizontal" size="small" variant="text" />
                                    </template>
                                    <v-list density="compact">
                                        <v-list-item prepend-icon="mdi-pencil-outline" title="Bearbeiten" @click="openEditDialog(item)" />
                                        <v-list-item prepend-icon="mdi-paperclip-plus" title="Anlagen hinzufügen" @click="openAttachmentDialog(item)" />
                                        <v-list-item
                                            v-if="['failed', 'partial'].includes(item.processing_status)"
                                            prepend-icon="mdi-refresh"
                                            title="Verarbeitung wiederholen"
                                            @click="retryProcessing(item)" />
                                        <v-divider />
                                        <v-list-item
                                            prepend-icon="mdi-delete-outline"
                                            title="Löschen"
                                            base-color="error"
                                            @click="openDeleteDialog(item)" />
                                    </v-list>
                                </v-menu>
                            </div>

                            <p v-if="item.description" class="materials-v2-description">
                                {{ item.description }}
                            </p>
                            <p v-else class="materials-v2-description materials-v2-description--empty">
                                Keine Beschreibung
                            </p>

                            <v-alert
                                v-if="item.processing_error"
                                class="mb-4"
                                density="compact"
                                type="warning"
                                variant="tonal">
                                {{ item.processing_error }}
                            </v-alert>

                            <div v-if="item.user_keywords?.length" class="materials-v2-keyword-block">
                                <div class="materials-v2-keyword-label">Deine Suchwörter</div>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="keyword in item.user_keywords"
                                        :key="`user-${item.id}-${keyword}`"
                                        size="small"
                                        color="secondary"
                                        variant="outlined">
                                        {{ keyword }}
                                    </v-chip>
                                </div>
                            </div>

                            <div v-if="item.generated_keywords?.length" class="materials-v2-keyword-block">
                                <div class="materials-v2-keyword-label">
                                    <v-icon size="15" class="mr-1">mdi-sparkles</v-icon>
                                    Aus dem Inhalt erkannt
                                </div>
                                <div class="d-flex flex-wrap ga-1">
                                    <v-chip
                                        v-for="keyword in item.generated_keywords.slice(0, 8)"
                                        :key="`generated-${item.id}-${keyword}`"
                                        size="small"
                                        color="primary"
                                        variant="tonal">
                                        {{ keyword }}
                                    </v-chip>
                                </div>
                            </div>

                            <div class="materials-v2-attachments">
                                <div class="materials-v2-attachment-heading">
                                    <v-icon size="18">mdi-paperclip</v-icon>
                                    {{ item.attachments?.length || 0 }}
                                    {{ item.attachments?.length === 1 ? 'Anlage' : 'Anlagen' }}
                                </div>

                                <div v-if="item.attachments?.length" class="d-flex flex-column ga-2 mt-2">
                                    <div
                                        v-for="attachment in item.attachments"
                                        :key="attachment.id"
                                        class="materials-v2-attachment-row">
                                        <v-icon size="20" color="primary">{{ attachmentIcon(attachment) }}</v-icon>
                                        <button type="button" class="materials-v2-attachment-name" @click="previewAttachment(attachment)">
                                            {{ attachment.original_name }}
                                        </button>
                                        <span class="materials-v2-file-size">{{ formatFileSize(attachment.size_bytes) }}</span>
                                        <v-btn
                                            icon="mdi-eye-outline"
                                            size="x-small"
                                            variant="text"
                                            title="Vorschau"
                                            :aria-label="`${attachment.original_name} in der Vorschau öffnen`"
                                            @click="previewAttachment(attachment)" />
                                        <v-btn
                                            :href="attachment.download_url"
                                            target="_blank"
                                            icon="mdi-download"
                                            size="x-small"
                                            variant="text"
                                            title="Herunterladen" />
                                        <v-btn
                                            icon="mdi-close"
                                            size="x-small"
                                            variant="text"
                                            color="error"
                                            title="Anlage entfernen"
                                            @click="removeAttachment(item, attachment)" />
                                    </div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>

            <v-card v-else class="materials-v2-empty" rounded="xl" elevation="0">
                <v-icon size="54" color="primary">
                    {{ search.trim() ? 'mdi-file-search-outline' : 'mdi-folder-plus-outline' }}
                </v-icon>
                <h2>{{ search.trim() ? 'Noch kein passender Treffer' : 'Deine neue Materialsammlung ist leer' }}</h2>
                <p>
                    {{
                        search.trim()
                            ? 'Versuche andere Begriffe oder nur einen Wortteil.'
                            : 'Erstelle dein erstes Material – mit oder ohne Kategorie.'
                    }}
                </p>
                <v-btn v-if="!search.trim()" color="primary" rounded="xl" prepend-icon="mdi-plus" @click="openCreateDialog">
                    Erstes Material erstellen
                </v-btn>
            </v-card>

            <div v-if="meta.last_page > 1" class="d-flex justify-center mt-7">
                <v-pagination
                    v-model="page"
                    :length="meta.last_page"
                    :total-visible="7"
                    rounded="circle"
                    @update:model-value="loadItems" />
            </div>
        </div>

        <v-dialog v-model="materialDialog.open" persistent max-width="720">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">
                        {{ materialDialog.mode === 'create' ? 'mdi-file-plus-outline' : 'mdi-file-edit-outline' }}
                    </v-icon>
                    {{ materialDialog.mode === 'create' ? 'Material erstellen' : 'Material bearbeiten' }}
                </v-card-title>
                <v-card-text class="pa-6 pt-3">
                    <v-row dense>
                        <v-col cols="12" md="7">
                            <v-text-field
                                v-model="materialForm.title"
                                label="Titel"
                                variant="outlined"
                                maxlength="255"
                                counter
                                autofocus
                                :error-messages="formErrors.title"
                                :disabled="materialDialog.saving" />
                        </v-col>
                        <v-col cols="12" md="5">
                            <v-combobox
                                v-model="materialForm.category"
                                label="Kategorie (optional)"
                                variant="outlined"
                                maxlength="255"
                                clearable
                                :items="categoryOptions"
                                :return-object="false"
                                :error-messages="formErrors.category"
                                :disabled="materialDialog.saving"
                                hint="Bestehende Kategorie wählen oder eine neue eingeben"
                                persistent-hint />
                        </v-col>
                    </v-row>
                    <v-textarea
                        v-model="materialForm.description"
                        class="mt-2"
                        label="Beschreibung (optional)"
                        variant="outlined"
                        rows="3"
                        auto-grow
                        maxlength="10000"
                        :error-messages="formErrors.description"
                        :disabled="materialDialog.saving" />
                    <v-text-field
                        v-model="materialForm.keywords"
                        class="mt-2"
                        label="Eigene Suchwörter (optional)"
                        hint="Mit Komma trennen, z. B. Bruchrechnen, Übung, 2. Klasse"
                        persistent-hint
                        variant="outlined"
                        prepend-inner-icon="mdi-tag-multiple-outline"
                        :error-messages="formErrors.user_keywords"
                        :disabled="materialDialog.saving" />
                    <v-file-input
                        v-if="materialDialog.mode === 'create'"
                        v-model="materialForm.attachments"
                        class="mt-5"
                        label="Anlagen (optional)"
                        variant="outlined"
                        multiple
                        chips
                        show-size
                        counter
                        prepend-icon=""
                        prepend-inner-icon="mdi-paperclip"
                        :error-messages="formErrors.attachments"
                        :disabled="materialDialog.saving" />
                    <v-alert class="mt-4" type="info" variant="tonal" density="compact">
                        Dokumente werden nach dem Speichern gelesen. Daraus entstehen automatisch zusätzliche Suchwörter.
                    </v-alert>
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="materialDialog.saving" @click="closeMaterialDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        rounded="lg"
                        :loading="materialDialog.saving"
                        :disabled="!materialForm.title.trim()"
                        @click="saveMaterial()">
                        {{ materialDialog.mode === 'create' ? 'Erstellen' : 'Speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="categorySuggestionDialog.open" persistent max-width="560">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="warning" class="mr-2">mdi-spellcheck</v-icon>
                    Ähnliche Kategorie gefunden
                </v-card-title>
                <v-card-text class="px-6 pb-2">
                    Du hast <strong>„{{ categorySuggestionDialog.entered }}“</strong> eingegeben.
                    Es gibt bereits die ähnliche Kategorie
                    <strong>„{{ categorySuggestionDialog.existing }}“</strong>.
                    Welche möchtest du verwenden?
                </v-card-text>
                <v-card-actions class="px-6 pb-5 flex-wrap ga-2">
                    <v-btn variant="text" @click="categorySuggestionDialog.open = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn variant="tonal" color="secondary" @click="keepNewCategory">
                        Neue Kategorie anlegen
                    </v-btn>
                    <v-btn color="primary" @click="useSuggestedCategory">
                        Bestehende übernehmen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="previewDialog.open" max-width="1200" scrollable>
            <v-card rounded="xl" class="materials-v2-preview-card">
                <v-card-title class="materials-v2-dialog-title materials-v2-preview-title">
                    <v-icon color="primary" class="mr-2">{{ attachmentIcon(previewDialog.attachment || {}) }}</v-icon>
                    <span class="materials-v2-preview-name">
                        {{ previewDialog.attachment?.original_name || 'Anlage' }}
                    </span>
                    <v-spacer />
                    <v-btn
                        v-if="previewDialog.attachment?.preview_url"
                        :href="previewDialog.attachment.preview_url"
                        target="_blank"
                        icon="mdi-open-in-new"
                        size="small"
                        variant="text"
                        title="In neuem Tab öffnen" />
                    <v-btn
                        v-if="previewDialog.attachment?.download_url"
                        :href="previewDialog.attachment.download_url"
                        target="_blank"
                        icon="mdi-download"
                        size="small"
                        variant="text"
                        title="Herunterladen" />
                    <v-btn
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        title="Vorschau schließen"
                        @click="closePreviewDialog" />
                </v-card-title>
                <v-card-text class="materials-v2-preview-body">
                    <iframe
                        v-if="previewDialog.attachment?.preview_url"
                        class="materials-v2-preview-frame"
                        :src="previewDialog.attachment.preview_url"
                        :title="`Vorschau: ${previewDialog.attachment.original_name}`"
                        sandbox="allow-downloads allow-same-origin" />
                </v-card-text>
            </v-card>
        </v-dialog>

        <v-dialog v-model="attachmentDialog.open" persistent max-width="600">
            <v-card rounded="xl">
                <v-card-title class="materials-v2-dialog-title">
                    <v-icon color="primary" class="mr-2">mdi-paperclip-plus</v-icon>
                    Anlagen hinzufügen
                </v-card-title>
                <v-card-text class="pa-6 pt-3">
                    <div class="text-body-2 text-medium-emphasis mb-4">{{ attachmentDialog.item?.title }}</div>
                    <v-file-input
                        v-model="attachmentDialog.files"
                        label="Dateien auswählen"
                        variant="outlined"
                        multiple
                        chips
                        show-size
                        counter
                        prepend-icon=""
                        prepend-inner-icon="mdi-paperclip"
                        :error-messages="attachmentDialog.error"
                        :disabled="attachmentDialog.saving" />
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="attachmentDialog.saving" @click="closeAttachmentDialog">Abbrechen</v-btn>
                    <v-btn
                        color="primary"
                        :loading="attachmentDialog.saving"
                        :disabled="!(attachmentDialog.files || []).length"
                        @click="saveAttachments">
                        Hochladen
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog.open" persistent max-width="500">
            <v-card rounded="xl">
                <v-card-title>Material löschen?</v-card-title>
                <v-card-text>
                    <strong>{{ deleteDialog.item?.title }}</strong>
                    wird aus Materialien 2 entfernt. Die bestehende Materialien-Version bleibt davon unberührt.
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" :disabled="deleteDialog.saving" @click="deleteDialog.open = false">Abbrechen</v-btn>
                    <v-btn color="error" :loading="deleteDialog.saving" @click="deleteMaterial">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-container>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

const notification = useNotificationStore()

const search = ref('')
const page = ref(1)
const items = ref([])
const categoryOptions = ref([])
const loading = ref(false)
const loadError = ref('')
const meta = reactive({
    total: 0,
    current_page: 1,
    last_page: 1,
})
const materialDialog = reactive({
    open: false,
    mode: 'create',
    item: null,
    saving: false,
})
const materialForm = reactive({
    title: '',
    category: '',
    description: '',
    keywords: '',
    attachments: [],
})
const formErrors = reactive({
    title: [],
    category: [],
    description: [],
    user_keywords: [],
    attachments: [],
})
const attachmentDialog = reactive({
    open: false,
    item: null,
    files: [],
    error: [],
    saving: false,
})
const previewDialog = reactive({
    open: false,
    attachment: null,
})
const categorySuggestionDialog = reactive({
    open: false,
    entered: '',
    existing: '',
})
const deleteDialog = reactive({
    open: false,
    item: null,
    saving: false,
})

let searchTimer = null
let pollingTimer = null

const hasProcessingItems = computed(() =>
    items.value.some((item) => ['pending', 'processing'].includes(item.processing_status)),
)

watch(search, () => {
    window.clearTimeout(searchTimer)
    searchTimer = window.setTimeout(() => {
        page.value = 1
        loadItems()
    }, 350)
})

watch(hasProcessingItems, (isProcessing) => {
    configurePolling(isProcessing)
})

onMounted(async () => {
    await Promise.all([loadConfig(), loadItems()])
})

onBeforeUnmount(() => {
    window.clearTimeout(searchTimer)
    window.clearInterval(pollingTimer)
})

async function loadItems() {
    loading.value = true
    loadError.value = ''

    try {
        const response = await axios.get('/api/admin/materials-v2/items', {
            params: {
                search: search.value.trim() || undefined,
                page: page.value,
                per_page: 18,
            },
        })

        items.value = response.data?.data || []
        Object.assign(meta, response.data?.meta || {
            total: items.value.length,
            current_page: 1,
            last_page: 1,
        })
    } catch (error) {
        loadError.value = apiErrorMessage(error, 'Die Materialien konnten nicht geladen werden.')
    } finally {
        loading.value = false
    }
}

async function loadConfig() {
    try {
        const response = await axios.get('/api/admin/materials-v2/config')
        categoryOptions.value = Array.isArray(response.data?.categories) ? response.data.categories : []
    } catch {
        categoryOptions.value = []
    }
}

function clearSearch() {
    search.value = ''
    page.value = 1
    loadItems()
}

function openCreateDialog() {
    resetForm()
    materialDialog.mode = 'create'
    materialDialog.item = null
    materialDialog.open = true
}

function openEditDialog(item) {
    resetForm()
    materialDialog.mode = 'edit'
    materialDialog.item = item
    materialForm.title = item.title || ''
    materialForm.category = item.category || ''
    materialForm.description = item.description || ''
    materialForm.keywords = (item.user_keywords || []).join(', ')
    materialDialog.open = true
}

function closeMaterialDialog() {
    if (!materialDialog.saving) {
        materialDialog.open = false
    }
}

async function saveMaterial({ forceNewCategory = false } = {}) {
    clearFormErrors()
    materialDialog.saving = true

    try {
        if (materialDialog.mode === 'create') {
            const payload = new FormData()
            payload.append('title', materialForm.title.trim())

            if (normalizedCategory()) {
                payload.append('category', normalizedCategory())
            }

            if (forceNewCategory) {
                payload.append('force_new_category', '1')
            }

            if (materialForm.description.trim()) {
                payload.append('description', materialForm.description.trim())
            }

            normalizedKeywords().forEach((keyword) => payload.append('user_keywords[]', keyword))
            const attachments = materialForm.attachments || []
            attachments.forEach((file) => payload.append('attachments[]', file))

            await axios.post('/api/admin/materials-v2/items', payload)
        } else {
            await axios.put(`/api/admin/materials-v2/items/${materialDialog.item.id}`, {
                title: materialForm.title.trim(),
                category: normalizedCategory() || null,
                force_new_category: forceNewCategory,
                description: materialForm.description.trim() || null,
                user_keywords: normalizedKeywords(),
            })
        }

        materialDialog.open = false
        page.value = 1
        await Promise.all([loadItems(), loadConfig()])
        notify('Material gespeichert.')
    } catch (error) {
        if (showCategorySuggestion(error)) {
            return
        }

        applyValidationErrors(error)
        if (error.response?.status !== 422) {
            notify(apiErrorMessage(error, 'Das Material konnte nicht gespeichert werden.'), 'error')
        }
    } finally {
        materialDialog.saving = false
    }
}

function showCategorySuggestion(error) {
    const suggestion = error.response?.data?.category_suggestion
    if (error.response?.status !== 409 || !suggestion?.entered || !suggestion?.existing) {
        return false
    }

    categorySuggestionDialog.entered = String(suggestion.entered)
    categorySuggestionDialog.existing = String(suggestion.existing)
    categorySuggestionDialog.open = true

    return true
}

async function useSuggestedCategory() {
    materialForm.category = categorySuggestionDialog.existing
    categorySuggestionDialog.open = false
    await saveMaterial()
}

async function keepNewCategory() {
    materialForm.category = categorySuggestionDialog.entered
    categorySuggestionDialog.open = false
    await saveMaterial({ forceNewCategory: true })
}

function openAttachmentDialog(item) {
    attachmentDialog.item = item
    attachmentDialog.files = []
    attachmentDialog.error = []
    attachmentDialog.open = true
}

function closeAttachmentDialog() {
    if (!attachmentDialog.saving) {
        attachmentDialog.open = false
    }
}

async function saveAttachments() {
    attachmentDialog.saving = true
    attachmentDialog.error = []

    try {
        const payload = new FormData()
        const attachments = attachmentDialog.files || []
        attachments.forEach((file) => payload.append('attachments[]', file))
        await axios.post(`/api/admin/materials-v2/items/${attachmentDialog.item.id}/attachments`, payload)

        attachmentDialog.open = false
        await loadItems()
        notify('Anlagen hinzugefügt.')
    } catch (error) {
        attachmentDialog.error = validationMessages(error, 'attachments')
        if (!attachmentDialog.error.length) {
            notify(apiErrorMessage(error, 'Die Anlagen konnten nicht gespeichert werden.'), 'error')
        }
    } finally {
        attachmentDialog.saving = false
    }
}

async function removeAttachment(item, attachment) {
    try {
        await axios.delete(`/api/admin/materials-v2/attachments/${attachment.id}`)
        await loadItems()
        notify('Anlage entfernt.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die Anlage konnte nicht entfernt werden.'), 'error')
    }
}

function openDeleteDialog(item) {
    deleteDialog.item = item
    deleteDialog.open = true
}

async function deleteMaterial() {
    deleteDialog.saving = true

    try {
        await axios.delete(`/api/admin/materials-v2/items/${deleteDialog.item.id}`)
        deleteDialog.open = false
        await loadItems()
        notify('Material gelöscht.')
    } catch (error) {
        notify(apiErrorMessage(error, 'Das Material konnte nicht gelöscht werden.'), 'error')
    } finally {
        deleteDialog.saving = false
    }
}

async function retryProcessing(item) {
    try {
        await axios.post(`/api/admin/materials-v2/items/${item.id}/retry-processing`)
        await loadItems()
        notify('Verarbeitung neu gestartet.', 'info')
    } catch (error) {
        notify(apiErrorMessage(error, 'Die Verarbeitung konnte nicht neu gestartet werden.'), 'error')
    }
}

function previewAttachment(attachment) {
    previewDialog.attachment = attachment
    previewDialog.open = true
}

function closePreviewDialog() {
    previewDialog.open = false
}

function configurePolling(isProcessing) {
    window.clearInterval(pollingTimer)
    pollingTimer = null

    if (isProcessing) {
        pollingTimer = window.setInterval(() => {
            if (!loading.value) {
                loadItems()
            }
        }, 4000)
    }
}

function resetForm() {
    materialForm.title = ''
    materialForm.category = ''
    materialForm.description = ''
    materialForm.keywords = ''
    materialForm.attachments = []
    clearFormErrors()
}

function clearFormErrors() {
    Object.keys(formErrors).forEach((key) => {
        formErrors[key] = []
    })
}

function applyValidationErrors(error) {
    formErrors.title = validationMessages(error, 'title')
    formErrors.category = validationMessages(error, 'category')
    formErrors.description = validationMessages(error, 'description')
    formErrors.user_keywords = validationMessages(error, 'user_keywords')
    formErrors.attachments = [
        ...validationMessages(error, 'attachments'),
        ...validationMessages(error, 'attachments.0'),
    ]
}

function normalizedCategory() {
    return String(materialForm.category || '').trim()
}

function validationMessages(error, key) {
    return error.response?.data?.errors?.[key] || []
}

function normalizedKeywords() {
    return materialForm.keywords
        .split(/[,;\n]+/u)
        .map((keyword) => keyword.trim())
        .filter(Boolean)
        .filter((keyword, index, keywords) => keywords.findIndex((candidate) => candidate.toLowerCase() === keyword.toLowerCase()) === index)
        .slice(0, 20)
}

function statusMeta(status) {
    return {
        pending: { label: 'Wartet', color: 'info', icon: 'mdi-clock-outline' },
        processing: { label: 'Wird analysiert', color: 'info', icon: 'mdi-progress-clock' },
        ready: { label: 'Bereit', color: 'success', icon: 'mdi-check-circle-outline' },
        partial: { label: 'Teilweise gelesen', color: 'warning', icon: 'mdi-alert-circle-outline' },
        failed: { label: 'Fehlgeschlagen', color: 'error', icon: 'mdi-alert-outline' },
    }[status] || { label: status || 'Unbekannt', color: 'default', icon: 'mdi-help-circle-outline' }
}

function attachmentIcon(attachment) {
    const mimeType = String(attachment.mime_type || '').toLowerCase()
    const name = String(attachment.original_name || '').toLowerCase()

    if (mimeType.includes('pdf') || name.endsWith('.pdf')) return 'mdi-file-pdf-box'
    if (mimeType.includes('word') || name.endsWith('.docx')) return 'mdi-file-word-outline'
    if (mimeType.includes('sheet') || name.endsWith('.xlsx')) return 'mdi-file-excel-outline'
    if (mimeType.includes('presentation') || name.endsWith('.pptx')) return 'mdi-file-powerpoint-outline'
    if (mimeType.startsWith('image/')) return 'mdi-file-image-outline'
    return 'mdi-file-document-outline'
}

function formatFileSize(bytes) {
    const size = Number(bytes || 0)
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

function apiErrorMessage(error, fallback) {
    return error.response?.data?.message || fallback
}

function notify(message, type = 'success') {
    notification.notify({
        message,
        type,
        timeout: 3200,
    })
}
</script>

<style scoped>
.materials-v2-page {
    --materials-v2-ink: #172d3b;
    --materials-v2-muted: #617482;
    --materials-v2-accent: #ff7a32;
    --materials-v2-mint: #2fbf91;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at 15% 10%, rgba(47, 191, 145, 0.12), transparent 32rem),
        linear-gradient(145deg, #f8fbfa 0%, #f4f7f9 52%, #fff8f2 100%);
    color: var(--materials-v2-ink);
}

.materials-v2-content {
    position: relative;
    z-index: 1;
    width: min(1500px, 100%);
    margin: 0 auto;
}

.materials-v2-orb {
    position: absolute;
    border-radius: 999px;
    filter: blur(5px);
    pointer-events: none;
}

.materials-v2-orb--one {
    width: 320px;
    height: 320px;
    top: -160px;
    right: -70px;
    background: rgba(255, 122, 50, 0.13);
}

.materials-v2-orb--two {
    width: 250px;
    height: 250px;
    bottom: -120px;
    left: -90px;
    background: rgba(47, 191, 145, 0.13);
}

.materials-v2-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2rem;
    padding: 1.5rem 0 1.8rem;
}

.materials-v2-eyebrow {
    margin-bottom: 0.35rem;
    color: var(--materials-v2-accent);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.materials-v2-title {
    margin: 0;
    font-size: clamp(2.2rem, 5vw, 4.4rem);
    font-weight: 850;
    letter-spacing: -0.055em;
    line-height: 1;
}

.materials-v2-subtitle {
    max-width: 780px;
    margin: 0.85rem 0 0;
    color: var(--materials-v2-muted);
    font-size: clamp(1rem, 1.7vw, 1.2rem);
    line-height: 1.55;
}

.materials-v2-search-panel {
    padding: 1rem;
    border: 1px solid rgba(23, 45, 59, 0.08);
    border-radius: 26px;
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 22px 65px rgba(23, 45, 59, 0.09);
    backdrop-filter: blur(18px);
}

.materials-v2-search :deep(.v-field) {
    min-height: 64px;
    border: 2px solid rgba(23, 45, 59, 0.09);
    background: #fff;
    box-shadow: none;
    font-size: 1.05rem;
    transition: border-color 180ms ease, box-shadow 180ms ease;
}

.materials-v2-search :deep(.v-field--focused) {
    border-color: var(--materials-v2-accent);
    box-shadow: 0 0 0 5px rgba(255, 122, 50, 0.11);
}

.materials-v2-search-hints {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.2rem;
    padding: 0.75rem 0.6rem 0.1rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
}

.materials-v2-search-hints span {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.materials-v2-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 68px;
    gap: 1rem;
}

.materials-v2-card {
    border: 1px solid rgba(23, 45, 59, 0.08);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 12px 40px rgba(23, 45, 59, 0.07);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.materials-v2-card:hover {
    transform: translateY(-3px);
    border-color: rgba(255, 122, 50, 0.3);
    box-shadow: 0 18px 50px rgba(23, 45, 59, 0.12);
}

.materials-v2-card-title {
    overflow: hidden;
    margin: 0;
    font-size: 1.2rem;
    font-weight: 780;
    letter-spacing: -0.02em;
    line-height: 1.3;
    text-overflow: ellipsis;
}

.materials-v2-description {
    display: -webkit-box;
    min-height: 3.1rem;
    margin: 1rem 0;
    overflow: hidden;
    color: var(--materials-v2-muted);
    font-size: 0.92rem;
    line-height: 1.55;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.materials-v2-description--empty {
    font-style: italic;
    opacity: 0.65;
}

.materials-v2-keyword-block + .materials-v2-keyword-block {
    margin-top: 0.85rem;
}

.materials-v2-keyword-label {
    display: flex;
    align-items: center;
    margin-bottom: 0.4rem;
    color: var(--materials-v2-muted);
    font-size: 0.72rem;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.materials-v2-attachments {
    margin-top: 1.15rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(23, 45, 59, 0.08);
}

.materials-v2-attachment-heading {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--materials-v2-muted);
    font-size: 0.78rem;
    font-weight: 700;
}

.materials-v2-attachment-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    padding: 0.5rem 0.6rem;
    border-radius: 12px;
    background: #f6f8f8;
}

.materials-v2-attachment-name {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    color: var(--materials-v2-ink);
    font-size: 0.82rem;
    font-weight: 650;
    text-align: left;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-attachment-name:hover {
    color: var(--materials-v2-accent);
    text-decoration: underline;
}

.materials-v2-file-size {
    color: var(--materials-v2-muted);
    font-size: 0.7rem;
    white-space: nowrap;
}

.materials-v2-empty {
    display: flex;
    min-height: 330px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 3rem;
    border: 1px dashed rgba(23, 45, 59, 0.2);
    background: rgba(255, 255, 255, 0.7);
    text-align: center;
}

.materials-v2-empty h2 {
    margin: 1rem 0 0.4rem;
    font-size: 1.35rem;
}

.materials-v2-empty p {
    max-width: 520px;
    margin: 0 0 1.25rem;
    color: var(--materials-v2-muted);
}

.materials-v2-dialog-title {
    display: flex;
    align-items: center;
    padding: 1.4rem 1.5rem 1rem;
    font-weight: 760;
}

.materials-v2-preview-card {
    height: min(88vh, 900px);
}

.materials-v2-preview-title {
    flex: 0 0 auto;
    gap: 0.25rem;
}

.materials-v2-preview-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.materials-v2-preview-body {
    display: flex;
    min-height: 0;
    padding: 0 !important;
    background: #eef2f4;
}

.materials-v2-preview-frame {
    width: 100%;
    height: 100%;
    min-height: 65vh;
    border: 0;
    background: #fff;
}

.min-width-0 {
    min-width: 0;
}

@media (max-width: 700px) {
    .materials-v2-header {
        align-items: stretch;
        flex-direction: column;
        gap: 1.2rem;
    }

    .materials-v2-search-hints {
        align-items: flex-start;
        flex-direction: column;
    }

    .materials-v2-toolbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 1rem 0;
    }

    .materials-v2-file-size {
        display: none;
    }
}
</style>
