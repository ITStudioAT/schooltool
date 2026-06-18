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
                    @click="selectTab(item.key)">
                    <v-icon size="18" :icon="item.icon" class="mr-2" />
                    <span class="aba-details-nav__button-copy">
                        <span class="aba-details-nav__button-title">{{ item.label }}</span>
                        <span class="aba-details-nav__button-meta">{{ item.meta }}</span>
                    </span>
                </v-btn>
                <v-btn
                    v-if="nextAba"
                    rounded="xl"
                    color="secondary"
                    variant="tonal"
                    class="aba-details-nav__button"
                    @click="goToNextAba">
                    <v-icon size="18" icon="mdi-chevron-right-circle-outline" class="mr-2" />
                    <span class="aba-details-nav__button-copy">
                        <span class="aba-details-nav__button-title">Nächste ABA</span>
                        <span class="aba-details-nav__button-meta">{{ nextAba.title || `ABA #${nextAba.id}` }}</span>
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
                        <div class="basis-field__icon-wrap" :class="latestExtraction ? 'basis-field__icon-wrap--cyan' : 'basis-field__icon-wrap--grey'">
                            <v-icon size="16">mdi-text-box-search-outline</v-icon>
                        </div>
                        <div>
                            <div class="basis-field__label">Extraktion am</div>
                            <div class="basis-field__value">{{ formatDateTime(latestExtraction?.completed_at || latestExtraction?.started_at) || 'Noch nicht extrahiert' }}</div>
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
                    <div class="basis-card__header-badge extraction-header-actions">
                        <v-switch
                            v-model="overwriteExistingExtractionFields"
                            density="compact"
                            hide-details
                            inset
                            color="primary"
                            class="extraction-overwrite-switch"
                            :disabled="isAnyExtractionBusy">
                            <template #label>
                                <span class="extraction-overwrite-switch__label">Felder überschreiben</span>
                            </template>
                        </v-switch>
                        <v-btn
                            variant="flat"
                            color="primary"
                            prepend-icon="mdi-play-circle-outline"
                            :loading="isExtractionSubmitting"
                            :disabled="isAnyExtractionBusy"
                            @click="startExtraction">
                            Extraktion starten
                        </v-btn>
                        <v-btn
                            variant="tonal"
                            color="secondary"
                            prepend-icon="mdi-flask-outline"
                            :loading="isParselExtractionSubmitting"
                            :disabled="isAnyExtractionBusy"
                            @click="startParselExtraction">
                            Extraktion 2
                        </v-btn>
                        <v-chip
                            v-if="currentExtraction"
                            size="small"
                            variant="tonal"
                            :color="extractionStatusColor"
                            :prepend-icon="extractionStatusIcon">
                            Status: {{ extractionStatusLabel }}
                        </v-chip>
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
                        <div class="extraction-note__content">
                            <div class="extraction-note__title">{{ savedAbaTitle }}</div>
                            <div class="extraction-note__meta">Verfasser:in: {{ savedAbaStudentName }}</div>
                        </div>
                    </div>

                    <div v-if="currentExtraction" class="extraction-last-run">
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

                        <div v-if="comparisonSummary" class="extraction-comparison mt-4">
                            <div class="extraction-comparison__header">
                                <div>
                                    <div class="extraction-comparison__title">Vergleich</div>
                                    <div class="extraction-comparison__subtitle">
                                        Konventionelle Extraktion gegen Parsel
                                    </div>
                                </div>
                                <v-chip
                                    size="small"
                                    variant="tonal"
                                    :color="comparisonSummary.ready ? 'success' : 'warning'"
                                    :prepend-icon="comparisonSummary.ready ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'">
                                    {{ comparisonSummary.ready ? 'Bereit' : 'UnvollstÃ¤ndig' }}
                                </v-chip>
                            </div>
                            <div class="extraction-stats extraction-stats--comparison">
                                <div class="extraction-stat">
                                    <div class="extraction-stat__count">{{ comparisonSummary.conventional?.found_required_count ?? 0 }}</div>
                                    <div class="extraction-stat__label">Pflicht Alt</div>
                                </div>
                                <div class="extraction-stat">
                                    <div class="extraction-stat__count">{{ comparisonSummary.parsel?.found_required_count ?? 0 }}</div>
                                    <div class="extraction-stat__label">Pflicht Parsel</div>
                                </div>
                                <div class="extraction-stat">
                                    <div class="extraction-stat__count">{{ formatSignedNumber(comparisonSummary.delta?.required_found) }}</div>
                                    <div class="extraction-stat__label">Delta Pflicht</div>
                                </div>
                                <div class="extraction-stat">
                                    <div class="extraction-stat__count">{{ formatSignedNumber(comparisonSummary.delta?.text_length) }}</div>
                                    <div class="extraction-stat__label">Delta Text</div>
                                </div>
                            </div>
                            <v-alert
                                v-for="warning in comparisonWarnings"
                                :key="warning"
                                type="warning"
                                variant="tonal"
                                density="compact"
                                rounded="lg"
                                class="mt-3 text-body-2">
                                {{ warning }}
                            </v-alert>
                            <div v-if="comparisonSections.length" class="extraction-comparison__rows">
                                <div
                                    v-for="section in comparisonSections"
                                    :key="section.key"
                                    class="extraction-comparison__row">
                                    <div>
                                        <div class="extraction-comparison__row-title">{{ section.label }}</div>
                                        <div class="extraction-comparison__row-meta">{{ section.key }}</div>
                                    </div>
                                    <div class="extraction-comparison__chips">
                                        <v-chip size="x-small" :color="section.conventional_found ? 'success' : 'grey'" variant="tonal">
                                            Alt
                                        </v-chip>
                                        <v-chip size="x-small" :color="section.parsel_found ? 'success' : 'grey'" variant="tonal">
                                            Parsel
                                        </v-chip>
                                    </div>
                                </div>
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

                                    <div class="extraction-section-card__chips">
                                        <v-chip
                                            size="small"
                                            :color="sectionChipColor(section)"
                                            variant="tonal">
                                            {{ sectionChipLabel(section) }}
                                        </v-chip>
                                    </div>
                                </div>

                                <div v-if="section.key === 'title_page' && section.title_page" class="title-page-details">
                                    <div class="title-page-details__hero">
                                        <div class="title-page-details__hero-header">
                                            <div class="title-page-details__hero-label">Titel</div>
                                            <v-btn
                                                icon="mdi-pencil"
                                                size="x-small"
                                                color="primary"
                                                variant="tonal"
                                                @click="openTitlePageEditDialog('title', titlePageHeroTitle(section))" />
                                        </div>
                                        <div class="title-page-details__hero-title">{{ titlePageHeroTitle(section) || '–' }}</div>
                                        <div class="title-page-details__hero-subtitle-block">
                                            <div class="title-page-details__hero-subtitle-header">
                                                <div class="title-page-details__hero-subtitle-label">Untertitel</div>
                                                <v-btn
                                                    icon="mdi-pencil"
                                                    size="x-small"
                                                    color="primary"
                                                    variant="tonal"
                                                    @click="openTitlePageEditDialog('subtitle', titlePageHeroSubtitle(section))" />
                                            </div>
                                            <div class="title-page-details__hero-subtitle">
                                                {{ titlePageHeroSubtitle(section) || '–' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="title-page-details__grid">
                                        <div
                                            v-for="item in titlePageDetailItems(section)"
                                            :key="`${section.key}-${item.key}`"
                                            class="title-page-details__item">
                                            <div class="title-page-details__item-header">
                                                <div class="title-page-details__item-label">{{ item.label }}</div>
                                                <v-btn
                                                    v-if="item.editable"
                                                    icon="mdi-pencil"
                                                    size="x-small"
                                                    color="primary"
                                                    variant="tonal"
                                                    @click="openTitlePageEditDialog(item.editKey, item.editValue)" />
                                            </div>
                                            <div v-if="item.value !== null" class="title-page-details__item-value">{{ item.value }}</div>
                                            <div v-if="item.lines?.length" class="title-page-details__item-lines">
                                                <div
                                                    v-for="line in item.lines"
                                                    :key="`${section.key}-${item.key}-${line.label}`"
                                                    class="title-page-details__item-line">
                                                    <span class="title-page-details__item-line-label">{{ line.label }}</span>
                                                    <span class="title-page-details__item-line-value">{{ line.value }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="titlePageImages(section).length" class="title-page-details__images">
                                        <div class="title-page-details__extras-label">Erkannte Bilder</div>
                                        <div class="title-page-details__image-grid">
                                            <div
                                                v-for="image in titlePageImages(section)"
                                                :key="`${section.key}-${image.asset_index}-${image.url}`"
                                                class="title-page-details__image-card">
                                                <div
                                                    class="title-page-details__image-surface"
                                                    :style="titlePageImageSurfaceStyle(image)">
                                                    <img
                                                        :src="image.url"
                                                        :alt="image.alt_text || image.description || 'Titelblatt-Bild'"
                                                        class="title-page-details__image"
                                                        loading="lazy"
                                                        @load="handleTitlePageImageLoad(image, $event)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div
                                    v-if="section.key === 'table_of_contents' && tableOfContentsEntries(section).length"
                                    class="toc-details">
                                    <div
                                        v-for="entry in tableOfContentsEntries(section)"
                                        :key="`${section.key}-${entry}`"
                                        class="toc-details__entry">
                                        {{ entry }}
                                    </div>
                                </div>

                                <div v-if="section.preview_text && !['title_page', 'table_of_contents'].includes(section.key)" class="extraction-section-card__preview">
                                    {{ section.preview_text }}
                                </div>

                                <div v-if="visibleSectionWarnings(section).length" class="extraction-section-card__warnings">
                                    <v-chip
                                        v-for="warning in visibleSectionWarnings(section)"
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

        <v-dialog v-model="titlePageEditDialog.open" persistent max-width="560">
            <v-card>
                <v-card-title class="text-subtitle-1 d-flex align-center ga-2">
                    <v-icon size="18">mdi-pencil-outline</v-icon>
                    {{ titlePageEditDialog.label }} bearbeiten
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-textarea
                        v-if="titlePageEditDialog.multiline"
                        v-model="titlePageEditDialog.value"
                        :label="titlePageEditDialog.label"
                        variant="outlined"
                        density="comfortable"
                        auto-grow
                        rows="3"
                        :counter="titlePageEditDialog.maxLength"
                        :maxlength="titlePageEditDialog.maxLength"
                        :disabled="isTitlePageSaving" />
                    <v-text-field
                        v-else
                        v-model="titlePageEditDialog.value"
                        :label="titlePageEditDialog.label"
                        variant="outlined"
                        density="comfortable"
                        :counter="titlePageEditDialog.maxLength"
                        :maxlength="titlePageEditDialog.maxLength"
                        :disabled="isTitlePageSaving" />
                </v-card-text>
                <v-divider />
                <v-card-actions>
                    <v-btn variant="tonal" color="warning" :disabled="isTitlePageSaving" @click="closeTitlePageEditDialog">
                        Abbrechen
                    </v-btn>
                    <v-spacer />
                    <v-btn variant="flat" color="primary" :loading="isTitlePageSaving" :disabled="isTitlePageSaving" @click="saveTitlePageEdit">
                        Speichern
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
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
        this.applyRouteTabSelection()
    },

    data() {
        return {
            adminStore: null,
            aba: null,
            extractionResult: null,
            parselExtractionResult: null,
            comparisonResult: null,
            isLoading: false,
            isExtractionLoading: false,
            isExtractionSubmitting: false,
            isParselExtractionLoading: false,
            isParselExtractionSubmitting: false,
            isTitlePageSaving: false,
            error: '',
            extractionLoadError: '',
            extractionPollTimer: null,
            parselExtractionPollTimer: null,
            activeTab: 'basis',
            titlePageImageStyles: {},
            overwriteExistingExtractionFields: false,
            pendingOverwriteExtractionRunId: null,
            titlePageEditDialog: {
                open: false,
                fieldKey: '',
                label: '',
                value: '',
                maxLength: 255,
                multiline: false,
                required: false,
            },
        }
    },

    mounted() {
        this.loadAba()
    },

    beforeUnmount() {
        this.clearExtractionPoll()
        this.clearParselExtractionPoll()
    },

    watch: {
        abaId(value, previousValue) {
            if (value === previousValue) {
                return
            }

            this.handleAbaNavigationChange()
        },
        '$route.query': {
            deep: true,
            handler() {
                this.applyRouteTabSelection()
            },
        },
        activeTab(value) {
            this.syncRouteTab(value)
        },
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
        currentParselExtraction() {
            return this.parselExtractionResult || this.comparisonResult?.parsel || null
        },
        comparisonSummary() {
            return this.comparisonResult?.comparison || null
        },
        comparisonSections() {
            const sections = this.comparisonSummary?.sections

            return Array.isArray(sections) ? sections : []
        },
        comparisonWarnings() {
            const warnings = this.comparisonSummary?.warnings

            return Array.isArray(warnings) ? warnings : []
        },
        titlePageOverrides() {
            const overrides = this.aba?.title_page_overrides

            return overrides && typeof overrides === 'object' ? overrides : {}
        },
        savedAbaTitle() {
            const title = String(this.aba?.title || '').trim()

            return title || 'Ohne Titel'
        },
        savedAbaStudentName() {
            const studentName = String(this.aba?.student_name || '').trim()

            return studentName || '–'
        },
        nextAba() {
            const nextAba = this.aba?.next_aba || null

            return Number(nextAba?.id || 0) > 0 ? nextAba : null
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
        isParselExtractionBusy() {
            return this.isParselExtractionSubmitting || this.isParselExtractionLoading || this.isRunningParselExtraction
        },
        isAnyExtractionBusy() {
            return this.isExtractionBusy || this.isParselExtractionBusy
        },
        isRunningExtraction() {
            return ['started', 'running'].includes(this.currentExtraction?.status)
        },
        isRunningParselExtraction() {
            return ['started', 'running'].includes(this.currentParselExtraction?.status)
        },
    },

    methods: {
        normalizeTab(value) {
            const normalized = String(value || '').trim().toLowerCase()
            const allowed = ['basis', 'extraction']

            return allowed.includes(normalized) ? normalized : 'basis'
        },
        applyRouteTabSelection() {
            const routeValue = String(this.$route?.query?.tab || '').trim().toLowerCase()
            const normalized = this.normalizeTab(routeValue)

            if (this.activeTab !== normalized) {
                this.activeTab = normalized
                return
            }

            if (routeValue && routeValue !== normalized) {
                this.syncRouteTab(normalized)
            }
        },
        syncRouteTab(value) {
            if (!this.$route || !this.$router) {
                return
            }

            const normalized = this.normalizeTab(value)
            const current = String(this.$route?.query?.tab || '').trim().toLowerCase()

            if (current === normalized) {
                return
            }

            const navigation = this.$router.replace({
                path: this.$route.path,
                query: {
                    ...(this.$route.query || {}),
                    tab: normalized,
                },
            })

            if (navigation && typeof navigation.catch === 'function') {
                navigation.catch(() => {})
            }
        },
        selectTab(value) {
            const normalized = this.normalizeTab(value)

            if (this.activeTab !== normalized) {
                this.activeTab = normalized
                return
            }

            this.syncRouteTab(normalized)
        },
        goToNextAba() {
            if (!this.nextAba?.id || !this.$router) {
                return
            }

            const navigation = this.$router.push({
                path: `/admin/aba/details/${this.nextAba.id}`,
                query: {
                    tab: this.activeTab,
                },
            })

            if (navigation && typeof navigation.catch === 'function') {
                navigation.catch(() => {})
            }
        },
        handleAbaNavigationChange() {
            this.clearExtractionPoll()
            this.clearParselExtractionPoll()
            this.error = ''
            this.extractionLoadError = ''
            this.extractionResult = null
            this.parselExtractionResult = null
            this.comparisonResult = null
            this.titlePageImageStyles = {}
            this.closeTitlePageEditDialog()
            this.loadAba()
        },
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
        formatSignedNumber(value) {
            if (typeof value !== 'number') return 'â€“'

            return value > 0 ? `+${this.formatNumber(value)}` : this.formatNumber(value)
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
        clearParselExtractionPoll() {
            if (this.parselExtractionPollTimer) {
                clearTimeout(this.parselExtractionPollTimer)
                this.parselExtractionPollTimer = null
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
        async refreshAbaAfterOverwriteExtractionIfNeeded() {
            const currentRunId = Number(this.currentExtraction?.id || 0)
            const pendingRunId = Number(this.pendingOverwriteExtractionRunId || 0)

            if (!pendingRunId || currentRunId !== pendingRunId || !this.isTerminalExtractionStatus(this.currentExtraction?.status)) {
                return
            }

            this.pendingOverwriteExtractionRunId = null
            await this.loadAba()
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
        scheduleParselExtractionPoll() {
            this.clearParselExtractionPoll()
            if (!this.isRunningParselExtraction) {
                return
            }

            this.parselExtractionPollTimer = setTimeout(() => {
                this.loadParselExtraction(true)
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
                await this.refreshAbaAfterOverwriteExtractionIfNeeded()
                this.scheduleExtractionPoll()
                await this.loadExtractionComparison(true)
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
        async loadParselExtraction(silent = false) {
            if (!silent) {
                this.isParselExtractionLoading = true
            }

            this.extractionLoadError = ''

            try {
                const response = await axios.get(`/api/admin/abas/${this.abaId}/extraction/parsel`)
                this.parselExtractionResult = response?.data?.data || null
                this.scheduleParselExtractionPoll()
                await this.loadExtractionComparison(true)
            } catch (error) {
                this.parselExtractionResult = null
                this.clearParselExtractionPoll()
                this.extractionLoadError = error?.response?.data?.message || 'Extraktionsdaten konnten nicht geladen werden.'
            } finally {
                if (!silent) {
                    this.isParselExtractionLoading = false
                }
            }
        },
        async loadExtractionComparison(silent = false) {
            if (!silent) {
                this.isParselExtractionLoading = true
            }

            try {
                const response = await axios.get(`/api/admin/abas/${this.abaId}/extraction/compare`)
                this.comparisonResult = response?.data?.data || null
            } catch (error) {
                if (!silent) {
                    this.extractionLoadError = error?.response?.data?.message || 'Extraktionsvergleich konnte nicht geladen werden.'
                }
            } finally {
                if (!silent) {
                    this.isParselExtractionLoading = false
                }
            }
        },
        async startExtraction() {
            this.isExtractionSubmitting = true
            this.extractionLoadError = ''

            try {
                const response = await axios.post(`/api/admin/abas/${this.abaId}/extraction`, {
                    data: {
                        overwrite_existing_fields: this.overwriteExistingExtractionFields,
                    },
                })
                this.extractionResult = response?.data?.data || null
                this.pendingOverwriteExtractionRunId = this.overwriteExistingExtractionFields
                    ? Number(this.extractionResult?.id || 0) || null
                    : null
                this.syncAbaLatestExtraction()
                await this.refreshAbaAfterOverwriteExtractionIfNeeded()
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
        async startParselExtraction() {
            this.isParselExtractionSubmitting = true
            this.extractionLoadError = ''

            try {
                const response = await axios.post(`/api/admin/abas/${this.abaId}/extraction/parsel`)
                this.parselExtractionResult = response?.data?.data || null
                this.scheduleParselExtractionPoll()
                await this.loadExtractionComparison(true)

                const successMessage = this.isRunningParselExtraction
                    ? 'Extraktion 2 wurde gestartet.'
                    : this.currentParselExtraction?.status === 'completed'
                        ? 'Extraktion 2 abgeschlossen.'
                        : this.currentParselExtraction?.status === 'failed'
                            ? 'Extraktion 2 ist fehlgeschlagen.'
                            : 'Extraktion 2 wurde aktualisiert.'

                useNotificationStore().notify({
                    message: successMessage,
                    type: this.currentParselExtraction?.status === 'failed' ? 'error' : 'success',
                    timeout: 3000,
                })
            } catch (error) {
                const message = error?.response?.data?.message || 'Extraktion 2 konnte nicht gestartet werden.'
                this.extractionLoadError = message
                useNotificationStore().notify({
                    status: error?.response?.status || 500,
                    message,
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isParselExtractionSubmitting = false
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
        visibleSectionWarnings(section) {
            const warnings = Array.isArray(section?.warnings) ? section.warnings : []
            const hiddenWarnings = new Set([
                'Mehrere ähnliche Abschnittskandidaten erkannt.',
            ])

            if (section?.key === 'table_of_contents') {
                warnings.forEach((warning) => {
                    if (String(warning || '').includes('Inhaltsverzeichnisse erkannt; angezeigt wird die passendste Variante.')) {
                        hiddenWarnings.add(String(warning))
                    }
                })
            }

            return warnings.filter((warning) => !hiddenWarnings.has(String(warning)))
        },
        tableOfContentsEntries(section) {
            const entries = section?.table_of_contents?.entries

            return Array.isArray(entries)
                ? entries.filter((entry) => String(entry || '').trim() !== '')
                : []
        },
        titlePageFieldConfig(fieldKey) {
            const fields = {
                title: { label: 'Titel', maxLength: 255, multiline: true, required: true },
                subtitle: { label: 'Untertitel', maxLength: 500, multiline: true, required: false },
                author: { label: 'Verfasser:in', maxLength: 255, multiline: false, required: true },
                class: { label: 'Klasse', maxLength: 100, multiline: false, required: false },
                advisor: { label: 'Betreuer:in', maxLength: 255, multiline: false, required: false },
                school_full: { label: 'Schule (vollständig)', maxLength: 500, multiline: true, required: false },
                date: { label: 'Datum', maxLength: 255, multiline: false, required: false },
            }

            return fields[fieldKey] || { label: 'Feld', maxLength: 255, multiline: false, required: false }
        },
        titlePageHeroTitle(section) {
            return this.resolveTitlePageFieldValue(section, 'title')
        },
        titlePageHeroSubtitle(section) {
            return this.resolveTitlePageFieldValue(section, 'subtitle')
        },
        resolveTitlePageFieldValue(section, fieldKey) {
            const titlePage = section?.title_page || {}

            const values = {
                title: [this.aba?.title, titlePage.title],
                subtitle: [this.titlePageOverrides.subtitle, titlePage.subtitle],
                author: [this.aba?.student_name, titlePage.author],
                class: [this.aba?.student_class, titlePage.class],
                advisor: [this.titlePageOverrides.advisor, titlePage.advisor],
                school_full: [this.titlePageOverrides.school_full, titlePage.school_full, this.aba?.school_name],
                date: [this.titlePageOverrides.date, titlePage.date],
            }

            return (values[fieldKey] || [])
                .map((value) => this.normalizeOptionalDetail(value))
                .find(Boolean) || null
        },
        titlePageDetailItems(section) {
            const schoolItem = this.titlePageSchoolDetailItem(section)

            return [
                this.buildEditableTitlePageItem(section, 'author'),
                this.buildEditableTitlePageItem(section, 'class'),
                this.buildEditableTitlePageItem(section, 'advisor'),
                schoolItem,
                this.buildEditableTitlePageItem(section, 'date'),
                { key: 'images', label: 'Gefundene Bilder', value: this.formatFoundImages(section?.title_page?.found_images_count), editable: false },
                { key: 'page', label: 'Seitenzahl', value: this.titlePagePageLabel(section?.title_page || {}), editable: false },
            ]
        },
        buildEditableTitlePageItem(section, fieldKey) {
            const config = this.titlePageFieldConfig(fieldKey)
            const value = this.resolveTitlePageFieldValue(section, fieldKey)

            return {
                key: fieldKey,
                editKey: fieldKey,
                label: config.label,
                value: value || '–',
                editValue: value || '',
                editable: true,
            }
        },
        titlePageSchoolDetailItem(section) {
            const school = this.resolveTitlePageSchoolParts(section)
            const lines = [
                { label: 'Name', value: school.name },
                { label: 'Straße', value: school.street },
                { label: 'Ort', value: school.city },
            ].filter((line) => String(line.value || '').trim() !== '')

            return {
                key: 'school',
                label: 'Schule (vollständig)',
                value: lines.length ? null : (school.full || '–'),
                editKey: 'school_full',
                editValue: school.full || '',
                editable: true,
                lines,
            }
        },
        resolveTitlePageSchoolParts(section) {
            const titlePage = section?.title_page || {}
            const overrideSchoolFull = this.normalizeOptionalDetail(this.titlePageOverrides.school_full)
            const schoolName = this.normalizeOptionalDetail(titlePage?.school) || this.normalizeOptionalDetail(this.aba?.school_name)
            const schoolStreet = this.normalizeOptionalDetail(titlePage?.school_address)
            const schoolCity = this.normalizeOptionalDetail(titlePage?.school_city || titlePage?.school_location)
            const schoolFull = overrideSchoolFull || this.normalizeOptionalDetail(titlePage?.school_full)

            if (overrideSchoolFull) {
                const overrideSegments = overrideSchoolFull
                    .split(',')
                    .map((segment) => this.normalizeOptionalDetail(segment))
                    .filter(Boolean)

                return {
                    name: overrideSegments[0] || null,
                    street: overrideSegments[1] || null,
                    city: overrideSegments.slice(2).join(', ') || null,
                    full: overrideSchoolFull,
                }
            }

            if (schoolName || schoolStreet || schoolCity) {
                return {
                    name: schoolName,
                    street: schoolStreet,
                    city: schoolCity,
                    full: schoolFull || [schoolName, schoolStreet, schoolCity].filter(Boolean).join(', '),
                }
            }

            if (!schoolFull) {
                return {
                    name: null,
                    street: null,
                    city: null,
                    full: schoolName,
                }
            }

            const segments = schoolFull
                .split(',')
                .map((segment) => this.normalizeOptionalDetail(segment))
                .filter(Boolean)

            return {
                name: segments[0] || null,
                street: segments[1] || null,
                city: segments.slice(2).join(', ') || null,
                full: schoolFull,
            }
        },
        normalizeOptionalDetail(value) {
            const normalized = String(value || '').trim()

            return normalized !== '' && normalized !== '–' ? normalized : null
        },
        openTitlePageEditDialog(fieldKey, currentValue) {
            const config = this.titlePageFieldConfig(fieldKey)

            this.titlePageEditDialog = {
                open: true,
                fieldKey,
                label: config.label,
                value: currentValue || '',
                maxLength: config.maxLength,
                multiline: config.multiline,
                required: config.required,
            }
        },
        closeTitlePageEditDialog(force = false) {
            if (this.isTitlePageSaving && !force) {
                return
            }

            this.titlePageEditDialog = {
                open: false,
                fieldKey: '',
                label: '',
                value: '',
                maxLength: 255,
                multiline: false,
                required: false,
            }
        },
        titlePageEditPayload(fieldKey, value) {
            switch (fieldKey) {
            case 'title':
                return { title: value }
            case 'subtitle':
                return { title_page_overrides: { subtitle: value || null } }
            case 'author':
                return { student_name: value }
            case 'class':
                return { student_class: value || null }
            case 'advisor':
                return { title_page_overrides: { advisor: value || null } }
            case 'school_full':
                return { title_page_overrides: { school_full: value || null } }
            case 'date':
                return { title_page_overrides: { date: value || null } }
            default:
                return {}
            }
        },
        async saveTitlePageEdit() {
            const fieldKey = this.titlePageEditDialog.fieldKey
            const config = this.titlePageFieldConfig(fieldKey)
            const value = String(this.titlePageEditDialog.value || '').trim()

            if (config.required && value === '') {
                useNotificationStore().notify({
                    message: `${config.label} darf nicht leer sein.`,
                    type: 'error',
                    timeout: 3000,
                })
                return
            }

            this.isTitlePageSaving = true

            try {
                const response = await axios.put(`/api/admin/abas/${this.abaId}`, {
                    data: this.titlePageEditPayload(fieldKey, value),
                })

                this.aba = response?.data || this.aba
                this.closeTitlePageEditDialog(true)

                useNotificationStore().notify({
                    message: `${config.label} wurde gespeichert.`,
                    type: 'success',
                    timeout: 3000,
                })
            } catch (error) {
                const message = error?.response?.data?.message || `${config.label} konnte nicht gespeichert werden.`

                useNotificationStore().notify({
                    status: error?.response?.status || 500,
                    message,
                    type: 'error',
                    timeout: this.config?.timeout,
                })
            } finally {
                this.isTitlePageSaving = false
            }
        },
        titlePageImages(section) {
            const images = Array.isArray(section?.title_page?.images) ? section.title_page.images : []

            return images.filter((image) => String(image?.url || '').trim() !== '')
        },
        titlePageImageStyleKey(image) {
            return `${String(image?.url || '').trim()}|${String(image?.asset_index ?? '')}`
        },
        titlePageImageSurfaceStyle(image) {
            const presentation = this.titlePageImageStyles[this.titlePageImageStyleKey(image)] || null

            return {
                '--title-page-image-surface-background': presentation?.background || 'linear-gradient(135deg, rgba(241, 245, 249, 0.96) 0%, rgba(226, 232, 240, 0.94) 48%, rgba(15, 23, 42, 0.92) 52%, rgba(2, 6, 23, 0.96) 100%)',
                '--title-page-image-surface-border': presentation?.borderColor || 'rgba(148, 163, 184, 0.22)',
                '--title-page-image-surface-shadow': presentation?.shadow || 'inset 0 1px 0 rgba(255, 255, 255, 0.08)',
            }
        },
        handleTitlePageImageLoad(image, event) {
            const imageElement = event?.target
            const presentation = this.analyzeTitlePageImageElement(imageElement)
            if (!presentation) {
                return
            }

            const key = this.titlePageImageStyleKey(image)
            this.titlePageImageStyles = {
                ...this.titlePageImageStyles,
                [key]: presentation,
            }
        },
        analyzeTitlePageImageElement(imageElement) {
            if (
                typeof window === 'undefined'
                || typeof document === 'undefined'
                || !(imageElement instanceof HTMLImageElement)
                || !imageElement.naturalWidth
                || !imageElement.naturalHeight
            ) {
                return null
            }

            const canvas = document.createElement('canvas')
            const sampleSize = 36
            canvas.width = sampleSize
            canvas.height = sampleSize

            const context = canvas.getContext('2d', { willReadFrequently: true })
            if (!context) {
                return null
            }

            try {
                context.clearRect(0, 0, sampleSize, sampleSize)
                context.drawImage(imageElement, 0, 0, sampleSize, sampleSize)
                const pixels = context.getImageData(0, 0, sampleSize, sampleSize).data

                let weightedPixelCount = 0
                let weightedRed = 0
                let weightedGreen = 0
                let weightedBlue = 0
                let weightedLuminance = 0
                let darkWeight = 0
                let lightWeight = 0

                for (let index = 0; index < pixels.length; index += 4) {
                    const alpha = pixels[index + 3] / 255
                    if (alpha < 0.08) {
                        continue
                    }

                    const red = pixels[index]
                    const green = pixels[index + 1]
                    const blue = pixels[index + 2]
                    const luminance = (0.2126 * red) + (0.7152 * green) + (0.0722 * blue)

                    weightedPixelCount += alpha
                    weightedRed += red * alpha
                    weightedGreen += green * alpha
                    weightedBlue += blue * alpha
                    weightedLuminance += luminance * alpha

                    if (luminance < 96) {
                        darkWeight += alpha
                    }
                    if (luminance > 190) {
                        lightWeight += alpha
                    }
                }

                if (weightedPixelCount <= 0) {
                    return null
                }

                const averageRed = weightedRed / weightedPixelCount
                const averageGreen = weightedGreen / weightedPixelCount
                const averageBlue = weightedBlue / weightedPixelCount
                const averageLuminance = weightedLuminance / weightedPixelCount
                const coverage = weightedPixelCount / (sampleSize * sampleSize)
                const darkRatio = darkWeight / weightedPixelCount
                const lightRatio = lightWeight / weightedPixelCount

                return this.buildTitlePageImagePresentation({
                    red: averageRed,
                    green: averageGreen,
                    blue: averageBlue,
                    luminance: averageLuminance,
                    coverage,
                    darkRatio,
                    lightRatio,
                })
            } catch {
                return null
            }
        },
        buildTitlePageImagePresentation({ red, green, blue, luminance, coverage, darkRatio, lightRatio }) {
            const { hue, saturation } = this.rgbToHsl(red, green, blue)
            const normalizedHue = Number.isFinite(hue) ? Math.round(hue) : 212
            const tintedSaturation = this.clamp((saturation * 0.48) + 8, 8, 42)
            const mostlyDark = luminance < 118 || darkRatio > 0.62
            const mostlyLight = luminance > 182 || lightRatio > 0.62

            if (mostlyDark) {
                const endLightness = coverage < 0.18 ? 92 : 86

                return {
                    background: `linear-gradient(135deg, hsla(${normalizedHue}, ${tintedSaturation}%, 97%, 0.98) 0%, hsla(${normalizedHue}, ${this.clamp(tintedSaturation * 0.7, 10, 28)}%, ${endLightness}%, 0.96) 100%)`,
                    borderColor: `hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 18, 36)}%, 70%, 0.42)`,
                    shadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.52)',
                }
            }

            if (mostlyLight) {
                return {
                    background: `linear-gradient(135deg, hsla(${normalizedHue}, ${this.clamp(tintedSaturation * 0.85, 10, 32)}%, 11%, 0.98) 0%, hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 12, 36)}%, 18%, 0.96) 100%)`,
                    borderColor: `hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 16, 34)}%, 48%, 0.38)`,
                    shadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.08)',
                }
            }

            if (luminance < 148) {
                return {
                    background: `linear-gradient(135deg, hsla(${normalizedHue}, ${this.clamp(tintedSaturation * 0.75, 10, 28)}%, 95%, 0.98) 0%, hsla(${normalizedHue}, ${this.clamp(tintedSaturation * 0.9, 10, 32)}%, 84%, 0.95) 100%)`,
                    borderColor: `hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 16, 34)}%, 68%, 0.36)`,
                    shadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.4)',
                }
            }

            return {
                background: `linear-gradient(135deg, hsla(${normalizedHue}, ${this.clamp(tintedSaturation * 0.9, 10, 34)}%, 14%, 0.98) 0%, hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 12, 36)}%, 24%, 0.96) 100%)`,
                borderColor: `hsla(${normalizedHue}, ${this.clamp(tintedSaturation, 16, 34)}%, 52%, 0.36)`,
                shadow: 'inset 0 1px 0 rgba(255, 255, 255, 0.08)',
            }
        },
        rgbToHsl(red, green, blue) {
            const normalizedRed = this.clamp(red / 255, 0, 1)
            const normalizedGreen = this.clamp(green / 255, 0, 1)
            const normalizedBlue = this.clamp(blue / 255, 0, 1)
            const max = Math.max(normalizedRed, normalizedGreen, normalizedBlue)
            const min = Math.min(normalizedRed, normalizedGreen, normalizedBlue)
            const delta = max - min

            let hue = 0
            if (delta !== 0) {
                if (max === normalizedRed) {
                    hue = 60 * (((normalizedGreen - normalizedBlue) / delta) % 6)
                } else if (max === normalizedGreen) {
                    hue = 60 * (((normalizedBlue - normalizedRed) / delta) + 2)
                } else {
                    hue = 60 * (((normalizedRed - normalizedGreen) / delta) + 4)
                }
            }

            const lightness = (max + min) / 2
            const saturation = delta === 0
                ? 0
                : delta / (1 - Math.abs((2 * lightness) - 1))

            return {
                hue: hue < 0 ? hue + 360 : hue,
                saturation: saturation * 100,
                lightness: lightness * 100,
            }
        },
        clamp(value, min, max) {
            return Math.min(Math.max(value, min), max)
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
                await this.loadParselExtraction(true)
                await this.loadExtractionComparison(true)
            } catch (error) {
                this.aba = null
                this.extractionResult = null
                this.parselExtractionResult = null
                this.comparisonResult = null
                this.clearExtractionPoll()
                this.clearParselExtractionPoll()
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

.extraction-overwrite-switch {
    margin-inline-end: 0;
    align-self: flex-start;
}

.extraction-header-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
}

.extraction-overwrite-switch__label {
    font-size: 0.84rem;
    font-weight: 600;
    color: rgba(226, 232, 240, 0.94);
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

.basis-field__icon-wrap--cyan {
    background: rgba(34, 211, 238, 0.12);
    color: #67e8f9;
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
    align-items: flex-start;
    gap: 10px;
    border: 1px solid rgba(96, 165, 250, 0.18);
    border-radius: 14px;
    padding: 12px 14px;
    color: #cbd5e1;
    background: rgba(15, 23, 42, 0.35);
    font-size: 0.84rem;
}

.extraction-note__content {
    display: grid;
    gap: 4px;
}

.extraction-note__title {
    font-size: 0.9rem;
    font-weight: 700;
    line-height: 1.35;
    color: #f8fafc;
}

.extraction-note__meta {
    font-size: 0.8rem;
    line-height: 1.35;
    color: #cbd5e1;
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

.extraction-stats--comparison {
    margin-top: 14px;
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

.extraction-comparison {
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 14px;
    padding: 14px;
    background: rgba(15, 23, 42, 0.38);
}

.extraction-comparison__header,
.extraction-comparison__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.extraction-comparison__title,
.extraction-comparison__row-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #f1f5f9;
}

.extraction-comparison__subtitle,
.extraction-comparison__row-meta {
    font-size: 0.74rem;
    color: #94a3b8;
    margin-top: 2px;
}

.extraction-comparison__rows {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.extraction-comparison__row {
    padding: 10px 0;
    border-top: 1px solid rgba(148, 163, 184, 0.1);
}

.extraction-comparison__chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
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

.extraction-section-card__chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
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

.toc-details {
    display: grid;
    gap: 6px;
    margin-top: 12px;
}

.toc-details__entry {
    font-size: 0.82rem;
    line-height: 1.45;
    color: #dbeafe;
    white-space: pre-wrap;
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

.title-page-details__hero-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
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

.title-page-details__hero-subtitle-block {
    margin-top: 10px;
}

.title-page-details__hero-subtitle-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.title-page-details__hero-subtitle-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.35px;
    color: #94a3b8;
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

.title-page-details__item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
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

.title-page-details__item-lines {
    display: grid;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(148, 163, 184, 0.12);
}

.title-page-details__item-line {
    display: grid;
    gap: 2px;
}

.title-page-details__item-line-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #94a3b8;
}

.title-page-details__item-line-value {
    font-size: 0.8rem;
    line-height: 1.4;
    color: #dbeafe;
}

.title-page-details__extras-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.35px;
    color: #94a3b8;
}

.title-page-details__images {
    display: grid;
    gap: 8px;
}

.title-page-details__image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}

.title-page-details__image-card {
    border: 1px solid rgba(148, 163, 184, 0.12);
    border-radius: 14px;
    overflow: hidden;
    background: rgba(15, 23, 42, 0.38);
}

.title-page-details__image-surface {
    height: 180px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--title-page-image-surface-background);
    box-shadow: var(--title-page-image-surface-shadow);
    border-bottom: 1px solid var(--title-page-image-surface-border);
}

.title-page-details__image {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    background: transparent;
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

    .extraction-header-actions {
        align-items: stretch;
    }

    .extraction-stats {
        flex-direction: column;
    }
}
</style>
