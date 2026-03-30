<template>
    <v-container fluid class="aba-details-page ma-0 w-100 pa-2">
        <AdminSectionHero
            class="mb-3"
            eyebrow="Intern"
            title="ABA Details"
            :active-section="activeSection"
            :chips="headerChips"
            :show-current-user-chip="true"
            secondary-color="#1d4ed8"
            right-orb-color="#93c5fd" />

        <v-sheet rounded="xl" class="aba-details-nav mb-3">
            <div class="aba-details-nav__buttons">
                <v-btn
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="aba-details-nav__button"
                    @click="$router.push('/admin/aba')">
                    <v-icon size="18" icon="mdi-arrow-left" class="mr-2" />
                    <span class="aba-details-nav__button-copy">
                        <span class="aba-details-nav__button-title">Zurück</span>
                        <span class="aba-details-nav__button-meta">ABA-Überblick</span>
                    </span>
                </v-btn>
                <v-btn
                    v-for="item in navigationItems"
                    :key="item.key"
                    rounded="xl"
                    :color="activeTab === item.key ? 'primary' : 'secondary'"
                    :variant="activeTab === item.key ? 'flat' : 'tonal'"
                    class="aba-details-nav__button"
                    @click="activeTab = item.key">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="aba-details-nav__button-copy">
                        <span class="aba-details-nav__button-title">{{ item.label }}</span>
                        <span class="aba-details-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
            </div>
        </v-sheet>

        <!-- Loading -->
        <div class="basis-card" v-if="isLoading">
            <div class="basis-card__loading">
                <v-progress-circular indeterminate color="primary" />
            </div>
        </div>

        <v-alert v-else-if="error" type="warning" variant="tonal" rounded="lg" class="basis-card__alert">
            {{ error }}
        </v-alert>

        <v-alert v-else-if="!aba" type="info" variant="tonal" rounded="lg" class="basis-card__alert">
            Für diese ABA liegen aktuell keine Basisinformationen vor.
        </v-alert>

        <template v-else>
            <!-- Tab: Basisinformationen -->
            <div v-if="activeTab === 'basis'" class="basis-card">
                <div class="basis-card__glow" />

                <div class="basis-card__header">
                    <div class="basis-card__icon-wrap">
                        <v-icon size="26" color="white">mdi-file-document-outline</v-icon>
                    </div>
                    <div class="basis-card__header-text">
                        <div class="basis-card__title">{{ aba.title || 'Ohne Titel' }}</div>
                        <div class="basis-card__subtitle">
                            <v-icon size="14" class="mr-1">mdi-account-outline</v-icon>
                            {{ aba.student_name || '–' }}
                            <template v-if="aba.student_class">
                                <span class="basis-card__separator">&middot;</span>
                                {{ aba.student_class }}
                            </template>
                        </div>
                    </div>
                    <div class="basis-card__header-badge">
                        <v-chip size="small" :color="aba.evaluated_on ? 'success' : 'blue'" variant="tonal">
                            <v-icon start size="14">{{ aba.evaluated_on ? 'mdi-check-decagram-outline' : 'mdi-progress-clock' }}</v-icon>
                            {{ aba.evaluated_on ? 'Ausgewertet' : 'In Bearbeitung' }}
                        </v-chip>
                    </div>
                </div>

                <div class="basis-card__divider" />

                <div class="basis-card__fields">
                    <div class="basis-field">
                        <div class="basis-field__icon-wrap basis-field__icon-wrap--blue">
                            <v-icon size="16">mdi-calendar-month-outline</v-icon>
                        </div>
                        <div>
                            <div class="basis-field__label">Schuljahr</div>
                            <div class="basis-field__value">{{ aba.schoolyear_name || '–' }}</div>
                        </div>
                    </div>
                    <div class="basis-field">
                        <div class="basis-field__icon-wrap basis-field__icon-wrap--teal">
                            <v-icon size="16">mdi-calendar-plus-outline</v-icon>
                        </div>
                        <div>
                            <div class="basis-field__label">Erstellt am</div>
                            <div class="basis-field__value">{{ formatDateTime(aba.created_at) }}</div>
                        </div>
                    </div>
                    <div class="basis-field">
                        <div class="basis-field__icon-wrap" :class="aba.evaluated_on ? 'basis-field__icon-wrap--green' : 'basis-field__icon-wrap--grey'">
                            <v-icon size="16">mdi-calendar-check-outline</v-icon>
                        </div>
                        <div>
                            <div class="basis-field__label">Ausgewertet am</div>
                            <div class="basis-field__value">{{ formatDate(aba.evaluated_on) || 'Noch nicht ausgewertet' }}</div>
                        </div>
                    </div>
                </div>

                <div class="basis-card__docs">
                    <div class="basis-doc basis-doc--main">
                        <div class="basis-doc__icon-wrap">
                            <v-icon size="18" :color="aba.main_attachment ? '#3b82f6' : '#64748b'">{{ aba.main_attachment ? 'mdi-file-document' : 'mdi-file-hidden' }}</v-icon>
                        </div>
                        <div class="basis-doc__body">
                            <div class="basis-doc__label">Hauptdokument</div>
                            <div class="basis-doc__name" :class="{ 'basis-doc__name--missing': !aba.main_attachment }">
                                {{ aba.main_attachment?.original_name || 'Nicht vorhanden' }}
                            </div>
                        </div>
                    </div>
                    <div class="basis-doc" v-if="(aba.additional_attachments_count ?? 0) > 0">
                        <div class="basis-doc__icon-wrap">
                            <v-icon size="18" color="#8b5cf6">mdi-paperclip</v-icon>
                        </div>
                        <div class="basis-doc__body">
                            <div class="basis-doc__label">Weitere Dokumente</div>
                            <div class="basis-doc__name">{{ aba.additional_attachments_count }} Anhang{{ aba.additional_attachments_count > 1 ? 'e' : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Extraktion -->
            <div v-if="activeTab === 'extraction'" class="basis-card">
                <div class="basis-card__glow" />

                <div class="basis-card__header">
                    <div class="basis-card__icon-wrap basis-card__icon-wrap--extraction">
                        <v-icon size="26" color="white">mdi-text-box-search-outline</v-icon>
                    </div>
                    <div class="basis-card__header-text">
                        <div class="basis-card__title">Extraktion</div>
                        <div class="basis-card__subtitle">
                            Dokumentinhalte aus dem Hauptdokument extrahieren
                        </div>
                    </div>
                    <div class="basis-card__header-badge d-flex align-center ga-2 flex-wrap">
                        <v-chip v-if="currentExtraction" size="small" :color="extractionStatusColor" variant="tonal">
                            <v-icon start size="14">{{ extractionStatusIcon }}</v-icon>
                            {{ extractionStatusLabel }}
                        </v-chip>
                        <v-btn
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-play-circle-outline"
                            :loading="isExtractionSubmitting"
                            :disabled="isExtractionBusy"
                            @click="startExtraction">
                            Extraktion starten
                        </v-btn>
                    </div>
                </div>

                <div class="basis-card__divider" />

                <div class="extraction-content">
                    <v-alert
                        v-if="extractionLoadError"
                        type="warning"
                        variant="tonal"
                        density="comfortable"
                        rounded="lg"
                        class="mb-4">
                        {{ extractionLoadError }}
                    </v-alert>

                    <div class="extraction-note mb-4">
                        <v-icon size="16" color="#60a5fa" class="mr-2">mdi-file-word-outline</v-icon>
                        DOCX-Hauptdokumente werden gegen das ABA-Regelwerk geprüft und in strukturierte Bereiche zerlegt.
                    </div>

                    <div v-if="currentExtraction" class="extraction-last-run">
                        <div class="extraction-last-run__header">
                            <v-icon size="16" color="#60a5fa" class="mr-2">mdi-history</v-icon>
                            <span class="extraction-last-run__title">Letzte Extraktion</span>
                        </div>

                        <div class="extraction-fields">
                            <div class="extraction-field">
                                <div class="basis-field__icon-wrap basis-field__icon-wrap--blue">
                                    <v-icon size="16">mdi-clock-outline</v-icon>
                                </div>
                                <div>
                                    <div class="basis-field__label">Zeitpunkt</div>
                                    <div class="basis-field__value">{{ formatDateTime(currentExtraction.completed_at || currentExtraction.started_at) }}</div>
                                </div>
                            </div>
                            <div class="extraction-field">
                                <div class="basis-field__icon-wrap basis-field__icon-wrap--teal">
                                    <v-icon size="16">mdi-file-outline</v-icon>
                                </div>
                                <div>
                                    <div class="basis-field__label">Quelldatei</div>
                                    <div class="basis-field__value">{{ currentExtraction.source_original_name || currentExtraction.document?.source_original_name || '–' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="extraction-stats" v-if="currentExtraction.status === 'completed'">
                            <div class="extraction-stat">
                                <div class="extraction-stat__count">{{ foundRequiredCount }}</div>
                                <div class="extraction-stat__label">Pflicht gefunden</div>
                            </div>
                            <div class="extraction-stat">
                                <div class="extraction-stat__count">{{ missingRequiredCount }}</div>
                                <div class="extraction-stat__label">Pflicht fehlt</div>
                            </div>
                            <div class="extraction-stat">
                                <div class="extraction-stat__count">{{ foundOptionalCount }}</div>
                                <div class="extraction-stat__label">Optional gefunden</div>
                            </div>
                            <div class="extraction-stat">
                                <div class="extraction-stat__count">{{ uncertainMatchCount }}</div>
                                <div class="extraction-stat__label">Unsicher</div>
                            </div>
                        </div>

                        <v-alert v-if="currentExtraction.status === 'running' || currentExtraction.status === 'started'" type="info" variant="tonal" density="compact" rounded="lg" class="mt-4 text-body-2">
                            Extraktion läuft. Die Ergebnisse werden automatisch aktualisiert.
                        </v-alert>

                        <v-alert v-if="currentExtraction.status === 'failed'" type="error" variant="tonal" density="compact" rounded="lg" class="mt-4 text-body-2">
                            {{ currentExtraction.error_message || currentExtraction.status_message || 'Extraktion fehlgeschlagen.' }}
                        </v-alert>

                        <v-alert
                            v-for="warning in extractionWarnings"
                            :key="warning"
                            type="warning"
                            variant="tonal"
                            density="compact"
                            rounded="lg"
                            class="mt-3 text-body-2">
                            {{ warning }}
                        </v-alert>

                        <div v-if="currentExtraction.status === 'completed' && extractionSections.length" class="extraction-sections mt-4">
                            <div v-for="section in extractionSections" :key="section.key" class="extraction-section-card">
                                <div class="extraction-section-card__header">
                                    <div>
                                        <div class="extraction-section-card__title">{{ section.label }}</div>
                                        <div class="extraction-section-card__meta">
                                            {{ section.required ? 'Pflichtbereich' : 'Optional' }}
                                            <template v-if="section.matched_heading">
                                                <span class="basis-card__separator">&middot;</span>
                                                {{ section.matched_heading }}
                                            </template>
                                        </div>
                                    </div>

                                    <v-chip
                                        size="small"
                                        :color="sectionChipColor(section)"
                                        variant="tonal">
                                        {{ sectionChipLabel(section) }}
                                    </v-chip>
                                </div>

                                <div v-if="section.key === 'title_page' && section.title_page" class="title-page-details">
                                    <div class="title-page-details__hero">
                                        <div class="title-page-details__hero-label">Titel</div>
                                        <div class="title-page-details__hero-title">{{ section.title_page.title || '–' }}</div>
                                        <div v-if="section.title_page.subtitle" class="title-page-details__hero-subtitle">
                                            {{ section.title_page.subtitle }}
                                        </div>
                                    </div>

                                    <div class="title-page-details__grid">
                                        <div
                                            v-for="item in titlePageDetailItems(section)"
                                            :key="`${section.key}-${item.key}`"
                                            class="title-page-details__item">
                                            <div class="title-page-details__item-label">{{ item.label }}</div>
                                            <div class="title-page-details__item-value">{{ item.value }}</div>
                                        </div>
                                    </div>

                                    <div v-if="titlePageOtherThings(section).length" class="title-page-details__extras">
                                        <div class="title-page-details__extras-label">Weitere Angaben vom Titelblatt</div>
                                        <div class="title-page-details__extras-list">
                                            <v-chip
                                                v-for="item in titlePageOtherThings(section)"
                                                :key="`${section.key}-${item.label}-${item.value}`"
                                                size="small"
                                                color="secondary"
                                                variant="tonal">
                                                {{ item.label }}: {{ item.value }}
                                            </v-chip>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="section.preview_text && section.key !== 'title_page'" class="extraction-section-card__preview">
                                    {{ section.preview_text }}
                                </div>

                                <div v-if="section.warnings?.length" class="extraction-section-card__warnings">
                                    <v-chip
                                        v-for="warning in section.warnings"
                                        :key="`${section.key}-${warning}`"
                                        size="x-small"
                                        color="warning"
                                        variant="tonal">
                                        {{ warning }}
                                    </v-chip>
                                </div>
                            </div>
                        </div>

                        <div v-if="currentExtraction.status === 'completed' && !extractionSections.length" class="extraction-empty mt-4">
                            <v-icon size="36" color="#64748b" class="mb-2">mdi-text-box-search-outline</v-icon>
                            <div class="extraction-empty__title">Keine ABA-Bereiche erkannt</div>
                            <div class="extraction-empty__desc">Der Text wurde gelesen, aber keinem Bereich des ABA-Regelwerks sicher zugeordnet.</div>
                        </div>

                        <div v-if="currentExtraction.status === 'completed'" class="extraction-footnote mt-4">
                            Nicht zugeordnete Blöcke: {{ currentExtraction.unmatched_blocks_count ?? 0 }}
                        </div>
                    </div>

                    <div v-else class="extraction-empty">
                        <v-icon size="40" color="#475569" class="mb-3">mdi-text-box-remove-outline</v-icon>
                        <div class="extraction-empty__title">Noch keine Extraktion durchgeführt</div>
                        <div class="extraction-empty__desc">Die Inhalte des Hauptdokuments wurden noch nicht extrahiert.</div>
                    </div>
                </div>
            </div>
        </template>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'

export default {
    components: { AdminSectionHero },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    data() {
        return {
            adminStore: null,
            aba: null,
            extractionResult: null,
            isLoading: false,
            isExtractionLoading: false,
            isExtractionSubmitting: false,
            error: '',
            extractionLoadError: '',
            extractionPollTimer: null,
            activeTab: 'basis',
        }
    },

    mounted() {
        this.loadAba()
    },

    beforeUnmount() {
        this.clearExtractionPoll()
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        abaId() {
            return String(this.$route?.params?.abaId || '').trim() || '-'
        },
        selectedSchoolLabel() {
            return this.config?.selected_school?.long_name || this.config?.selected_school?.name || 'Keine Schule gewählt'
        },
        selectedRoleLabel() {
            const roles = Array.isArray(this.config?.roles) ? this.config.roles : []
            if (!roles.length) {
                return 'Keine Rolle'
            }
            return roles.slice(0, 2).join(' / ')
        },
        headerChips() {
            return [
                { key: 'school', text: this.selectedSchoolLabel, icon: 'mdi-domain' },
            ]
        },
        activeSection() {
            const sections = {
                basis: { label: 'Basisinformationen', icon: 'mdi-file-document-outline', note: `ABA #${this.abaId}` },
                extraction: { label: 'Extraktion', icon: 'mdi-text-box-search-outline', note: `ABA #${this.abaId}` },
            }
            return sections[this.activeTab] || sections.basis
        },
        navigationItems() {
            return [
                { key: 'basis', label: 'Basis', meta: 'Stammdaten', icon: 'mdi-file-document-outline' },
                { key: 'extraction', label: 'Extraktion', meta: this.extractionNavMeta, icon: 'mdi-text-box-search-outline' },
            ]
        },
        currentExtraction() {
            return this.extractionResult || this.aba?.latest_extraction || null
        },
        latestExtraction() {
            return this.aba?.latest_extraction || null
        },
        extractionNavMeta() {
            if (!this.currentExtraction) return 'Nicht durchgeführt'
            if (this.currentExtraction.status === 'completed') return this.formatShortDateTime(this.currentExtraction.completed_at)
            if (this.currentExtraction.status === 'failed') return 'Fehlgeschlagen'
            if (this.currentExtraction.status === 'running') return 'Läuft...'
            return 'Gestartet'
        },
        extractionStatusColor() {
            const map = { completed: 'success', failed: 'error', running: 'blue', started: 'blue', aborted: 'warning' }
            return map[this.currentExtraction?.status] || 'grey'
        },
        extractionStatusIcon() {
            const map = { completed: 'mdi-check-circle-outline', failed: 'mdi-alert-circle-outline', running: 'mdi-progress-clock', started: 'mdi-play-circle-outline', aborted: 'mdi-stop-circle-outline' }
            return map[this.currentExtraction?.status] || 'mdi-circle-outline'
        },
        extractionStatusLabel() {
            const map = { completed: 'Abgeschlossen', failed: 'Fehlgeschlagen', running: 'Läuft', started: 'Gestartet', aborted: 'Abgebrochen' }
            return map[this.currentExtraction?.status] || this.currentExtraction?.status
        },
        extractionSections() {
            const sections = this.currentExtraction?.sections
            return Array.isArray(sections) ? sections : []
        },
        extractionWarnings() {
            const warnings = this.currentExtraction?.warnings
            return Array.isArray(warnings) ? warnings : []
        },
        foundRequiredCount() {
            return this.extractionSections.filter((section) => section.required && section.found).length
        },
        missingRequiredCount() {
            return this.extractionSections.filter((section) => section.required && !section.found).length
        },
        foundOptionalCount() {
            return this.extractionSections.filter((section) => !section.required && section.found).length
        },
        uncertainMatchCount() {
            return this.extractionSections.filter((section) => section.uncertain).length
        },
        isExtractionBusy() {
            return this.isExtractionSubmitting || this.isExtractionLoading || this.isRunningExtraction
        },
        isRunningExtraction() {
            return ['started', 'running'].includes(this.currentExtraction?.status)
        },
    },

    methods: {
        formatDate(value) {
            if (!value) return ''
            const d = new Date(value)
            if (isNaN(d.getTime())) return value
            return d.toLocaleDateString('de-AT', { year: 'numeric', month: 'long', day: 'numeric' })
        },

        formatDateTime(value) {
            if (!value) return '–'
            const d = new Date(value)
            if (isNaN(d.getTime())) return value
            const date = d.toLocaleDateString('de-AT', { year: 'numeric', month: 'long', day: 'numeric' })
            const time = d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' })
            return `${date}, ${time}`
        },

        formatShortDateTime(value) {
            if (!value) return '–'
            const d = new Date(value)
            if (isNaN(d.getTime())) return value
            return d.toLocaleDateString('de-AT', { day: '2-digit', month: '2-digit', year: '2-digit' }) + ' ' + d.toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit' })
        },

        formatNumber(value) {
            if (typeof value !== 'number') return '–'
            return value.toLocaleString('de-AT')
        },
        isTerminalExtractionStatus(status) {
            return ['completed', 'failed', 'aborted'].includes(status)
        },
        clearExtractionPoll() {
            if (this.extractionPollTimer) {
                clearTimeout(this.extractionPollTimer)
                this.extractionPollTimer = null
            }
        },
        syncAbaLatestExtraction() {
            if (!this.aba || !this.extractionResult) {
                return
            }

            this.aba = {
                ...this.aba,
                latest_extraction: {
                    ...(this.aba.latest_extraction || {}),
                    ...this.extractionResult,
                },
            }
        },
        scheduleExtractionPoll() {
            this.clearExtractionPoll()
            if (!this.isRunningExtraction) {
                return
            }

            this.extractionPollTimer = setTimeout(() => {
                this.loadExtraction(true)
            }, 2000)
        },
        async loadExtraction(silent = false) {
            if (!silent) {
                this.isExtractionLoading = true
            }

            this.extractionLoadError = ''

            try {
                const response = await axios.get(`/api/admin/abas/${this.abaId}/extraction`)
                this.extractionResult = response?.data?.data || null
                this.syncAbaLatestExtraction()
                this.scheduleExtractionPoll()
            } catch (error) {
                this.extractionResult = null
                this.clearExtractionPoll()
                this.extractionLoadError = error?.response?.data?.message || 'Extraktionsdaten konnten nicht geladen werden.'
            } finally {
                if (!silent) {
                    this.isExtractionLoading = false
                }
            }
        },
        async startExtraction() {
            this.isExtractionSubmitting = true
            this.extractionLoadError = ''

            try {
                const response = await axios.post(`/api/admin/abas/${this.abaId}/extraction`)
                this.extractionResult = response?.data?.data || null
                this.syncAbaLatestExtraction()
                this.scheduleExtractionPoll()

                const successMessage = this.isRunningExtraction
                    ? 'Extraktion wurde gestartet.'
                    : this.currentExtraction?.status === 'completed'
                        ? 'Extraktion abgeschlossen.'
                        : this.currentExtraction?.status === 'failed'
                            ? 'Extraktion ist fehlgeschlagen.'
                        : 'Extraktion wurde aktualisiert.'

                useNotificationStore().notify({
                    message: successMessage,
                    type: this.currentExtraction?.status === 'failed' ? 'error' : 'success',
                    timeout: 3000,
                })
            } catch (error) {
                const message = error?.response?.data?.message || 'Extraktion konnte nicht gestartet werden.'
                this.extractionLoadError = message
                useNotificationStore().notify({
                    status: error?.response?.status || 500,
                    message,
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isExtractionSubmitting = false
            }
        },
        sectionChipColor(section) {
            if (section?.found) return 'success'
            if (section?.uncertain) return 'warning'
            return section?.required ? 'error' : 'grey'
        },
        sectionChipLabel(section) {
            if (section?.found) return 'Gefunden'
            if (section?.uncertain) return 'Unsicher'
            return section?.required ? 'Fehlt' : 'Nicht gefunden'
        },
        titlePageDetailItems(section) {
            const titlePage = section?.title_page || {}

            return [
                { key: 'author', label: 'Verfasser', value: titlePage.author || '–' },
                { key: 'class', label: 'Klasse', value: titlePage.class || '–' },
                { key: 'advisor', label: 'Betreuer', value: titlePage.advisor || '–' },
                { key: 'date', label: 'Datum', value: titlePage.date || '–' },
                { key: 'images', label: 'Gefundene Bilder', value: this.formatFoundImages(titlePage.found_images_count) },
                { key: 'page', label: 'Seitenzahl', value: this.titlePagePageLabel(titlePage) },
            ]
        },
        titlePageOtherThings(section) {
            const items = Array.isArray(section?.title_page?.other_things) ? section.title_page.other_things : []

            return items.filter((item) => String(item?.label || '').trim() !== '' && String(item?.value || '').trim() !== '')
        },
        formatFoundImages(value) {
            if (typeof value !== 'number' || Number.isNaN(value)) {
                return '–'
            }

            return `${value}`
        },
        titlePagePageLabel(titlePage) {
            const start = Number(titlePage?.page_range?.start || titlePage?.page_number || 0)
            const end = Number(titlePage?.page_range?.end || titlePage?.page_number || 0)

            if (!start && !end) {
                return '–'
            }

            if (start && end && start !== end) {
                return `${start}–${end}`
            }

            return `${start || end}`
        },

        async loadAba() {
            this.isLoading = true
            this.error = ''

            try {
                const response = await axios.get(`/api/admin/abas/${this.abaId}`)
                this.aba = response?.data?.data || response?.data || null
                await this.loadExtraction(true)
            } catch (error) {
                this.aba = null
                this.extractionResult = null
                this.clearExtractionPoll()
                this.error = error?.response?.data?.message || 'ABA-Details konnten nicht geladen werden.'
            } finally {
                this.isLoading = false
            }
        },
    },
}
</script>

<style scoped>
.aba-details-page {
    background: #0f172a;
    min-height: 100vh;
}

/* Navigation */
.aba-details-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
}

.aba-details-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.aba-details-nav__button {
    min-height: 54px;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.aba-details-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.aba-details-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.aba-details-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

/* Basis Card */
.basis-card {
    position: relative;
    max-width: 720px;
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.14);
    background:
        radial-gradient(ellipse at top left, rgba(59, 130, 246, 0.08), transparent 55%),
        radial-gradient(ellipse at bottom right, rgba(139, 92, 246, 0.06), transparent 55%),
        rgba(30, 41, 59, 0.7);
    backdrop-filter: blur(12px);
    overflow: hidden;
    animation: basis-card-in 0.3s ease-out;
}

.basis-card__loading {
    display: flex;
    justify-content: center;
    padding: 48px;
}

.basis-card__alert {
    max-width: 720px;
}

.basis-card__glow {
    position: absolute;
    top: -1px;
    left: 40px;
    right: 40px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.5), rgba(139, 92, 246, 0.4), transparent);
}

/* Header */
.basis-card__header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 24px 28px 20px;
}

.basis-card__icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
    flex-shrink: 0;
}

.basis-card__icon-wrap--extraction {
    background: linear-gradient(135deg, #0d9488, #0891b2);
    box-shadow: 0 4px 14px rgba(13, 148, 136, 0.3);
}

.basis-card__header-text {
    flex: 1;
    min-width: 0;
}

.basis-card__title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #f1f5f9;
    line-height: 1.25;
    margin-bottom: 4px;
}

.basis-card__subtitle {
    font-size: 0.84rem;
    color: #94a3b8;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.basis-card__separator {
    margin: 0 6px;
    opacity: 0.4;
}

.basis-card__header-badge {
    flex-shrink: 0;
    padding-top: 4px;
}

/* Divider */
.basis-card__divider {
    height: 1px;
    margin: 0 28px;
    background: linear-gradient(90deg, transparent, rgba(148, 163, 184, 0.15), transparent);
}

/* Fields */
.basis-card__fields {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    padding: 6px 14px;
}

.basis-field {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 14px;
    border-radius: 12px;
    transition: background 0.15s ease;
}

.basis-field:hover {
    background: rgba(148, 163, 184, 0.06);
}

.basis-field__icon-wrap {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.basis-field__icon-wrap--blue {
    background: rgba(59, 130, 246, 0.12);
    color: #60a5fa;
}

.basis-field__icon-wrap--teal {
    background: rgba(20, 184, 166, 0.12);
    color: #2dd4bf;
}

.basis-field__icon-wrap--green {
    background: rgba(34, 197, 94, 0.12);
    color: #4ade80;
}

.basis-field__icon-wrap--grey {
    background: rgba(148, 163, 184, 0.1);
    color: #64748b;
}

.basis-field__label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 2px;
}

.basis-field__value {
    font-size: 0.88rem;
    font-weight: 650;
    color: #e2e8f0;
    line-height: 1.3;
}

/* Documents */
.basis-card__docs {
    margin: 0 28px;
    padding: 16px 0 20px;
    border-top: 1px solid rgba(148, 163, 184, 0.08);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.basis-doc {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.1);
    background: rgba(15, 23, 42, 0.4);
    transition: border-color 0.15s ease, background 0.15s ease;
}

.basis-doc:hover {
    border-color: rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.6);
}

