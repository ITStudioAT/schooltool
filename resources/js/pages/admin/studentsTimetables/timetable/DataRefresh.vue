<template>
    <v-card rounded="xl" class="student-data-refresh">
        <v-card-title class="student-data-refresh__title-row pt-4 px-4">
            <v-icon color="primary" size="22" icon="mdi-database-sync-outline" />
            <h2 class="student-data-refresh__title">
                Datenaktualisierung
                <span class="text-primary">{{ schoolyearLabel }}</span>
            </h2>
            <v-btn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="mdi-arrow-left"
                class="student-data-refresh__back-button"
                @click="$emit('back')">
                Zurück
            </v-btn>
        </v-card-title>

        <v-card-text class="px-4 pb-4">
            <v-alert type="info" variant="tonal" class="mb-4">
                Bei dieser Datenaktualisierung werden für das ausgewählte Schuljahr ausgeführt:
            </v-alert>

            <div class="student-data-refresh__tasks mb-4">
                <div class="student-data-refresh__task">
                    <v-icon icon="mdi-account-details-outline" color="primary" />
                    <span>Aktualisierung aller Studienauswahl bei den Studierenden</span>
                </div>
                <div class="student-data-refresh__task">
                    <v-icon icon="mdi-format-list-numbered" color="primary" />
                    <span>Aktualisierung aller Noten bei den Studierenden</span>
                </div>
            </div>

            <v-alert v-if="loadError" type="error" variant="tonal" class="mb-4">
                {{ loadError }}
            </v-alert>

            <v-btn
                color="primary"
                variant="flat"
                prepend-icon="mdi-play"
                :loading="starting"
                :disabled="loading || refreshIsActive"
                @click="startRefresh">
                Datenaktualisierung starten
            </v-btn>

            <v-progress-linear v-if="loading && !dataRefresh" indeterminate color="primary" class="mt-5" />

            <v-alert
                v-if="refreshIsActive"
                type="info"
                variant="tonal"
                class="student-data-refresh__status mt-5">
                <div class="d-flex align-center justify-space-between ga-3 mb-2">
                    <strong>{{ dataRefresh.status === 'queued' ? 'Datenaktualisierung wird vorbereitet' : 'Datenaktualisierung läuft' }}</strong>
                    <span v-if="dataRefresh.total_students" class="text-caption">
                        {{ dataRefresh.processed_students }} von {{ dataRefresh.total_students }} Studierenden
                    </span>
                </div>
                <v-progress-linear
                    color="primary"
                    height="12"
                    rounded
                    :indeterminate="dataRefresh.status === 'queued' || !dataRefresh.total_students"
                    :model-value="dataRefresh.progress_percent" />
                <div v-if="dataRefresh.status === 'running'" class="text-caption mt-2">
                    {{ dataRefresh.progress_percent }} % abgeschlossen
                </div>
            </v-alert>

            <v-alert
                v-if="dataRefresh?.status === 'completed'"
                type="success"
                variant="tonal"
                class="student-data-refresh__status mt-5">
                <strong>Datenaktualisierung abgeschlossen</strong>
                <div class="student-data-refresh__summary mt-3">
                    <div class="student-data-refresh__summary-item">
                        <span>Studierende verarbeitet</span>
                        <strong>{{ dataRefresh.processed_students }}</strong>
                    </div>
                    <div class="student-data-refresh__summary-item">
                        <span>Studienauswahl aktualisiert</span>
                        <strong>{{ dataRefresh.study_selections_updated }}</strong>
                    </div>
                    <div class="student-data-refresh__summary-item">
                        <span>Noten aktualisiert</span>
                        <strong>{{ dataRefresh.course_results_updated }}</strong>
                    </div>
                </div>
                <div v-if="finishedAtLabel" class="text-caption mt-3">
                    Abgeschlossen: {{ finishedAtLabel }}
                </div>
            </v-alert>

            <v-alert
                v-if="dataRefresh?.status === 'failed'"
                type="error"
                variant="tonal"
                class="student-data-refresh__status mt-5">
                <strong>Datenaktualisierung fehlgeschlagen</strong>
                <div class="mt-1">
                    {{ dataRefresh.error_message || 'Die Datenaktualisierung konnte nicht abgeschlossen werden.' }}
                </div>
            </v-alert>
        </v-card-text>
    </v-card>
