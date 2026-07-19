<template>
    <v-col cols="12">
        <v-card rounded="xl" class="pa-4 pa-md-6" data-testid="teaching-test-environment">
            <div class="d-flex flex-column flex-md-row align-md-center ga-4">
                <div class="flex-grow-1">
                    <div class="test-environment-title d-flex align-center ga-2 text-h6 font-weight-bold">
                        <v-icon color="warning" icon="mdi-flask-outline" />
                        Temporäre Test-Umgebung 2026/27
                    </div>
                    <p class="text-body-2 text-medium-emphasis mt-2 mb-0">
                        Ausschließlich für den Test des Unterrichtsmoduls. Diese Funktion wird nach Abschluss der Tests wieder entfernt.
                    </p>
                </div>

                <v-btn
                    v-if="status"
                    :color="status.is_configured ? 'error' : 'warning'"
                    variant="flat"
                    size="large"
                    rounded="xl"
                    :prepend-icon="status.is_configured ? 'mdi-delete-sweep-outline' : 'mdi-flask-plus-outline'"
                    :loading="submitting"
                    class="test-environment-action"
                    data-testid="teaching-test-environment-action"
                    @click="openConfirmation">
                    {{ status.is_configured ? 'Test-Umgebung löschen' : 'Test-Umgebung 26/27 einrichten' }}
                </v-btn>
            </div>

            <v-progress-linear v-if="loading" indeterminate color="primary" class="mt-5" />

            <v-alert v-if="error" type="error" variant="tonal" rounded="lg" class="mt-5">
                {{ error }}
            </v-alert>

            <template v-if="status && !loading">
                <v-alert
                    :type="status.is_configured ? 'warning' : 'info'"
                    variant="tonal"
                    rounded="lg"
                    class="mt-5">
                    <strong v-if="status.is_configured">Die Test-Umgebung ist aktiv.</strong>
                    <strong v-else>Die Test-Umgebung ist noch nicht eingerichtet.</strong>
                    Beim Einrichten werden bestehende Testdaten für 2026/27 zuerst entfernt. Grundeinstellungen,
                    Einträge und eigene freie Tage bleiben unverändert.
                </v-alert>

                <div class="d-flex flex-wrap ga-2 mt-4">
                    <v-chip color="primary" variant="tonal" prepend-icon="mdi-database-import-outline">
                        2025/26: {{ status.source_import116_count }} Import-116-Datensätze
                    </v-chip>
                    <v-chip :color="status.target_import116_count ? 'warning' : 'default'" variant="tonal" prepend-icon="mdi-account-school-outline">
                        2026/27: {{ status.target_import116_count }} Import-116-Datensätze
                    </v-chip>
                    <v-chip :color="status.target_teaching_record_count ? 'warning' : 'default'" variant="tonal" prepend-icon="mdi-book-open-variant-outline">
                        {{ status.target_teaching_record_count }} löschbare Unterrichtsdatensätze
                    </v-chip>
                </div>
            </template>
        </v-card>

        <v-dialog v-model="confirmationOpen" max-width="560" persistent>
            <v-card rounded="xl">
                <v-card-title class="test-environment-dialog-title d-flex align-center ga-2 pt-5 px-5">
                    <v-icon :color="status?.is_configured ? 'error' : 'warning'" icon="mdi-alert-outline" />
                    {{ confirmationTitle }}
                </v-card-title>
                <v-card-text class="px-5">
                    <template v-if="status?.is_configured">
                        Die Testdaten für <strong>2026/27</strong> werden endgültig gelöscht. Grundeinstellungen,
                        Einträge und eigene freie Tage bleiben unverändert. Globale Benutzerkonten bleiben erhalten und
                        werden auf 2025/26 zurückgestellt.
                    </template>
                    <template v-else>
                        Bestehende Testdaten für <strong>2026/27</strong> werden zuerst endgültig gelöscht. Grundeinstellungen,
                        Einträge und eigene freie Tage bleiben unverändert. Danach werden alle Import-116-Datensätze aus
                        2025/26 als isolierte Testdaten übernommen.
                    </template>

                    <v-text-field
                        v-model="confirmationText"
                        class="mt-5"
                        label="Zur Bestätigung 2026/27 eingeben"
                        variant="outlined"
                        hide-details
                        autocomplete="off"
                        data-testid="teaching-test-environment-confirmation" />
                </v-card-text>
                <v-card-actions class="test-environment-dialog-actions px-5 pb-5">
                    <v-btn variant="tonal" :disabled="submitting" @click="closeConfirmation">Abbrechen</v-btn>
                    <v-spacer />
                    <v-btn
                        :color="status?.is_configured ? 'error' : 'warning'"
                        variant="flat"
                        :disabled="confirmationText !== '2026/27'"
                        :loading="submitting"
                        data-testid="teaching-test-environment-confirm"
                        @click="submit">
                        {{ status?.is_configured ? 'Vollständig löschen' : 'Einrichten' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import axios from 'axios'

export default {
    data() {
        return {
            status: null,
            loading: false,
            submitting: false,
            error: '',
            confirmationOpen: false,
            confirmationText: '',
        }
    },

    computed: {
        confirmationTitle() {
            return this.status?.is_configured
                ? 'Test-Umgebung 2026/27 vollständig löschen?'
                : 'Test-Umgebung 2026/27 einrichten?'
        },
    },

    mounted() {
        this.loadStatus()
    },

    methods: {
        async loadStatus() {
            this.loading = true
            this.error = ''

            try {
                const response = await axios.get('/api/admin/teaching/test-environment')
                this.status = response.data.data
            } catch (error) {
                this.error = this.errorMessage(error)
            } finally {
                this.loading = false
            }
        },
        openConfirmation() {
            this.confirmationText = ''
            this.confirmationOpen = true
            this.error = ''
        },
        closeConfirmation() {
            if (this.submitting) {
                return
            }

            this.confirmationOpen = false
            this.confirmationText = ''
        },
        async submit() {
            if (this.confirmationText !== '2026/27' || !this.status) {
                return
            }

            this.submitting = true
            this.error = ''

            try {
                if (this.status.is_configured) {
                    await axios.delete('/api/admin/teaching/test-environment', {
                        data: { confirmation: this.confirmationText },
                    })
                    this.redirectTo('/admin/teaching')

                    return
                }

                await axios.post('/api/admin/teaching/test-environment', {
                    confirmation: this.confirmationText,
                })
                this.redirectTo('/admin/teaching/testumgebung')
            } catch (error) {
                this.error = this.errorMessage(error)
                this.confirmationOpen = false
            } finally {
                this.submitting = false
            }
        },
        errorMessage(error) {
            return error?.response?.data?.message || 'Die Test-Umgebung konnte nicht geändert werden.'
        },
        redirectTo(path) {
            window.location.assign(path)
        },
    },
}
</script>

<style scoped>
@media (max-width: 600px) {
    .test-environment-title,
    .test-environment-dialog-title {
        align-items: flex-start !important;
        white-space: normal;
    }

    .test-environment-action {
        min-height: 48px;
        width: 100%;
    }

    :deep(.v-chip) {
        height: auto;
        max-width: 100%;
        white-space: normal;
    }

    :deep(.v-chip__content) {
        overflow-wrap: anywhere;
        padding-block: 4px;
        white-space: normal;
    }

    .test-environment-dialog-actions {
        align-items: stretch;
        flex-direction: column;
        gap: 8px;
    }

    .test-environment-dialog-actions :deep(.v-spacer) {
        display: none;
    }

    .test-environment-dialog-actions :deep(.v-btn) {
        margin-inline: 0 !important;
        min-height: 44px;
        width: 100%;
    }
}
</style>
