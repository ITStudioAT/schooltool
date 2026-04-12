<template>
    <div class="curricula-overview">
        <v-sheet rounded="xl" class="curricula-overview__toolbar pa-3 mb-3">
            <div class="curricula-overview__toolbar-inner">
                <v-text-field
                    v-model="searchInput"
                    density="compact"
                    hide-details
                    clearable
                    variant="solo-filled"
                    placeholder="Curricula suchen..."
                    prepend-inner-icon="mdi-magnify"
                    class="curricula-overview__search"
                    @update:modelValue="onSearchChanged" />
                <v-btn
                    color="success"
                    variant="flat"
                    rounded="xl"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog">
                    Neues Curriculum
                </v-btn>
            </div>
        </v-sheet>

        <v-sheet rounded="xl" class="curricula-overview__list pa-4">
            <div v-if="curriculumStore.is_loading && !curricula.length" class="curricula-overview__empty text-center py-6">
                <v-progress-circular indeterminate color="primary" size="28" class="mb-2" />
                <div>Curricula werden geladen...</div>
            </div>
            <div v-else-if="!curricula.length" class="curricula-overview__empty text-center py-6">
                <v-icon size="40" color="primary" class="mb-2">mdi-book-education-outline</v-icon>
                <div class="text-body-2 font-weight-medium">Noch keine Curricula vorhanden.</div>
                <div class="text-caption">Lege ein erstes Curriculum an.</div>
            </div>
            <v-list v-else bg-color="transparent" density="compact" class="py-0">
                <v-list-item
                    v-for="curriculum in curricula"
                    :key="curriculum.id"
                    class="curricula-overview__item mb-2 px-3"
                    min-height="44"
                    rounded="lg"
                    style="cursor: pointer"
                    @click="$emit('select', curriculum)">
                    <template #prepend>
                        <v-icon color="#a5b4fc" size="18" class="mr-2">mdi-book-education-outline</v-icon>
                    </template>
                    <v-list-item-title class="text-body-2 font-weight-bold">{{ curriculum.title }}</v-list-item-title>
                    <v-list-item-subtitle class="text-caption">
                        <span v-if="curriculum.description">{{ curriculum.description }} · </span>
                        <span class="curricula-overview__semester-badge">{{ curriculum.semester_count ?? 2 }} Semester</span>
                    </v-list-item-subtitle>
                    <template #append>
                        <v-btn
                            icon="mdi-pencil-outline"
                            variant="tonal"
                            color="primary"
                            size="x-small"
                            class="mr-1"
                            title="Bearbeiten"
                            @click.stop="openEditDialog(curriculum)" />
                        <v-btn
                            icon="mdi-delete-outline"
                            variant="tonal"
                            color="warning"
                            size="x-small"
                            title="Löschen"
                            @click.stop="askDelete(curriculum)" />
                    </template>
                </v-list-item>
            </v-list>

            <div v-if="meta.last_page > 1" class="curricula-overview__pagination d-flex justify-center mt-4">
                <v-pagination
                    v-model="currentPage"
                    :length="meta.last_page"
                    :total-visible="7"
                    rounded="circle"
                    @update:modelValue="changePage" />
            </div>

            <div v-if="meta.total > 0" class="curricula-overview__total text-caption text-center mt-2">
                {{ meta.total }} Curriculum{{ meta.total === 1 ? '' : 'a' }} insgesamt
            </div>
        </v-sheet>

        <v-dialog v-model="dialogOpen" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="primary" size="22">mdi-book-education-outline</v-icon>
                    {{ editing ? 'Curriculum bearbeiten' : 'Neues Curriculum' }}
                </v-card-title>
                <v-card-text class="px-4">
                    <v-text-field
                        v-model="form.title"
                        label="Titel"
                        variant="outlined"
                        density="comfortable"
                        :error-messages="formErrors.title"
                        autofocus
                        class="mb-2" />
                    <v-textarea
                        v-model="form.description"
                        label="Beschreibung"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        auto-grow
                        :error-messages="formErrors.description"
                        class="mb-2" />
                    <div class="text-body-2 font-weight-medium mb-1">Anzahl Semester</div>
                    <v-btn-toggle
                        v-model="form.semester_count"
                        mandatory
                        color="primary"
                        density="comfortable"
                        rounded="lg"
                        class="mb-1">
                        <v-btn :value="1" variant="outlined" class="text-none px-6">1 Semester</v-btn>
                        <v-btn :value="2" variant="outlined" class="text-none px-6">2 Semester</v-btn>
                    </v-btn-toggle>
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="saving" @click="closeDialog">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="primary" variant="flat" :loading="saving" @click="saveForm">
                        {{ editing ? 'Speichern' : 'Anlegen' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialogOpen" max-width="420" persistent>
            <v-card rounded="xl">
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2 pt-4 px-4">
                    <v-icon color="error" size="20">mdi-delete-outline</v-icon>
                    Curriculum löschen
                </v-card-title>
                <v-card-text class="px-4">
                    Soll das Curriculum <strong>„{{ deleteTarget?.title }}"</strong> wirklich gelöscht werden?
                </v-card-text>
                <v-card-actions class="px-4 pb-4">
                    <v-btn variant="tonal" :disabled="deleteLoading" @click="deleteDialogOpen = false">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn color="error" variant="flat" :loading="deleteLoading" @click="confirmDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapState } from 'pinia'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

export default {
    name: 'TeachingCurriculaOverview',
    emits: ['select'],

    data() {
        return {
            curriculumStore: null,
            searchInput: '',
            searchTimer: null,
            currentPage: 1,
            dialogOpen: false,
            editing: null,
            form: { title: '', description: '', semester_count: 2 },
            formErrors: {},
            saving: false,
            deleteDialogOpen: false,
            deleteTarget: null,
            deleteLoading: false,
        }
    },

    computed: {
        ...mapState(useCurriculumStore, ['curricula', 'meta']),
    },

    async beforeMount() {
        this.curriculumStore = useCurriculumStore()
        this.searchInput = this.curriculumStore.search
        this.currentPage = this.curriculumStore.meta.current_page || 1
        await this.curriculumStore.index({ page: this.currentPage })
        this.currentPage = this.curriculumStore.meta.current_page || 1
    },

    methods: {
        async changePage(page) {
            this.currentPage = page
            await this.curriculumStore.index({ page })
        },
        onSearchChanged(value) {
            this.curriculumStore.search = value || ''
            if (this.searchTimer) clearTimeout(this.searchTimer)
            this.searchTimer = setTimeout(async () => {
                this.currentPage = 1
                await this.curriculumStore.index({ page: 1, search: this.curriculumStore.search })
            }, 300)
        },
        openCreateDialog() {
            this.editing = null
            this.form = { title: '', description: '', semester_count: 2 }
            this.formErrors = {}
            this.dialogOpen = true
        },
        openEditDialog(curriculum) {
            this.editing = curriculum
            this.form = {
                title: curriculum.title || '',
                description: curriculum.description || '',
                semester_count: curriculum.semester_count ?? 2,
            }
            this.formErrors = {}
            this.dialogOpen = true
        },
        closeDialog() {
            if (this.saving) return
            this.dialogOpen = false
        },
        async saveForm() {
            this.formErrors = {}
            if (!this.form.title.trim()) {
                this.formErrors = { title: 'Titel ist erforderlich.' }
                return
            }
            this.saving = true
            try {
                const payload = {
                    title: this.form.title.trim(),
                    description: this.form.description?.trim() || null,
                    semester_count: this.form.semester_count ?? 2,
                    free_weeks: this.editing?.free_weeks ?? [],
                    topics: this.editing?.topics ?? [],
                }
                const result = this.editing
                    ? await this.curriculumStore.update(this.editing.id, payload)
                    : await this.curriculumStore.store(payload)
                if (result) {
                    this.dialogOpen = false
                    await this.curriculumStore.index({ page: this.editing ? this.currentPage : 1 })
                    if (!this.editing) {
                        this.currentPage = 1
                    }
                }
            } finally {
                this.saving = false
            }
        },
        askDelete(curriculum) {
            this.deleteTarget = curriculum
            this.deleteDialogOpen = true
        },
        async confirmDelete() {
            if (!this.deleteTarget) return
            this.deleteLoading = true
            try {
                const ok = await this.curriculumStore.destroy(this.deleteTarget.id)
                if (ok) {
                    this.deleteDialogOpen = false
                    this.deleteTarget = null
                    const targetPage = this.curricula.length === 1 && this.currentPage > 1
                        ? this.currentPage - 1
                        : this.currentPage
                    this.currentPage = targetPage
                    await this.curriculumStore.index({ page: targetPage })
                }
            } finally {
                this.deleteLoading = false
            }
        },
    },
}
</script>

<style scoped>
.curricula-overview {
    max-width: 720px;
}

.curricula-overview__toolbar {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(30, 41, 59, 0.75);
}

.curricula-overview__toolbar-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.curricula-overview__search {
    flex: 1 1 260px;
    min-width: 220px;
}

.curricula-overview__list {
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(30, 41, 59, 0.75);
    color: #e2e8f0;
}

.curricula-overview__item {
    background: rgba(15, 23, 42, 0.55);
    border: 1px solid rgba(99, 102, 241, 0.18);
}

.curricula-overview__empty {
    color: #cbd5e1;
}

.curricula-overview__empty .text-caption {
    color: #94a3b8;
}

.curricula-overview__total {
    color: #94a3b8;
}

.curricula-overview__semester-badge {
    color: #a5b4fc;
    font-weight: 600;
}
</style>