</template>

<script>
import {
    index as loadStudentDataRefresh,
    store as startStudentDataRefresh,
} from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/StudentTimetableDataRefreshController'

export default {
    name: 'StudentTimetableDataRefresh',
    props: {
        schoolyearLabel: {
            type: String,
            default: 'nicht festgelegt',
        },
        schoolyearId: {
            type: [Number, String],
            default: null,
        },
    },
    emits: ['back'],
    data() {
        return {
            dataRefresh: null,
            loading: false,
            starting: false,
            loadError: '',
            pollingInterval: null,
        }
    },
    computed: {
        refreshIsActive() {
            return ['queued', 'running'].includes(this.dataRefresh?.status)
        },
        finishedAtLabel() {
            const value = this.dataRefresh?.finished_at

            if (!value) return ''

            const date = new Date(value)

            if (Number.isNaN(date.getTime())) return value

            return new Intl.DateTimeFormat('de-AT', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date)
        },
    },
    watch: {
        schoolyearId() {
            this.clearPolling()
            this.dataRefresh = null
            void this.loadRefresh()
        },
    },
    mounted() {
        void this.loadRefresh()
    },
    unmounted() {
        this.clearPolling()
    },
    methods: {
        async loadRefresh({ silent = false } = {}) {
            if (!silent) {
                this.loading = true
                this.loadError = ''
            }

            try {
                const response = await axios.get(loadStudentDataRefresh.url())
                this.dataRefresh = response?.data?.data || null

                if (this.refreshIsActive) {
                    this.schedulePolling()
                } else {
                    this.clearPolling()
                }
            } catch (error) {
                if (!silent) {
                    this.loadError = error?.response?.data?.message
                        || 'Der Stand der Datenaktualisierung konnte nicht geladen werden.'
                }
            } finally {
                if (!silent) this.loading = false
            }
        },
        async startRefresh() {
            if (this.starting || this.refreshIsActive) return

            this.starting = true
            this.loadError = ''

            try {
                const response = await axios.post(startStudentDataRefresh.url())
                this.dataRefresh = response?.data?.data || null
                this.schedulePolling()
            } catch (error) {
                this.loadError = error?.response?.data?.message
                    || 'Die Datenaktualisierung konnte nicht gestartet werden.'
            } finally {
                this.starting = false
            }
        },
        schedulePolling() {
            if (this.pollingInterval) return

            this.pollingInterval = window.setInterval(() => {
                void this.loadRefresh({ silent: true })
            }, 1500)
        },
        clearPolling() {
            if (!this.pollingInterval) return

            window.clearInterval(this.pollingInterval)
            this.pollingInterval = null
        },
    },
}
</script>

<style scoped>
.student-data-refresh {
    border: 1px solid rgba(37, 99, 235, 0.16);
    background: linear-gradient(145deg, rgba(239, 246, 255, 0.7), #fff 44%);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.student-data-refresh__title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-data-refresh__title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
}

.student-data-refresh__back-button {
    flex: 0 0 auto;
    margin-left: auto;
    letter-spacing: 0;
    text-transform: none;
}

.student-data-refresh__tasks {
    display: grid;
    gap: 10px;
}

.student-data-refresh__task {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 14px;
    border: 1px solid rgba(37, 99, 235, 0.14);
    border-radius: 12px;
    background: #fff;
    color: #1e293b;
    font-weight: 700;
}

.student-data-refresh__status {
    border-radius: 12px;
}

.student-data-refresh__summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.student-data-refresh__summary-item {
    padding: 10px;
    border: 1px solid rgba(22, 163, 74, 0.2);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.76);
}

.student-data-refresh__summary-item span,
.student-data-refresh__summary-item strong {
    display: block;
}

.student-data-refresh__summary-item span {
    font-size: 0.75rem;
}

.student-data-refresh__summary-item strong {
    margin-top: 2px;
    font-size: 1.2rem;
}

@media (max-width: 600px) {
    .student-data-refresh__title-row {
        flex-wrap: wrap;
    }

    .student-data-refresh__back-button {
        width: 100%;
        margin-left: 0;
    }

    .student-data-refresh__summary {
        grid-template-columns: 1fr;
    }
}
</style>