.basis-doc__icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(148, 163, 184, 0.08);
    flex-shrink: 0;
}

.basis-doc__body {
    flex: 1;
    min-width: 0;
}

.basis-doc__label {
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 1px;
}

.basis-doc__name {
    font-size: 0.84rem;
    font-weight: 600;
    color: #cbd5e1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.basis-doc__name--missing {
    color: #64748b;
    font-style: italic;
}

/* Extraction Tab */
.extraction-content {
    padding: 20px 28px 24px;
}

.extraction-note {
    display: flex;
    align-items: center;
    border: 1px solid rgba(96, 165, 250, 0.18);
    border-radius: 14px;
    padding: 12px 14px;
    color: #cbd5e1;
    background: rgba(15, 23, 42, 0.35);
    font-size: 0.84rem;
}

.extraction-last-run__header {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.extraction-last-run__title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.extraction-fields {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    margin-bottom: 16px;
}

.extraction-field {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
}

.extraction-stats {
    display: flex;
    gap: 12px;
}

.extraction-stat {
    flex: 1;
    text-align: center;
    padding: 16px 12px;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.1);
    background: rgba(15, 23, 42, 0.4);
}

.extraction-stat__count {
    font-size: 1.4rem;
    font-weight: 800;
    color: #e2e8f0;
    line-height: 1;
    margin-bottom: 4px;
}

