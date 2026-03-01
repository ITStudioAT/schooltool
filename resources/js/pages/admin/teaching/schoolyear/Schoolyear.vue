<template>
    <v-col cols="12">
        <section class="teaching-schoolyear-page">
            <section class="teaching-schoolyear-toolbar">
                <v-chip size="small" color="primary" variant="flat" prepend-icon="mdi-calendar-check-outline">
                    Aktiv: {{ activeSchoolyearName }}
                </v-chip>
                <v-chip size="small" color="secondary" variant="tonal" prepend-icon="mdi-calendar-multiselect">
                    {{ schoolyearCountLabel }}
                </v-chip>
                <v-chip v-if="selectedSchoolyearRange" size="small" color="secondary" variant="tonal" prepend-icon="mdi-timeline-clock-outline">
                    {{ selectedSchoolyearRange }}
                </v-chip>
            </section>

            <section class="teaching-schoolyear-content-shell">
                <v-row class="w-100 ma-0" dense>
                    <v-col v-if="schoolyears.length > 0" cols="12" md="10" lg="7" xl="6" class="teaching-schoolyear-panel-col">
                        <ItsGridBox
                            variant="overview"
                            color="primary"
                            title="Schuljahr auswählen"
                            subtitle="Aktives Schuljahr für den Unterricht setzen"
                            icon="mdi-calendar-range"
                            :disabled="is_loading"
                            class="w-100">
                            <v-alert type="info" variant="tonal" class="mb-3">
                                Wählen Sie zuerst ein Schuljahr aus. Aktiviert wird es erst über den Button im Info-Block.
                            </v-alert>

                            <div class="schoolyear-card-grid">
                                <v-btn
                                    v-for="schoolyear in sortedSchoolyears"
                                    :key="schoolyear.id"
                                    class="schoolyear-select-btn"
                                    :color="isSelectedSchoolyear(schoolyear) ? 'primary' : 'secondary'"
                                    :variant="isSelectedSchoolyear(schoolyear) ? 'flat' : 'tonal'"
                                    :prepend-icon="isActiveSchoolyear(schoolyear) ? 'mdi-check-circle-outline' : 'mdi-calendar-blank-outline'"
                                    :loading="is_loading && pending_schoolyear_id === schoolyear.id"
                                    :disabled="is_loading"
                                    @click="selectSchoolyear(schoolyear)">
                                    <span class="schoolyear-select-btn-title">{{ schoolyear.name }}</span>
                                    <span class="schoolyear-select-btn-meta">{{ buildSchoolyearMeta(schoolyear) }}</span>
                                </v-btn>
                            </div>
                        </ItsGridBox>
                    </v-col>

                    <v-col v-if="selectedSchoolyear" cols="12" md="7" lg="4" xl="3" class="teaching-schoolyear-panel-col">
                        <ItsGridBox
                            variant="overview"
                            color="primary"
                            title="Ausgewähltes Schuljahr"
                            subtitle="Vorschau und Aktivierung"
                            icon="mdi-calendar-star"
                            class="w-100 schoolyear-active-box">
                            <div class="schoolyear-active-header">
                                <div class="schoolyear-active-headline">{{ selectedSchoolyear.name }}</div>
                                <v-chip
                                    size="x-small"
                                    :color="isSelectedSchoolyearActive ? 'success' : 'warning'"
                                    variant="tonal">
                                    {{ isSelectedSchoolyearActive ? 'Aktiv' : 'Nicht aktiv' }}
                                </v-chip>
                            </div>
                            <div class="schoolyear-active-actions">
                                <v-btn
                                    size="small"
                                    color="primary"
                                    variant="flat"
                                    prepend-icon="mdi-check-circle-outline"
                                    :disabled="is_loading || isSelectedSchoolyearActive"
                                    :loading="is_loading && pending_schoolyear_id === selectedSchoolyear.id"
                                    @click="activateSelectedSchoolyear">
                                    Aktivieren
                                </v-btn>
                                <v-btn
                                    icon="mdi-check-bold"
                                    size="x-small"
                                    color="primary"
                                    variant="tonal"
                                    :disabled="is_loading || isSelectedSchoolyearActive"
                                    :loading="is_loading && pending_schoolyear_id === selectedSchoolyear.id"
                                    @click="activateSelectedSchoolyear" />
                            </div>
                            <div class="schoolyear-active-grid">
                                <div class="schoolyear-active-row">
                                    <span class="schoolyear-active-label">Beginn</span>
                                    <v-chip size="x-small" color="secondary" variant="outlined" class="schoolyear-active-chip">
                                        {{ formatDate(selectedSchoolyear.from) || 'Nicht gesetzt' }}
                                    </v-chip>
                                </div>
                                <div class="schoolyear-active-row">
                                    <span class="schoolyear-active-label">2. Semester</span>
                                    <v-chip size="x-small" color="secondary" variant="outlined" class="schoolyear-active-chip">
                                        {{ formatDate(selectedSchoolyear.sem_2_start) || 'Nicht gesetzt' }}
                                    </v-chip>
                                </div>
                                <div class="schoolyear-active-row">
                                    <span class="schoolyear-active-label">Ende</span>
                                    <v-chip size="x-small" color="secondary" variant="outlined" class="schoolyear-active-chip">
                                        {{ formatDate(selectedSchoolyear.until) || 'Nicht gesetzt' }}
                                    </v-chip>
                                </div>
                            </div>
                        </ItsGridBox>
                    </v-col>

                    <v-col v-if="schoolyears.length === 0" cols="12">
                        <div class="teaching-schoolyear-empty">
                            Keine Schuljahre verfügbar.
                        </div>
                    </v-col>
                </v-row>
            </section>
        </section>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        await this.schoolyearStore.index()
        this.syncSelectedSchoolyear()
    },

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            is_loading: false,
            pending_schoolyear_id: null,
            selected_schoolyear_id: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
        activeSchoolyear() {
            return this.config?.selected_schoolyear || null
        },
        selectedSchoolyear() {
            const selectedId = this.selected_schoolyear_id
            const selectedFromList = (this.schoolyears || []).find((schoolyear) => schoolyear.id === selectedId)
            if (selectedFromList) {
                return selectedFromList
            }
            if (this.activeSchoolyear) {
                return this.activeSchoolyear
            }
            return (this.schoolyears || [])[0] || null
        },
        isSelectedSchoolyearActive() {
            const selectedId = this.selectedSchoolyear?.id
            const activeId = this.activeSchoolyear?.id
            if (!selectedId || !activeId) {
                return false
            }
            return String(selectedId) === String(activeId)
        },
        activeSchoolyearName() {
            return this.config?.selected_schoolyear?.name || 'Kein Schuljahr aktiv'
        },
        schoolyearCountLabel() {
            const count = Array.isArray(this.schoolyears) ? this.schoolyears.length : 0
            if (count === 0) {
                return 'Keine Schuljahre'
            }
            if (count === 1) {
                return '1 Schuljahr'
            }
            return `${count} Schuljahre`
        },
        selectedSchoolyearRange() {
            if (!this.config?.selected_schoolyear) {
                return ''
            }
            return this.buildSchoolyearMeta(this.config.selected_schoolyear)
        },
        sortedSchoolyears() {
            return [...(this.schoolyears || [])].sort((a, b) => (b.name || '').localeCompare(a.name || '', 'de'))
        },
    },

    watch: {
        schoolyears: {
            handler() {
                this.syncSelectedSchoolyear()
            },
            deep: true,
        },
        'config.selected_schoolyear.id'() {
            this.syncSelectedSchoolyear()
        },
    },

    methods: {
        formatDate(dateValue) {
            if (!dateValue) {
                return ''
            }
            const date = new Date(dateValue)
            if (Number.isNaN(date.getTime())) {
                return ''
            }
            return date.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' })
        },
        buildSchoolyearMeta(schoolyear) {
            const from = this.formatDate(schoolyear?.from)
            const sem2 = this.formatDate(schoolyear?.sem_2_start)
            const until = this.formatDate(schoolyear?.until)

            const sections = []
            if (from) {
                sections.push(`Start ${from}`)
            }
            if (sem2) {
                sections.push(`Sem 2 ${sem2}`)
            }
            if (until) {
                sections.push(`Ende ${until}`)
            }

            return sections.length > 0 ? sections.join(' · ') : 'Keine Datumsangaben'
        },
        isActiveSchoolyear(schoolyear) {
            return schoolyear?.id === this.config?.selected_schoolyear?.id
        },
        isSelectedSchoolyear(schoolyear) {
            return schoolyear?.id === this.selectedSchoolyear?.id
        },
        syncSelectedSchoolyear() {
            const selectedId = this.selected_schoolyear_id
            const hasSelected = (this.schoolyears || []).some((schoolyear) => schoolyear.id === selectedId)
            if (hasSelected) {
                return
            }
            this.selected_schoolyear_id = this.activeSchoolyear?.id || this.schoolyears?.[0]?.id || null
        },
        selectSchoolyear(schoolyear) {
            if (!schoolyear?.id || this.is_loading) {
                return
            }
            this.selected_schoolyear_id = schoolyear.id
        },
        async activateSelectedSchoolyear() {
            const schoolyear = this.selectedSchoolyear
            if (!schoolyear?.id || this.is_loading) {
                return
            }
            if (this.isActiveSchoolyear(schoolyear)) {
                return
            }

            this.is_loading = true
            this.pending_schoolyear_id = schoolyear.id

            try {
                await this.schoolyearStore.setActiveSchoolyear(schoolyear.id)
                await this.adminStore.loadConfig()
            } finally {
                this.pending_schoolyear_id = null
                this.is_loading = false
            }
        },
    },
}
</script>

