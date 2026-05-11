<template>
    <v-col cols="12" md="6" lg="7" xl="4">
        <v-card rounded="lg" border class="mb-4">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-upload" />
                TXT-Datei importieren
            </v-card-title>
            <v-card-text>
                <v-alert v-if="currentImport" type="warning" variant="tonal" class="mb-3">
                    Es existiert bereits eine Datei für dieses Schuljahr. Ein neuer Import ersetzt die bestehende Datei.
                </v-alert>
                <FileUpload
                    :path="'/api/admin/students-timetables/upload'"
                    fileLabel
                    :allowedFileTypes="['text/plain']"
                    @fileUploadFinished="onUploadFinished"
                    @error="onUploadError" />
            </v-card-text>
        </v-card>

        <v-card rounded="lg" border>
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-file-document-outline" />
                Importierte Datei
                <v-chip v-if="schoolyearName" size="small" variant="tonal" color="light-blue">
                    {{ schoolyearName }}
                </v-chip>
            </v-card-title>
            <v-card-text>
                <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-2" />

                <v-alert v-if="!loading && !currentImport" type="info" variant="tonal">
                    Noch keine Datei für dieses Schuljahr importiert.
                </v-alert>

                <template v-if="currentImport">
                    <div class="d-flex align-center ga-2 mb-3">
                        <v-icon icon="mdi-file-document-outline" color="primary" />
                        <div>
                            <div class="font-weight-medium">{{ currentImport.original_filename }}</div>
                            <div class="text-caption text-medium-emphasis">
                                Importiert am {{ formatDate(currentImport.imported_at) }}
                            </div>
                        </div>
                    </div>

                    <v-table density="comfortable">
                        <thead>
                            <tr>
                                <th>Sektion</th>
                                <th>Beschreibung</th>
                                <th class="text-right">Anzahl</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(count, code) in currentImport.sections" :key="code">
                                <tr>
                                    <td>
                                        <v-chip size="small" color="primary" variant="tonal">{{ code }}</v-chip>
                                    </td>
                                    <td>{{ sectionLabel(code) }}</td>
                                    <td class="text-right font-weight-medium">{{ count }}</td>
                                </tr>
                                <tr v-if="code === 'TT' && currentImport.tt_courses" class="tt-sub-row">
                                    <td></td>
                                    <td class="text-caption pl-6">davon verschiedene Kurse</td>
                                    <td class="text-right font-weight-medium text-caption">{{ currentImport.tt_courses }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" class="font-weight-bold">Gesamt</td>
                                <td class="text-right font-weight-bold">{{ currentImport.total_lines }}</td>
                            </tr>
                        </tfoot>
                    </v-table>
                </template>
            </v-card-text>
        </v-card>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import FileUpload from '@/pages/components/FileUpload.vue'

const SECTION_LABELS = {
    VV: 'Kopfdaten / Version',
    SU: 'Unterrichtsfächer',
    TE: 'Lehrer',
    RM: 'Räume',
    KL: 'Klassen',
    GR: 'Gruppen',
    LS: 'Unterrichtseinheiten',
    TT: 'Stundenplan-Einträge',
}

export default {
    name: 'StudentsTimetablesImport',
    components: { FileUpload },
    data() {
        return {
            currentImport: null,
            loading: false,
        }
    },
    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        schoolyearName() {
            return this.config?.selected_schoolyear?.name || ''
        },
    },
    watch: {
        'config.selected_schoolyear.id'() {
            this.loadImport()
        },
    },
    mounted() {
        this.loadImport()
    },
    methods: {
        async loadImport() {
            this.loading = true
            try {
                const response = await axios.get('/api/admin/students-timetables/imports', {
                    params: { page: 1 },
                })
                this.currentImport = response.data.data?.[0] || null
            } catch {
                this.currentImport = null
            } finally {
                this.loading = false
            }
        },
        onUploadFinished() {
            this.loadImport()
        },
        onUploadError() {},
        sectionLabel(code) {
            return SECTION_LABELS[code] || code
        },
        formatDate(dateStr) {
            if (!dateStr) return ''
            const d = new Date(dateStr)
            return d.toLocaleDateString('de-AT', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            })
        },
    },
}
</script>

<style scoped>
.tt-sub-row td {
    border-top: none !important;
    padding-top: 0 !important;
    color: rgba(30, 64, 175, 0.8);
}
</style>