.extraction-stat__label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.extraction-sections {
    display: grid;
    gap: 12px;
}

.extraction-section-card {
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 14px;
    padding: 14px;
    background: rgba(15, 23, 42, 0.38);
}

.extraction-section-card__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.extraction-section-card__title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #f1f5f9;
}

.extraction-section-card__meta {
    font-size: 0.76rem;
    color: #94a3b8;
    margin-top: 2px;
}

.extraction-section-card__preview {
    margin-top: 10px;
    font-size: 0.84rem;
    line-height: 1.55;
    color: #cbd5e1;
}

.title-page-details {
    display: grid;
    gap: 14px;
    margin-top: 12px;
}

.title-page-details__hero {
    border: 1px solid rgba(96, 165, 250, 0.14);
    border-radius: 14px;
    padding: 14px;
    background: rgba(30, 41, 59, 0.45);
}

.title-page-details__hero-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #60a5fa;
    margin-bottom: 6px;
}

.title-page-details__hero-title {
    font-size: 0.98rem;
    font-weight: 800;
    line-height: 1.35;
    color: #f8fafc;
}

.title-page-details__hero-subtitle {
    margin-top: 6px;
    font-size: 0.82rem;
    line-height: 1.45;
    color: #cbd5e1;
}

.title-page-details__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.title-page-details__item {
    border: 1px solid rgba(148, 163, 184, 0.1);
    border-radius: 12px;
    padding: 10px 12px;
    background: rgba(15, 23, 42, 0.35);
}

.title-page-details__item-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.35px;
    color: #94a3b8;
    margin-bottom: 3px;
}

.title-page-details__item-value {
    font-size: 0.84rem;
    font-weight: 650;
    line-height: 1.4;
    color: #f1f5f9;
}

.title-page-details__extras {
    display: grid;
    gap: 8px;
}

.title-page-details__extras-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.35px;
    color: #94a3b8;
}

.title-page-details__extras-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.extraction-section-card__warnings {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
}

.extraction-footnote {
    font-size: 0.78rem;
    color: #94a3b8;
}

.extraction-empty {
    text-align: center;
    padding: 32px 16px;
}

.extraction-empty__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #94a3b8;
    margin-bottom: 4px;
}

.extraction-empty__desc {
    font-size: 0.82rem;
    color: #64748b;
}

@keyframes basis-card-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 680px) {
    .basis-card__fields,
    .extraction-fields {
        grid-template-columns: 1fr;
    }

    .title-page-details__grid {
        grid-template-columns: 1fr;
    }

    .basis-card__header {
        flex-wrap: wrap;
    }

    .basis-card__header-badge {
        width: 100%;
    }

    .extraction-stats {
        flex-direction: column;
    }
}
</style>