<style scoped>
.teaching-schoolyear-page {
    width: 100%;
    display: grid;
    gap: 12px;
}

.teaching-schoolyear-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.09);
    background: rgba(255, 255, 255, 0.78);
    padding: 10px;
}

.teaching-schoolyear-content-shell {
    border-radius: 16px;
    border: 1px solid rgba(16, 38, 58, 0.08);
    background: rgba(255, 255, 255, 0.66);
    padding: 8px;
}

.schoolyear-card-grid {
    display: grid;
    gap: 8px;
}

.schoolyear-select-btn {
    height: auto !important;
    padding: 10px 12px !important;
    justify-content: flex-start !important;
    align-items: flex-start !important;
    text-transform: none;
    letter-spacing: 0;
}

.schoolyear-select-btn :deep(.v-btn__content) {
    width: 100%;
    display: grid;
    justify-items: start;
    gap: 2px;
    text-align: left;
}

.schoolyear-select-btn-title {
    font-size: 0.92rem;
    font-weight: 650;
}

.schoolyear-select-btn-meta {
    font-size: 0.75rem;
    opacity: 0.88;
}

.schoolyear-active-headline {
    font-size: 0.9rem;
    font-weight: 700;
}

.schoolyear-active-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 6px;
}

.schoolyear-active-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}

.schoolyear-active-grid {
    display: grid;
    gap: 6px;
}

.schoolyear-active-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.schoolyear-active-label {
    font-size: 0.76rem;
    color: rgba(16, 38, 58, 0.85);
    font-weight: 600;
}

.schoolyear-active-chip {
    font-size: 0.72rem;
}

.teaching-schoolyear-empty {
    border-radius: 14px;
    border: 1px dashed rgba(16, 38, 58, 0.14);
    background: rgba(255, 255, 255, 0.72);
    color: rgba(16, 38, 58, 0.9);
    padding: 14px;
    font-size: 0.88rem;
}
</style>
