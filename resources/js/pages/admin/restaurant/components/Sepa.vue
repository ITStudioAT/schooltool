<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="SEPA-Lastschriftmandat" icon="mdi-bank-transfer">
            <template #header-actions>
                <div class="d-flex flex-wrap justify-end ga-2">
                    <template v-if="isEditing">
                        <v-btn
                            icon="mdi-close"
                            variant="text"
                            aria-label="Bearbeitung abbrechen"
                            title="Bearbeitung abbrechen"
                            @click="cancelEdit" />
                        <v-btn
                            icon="mdi-content-save"
                            color="primary"
                            variant="flat"
                            aria-label="SEPA-Einstellungen speichern"
                            title="SEPA-Einstellungen speichern"
                            @click="save" />
                    </template>
                    <v-btn
                        size="small"
                        color="info"
                        variant="tonal"
                        prepend-icon="mdi-file-eye-outline"
                        data-testid="sepa-preview-button"
                        @click="previewSepaForm">
                        Vorschau
                    </v-btn>
                    <v-btn
                        v-if="!isEditing"
                        size="small"
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-pencil"
                        @click="beginEdit">
                        Bearbeiten
                    </v-btn>
                </div>
            </template>

            <div class="pa-4">
                <template v-if="!isEditing">
                    <div class="sepa-field sepa-field--panel mb-4">
                        <div class="text-subtitle-2 text-medium-emphasis mb-1">Sollen Benutzer Online-SEPA bestätigen können?</div>
                        <v-chip
                            size="small"
                            :color="sepaSettings.sepa_online_enabled ? 'success' : 'error'"
                            variant="tonal">
                            {{ sepaSettings.sepa_online_enabled ? 'Ja' : 'Nein' }}
                        </v-chip>
                    </div>
                    <div class="sepa-field sepa-field--panel mb-4">
                        <div class="text-subtitle-2 text-medium-emphasis mb-1">Zahlungsempfänger</div>
                        <div
                            v-if="sepaPayeeHtml"
                            class="text-body-2 sepa-richtext"
                            v-html="sepaPayeeHtml"></div>
                        <div v-else class="text-body-2 text-medium-emphasis">Nicht hinterlegt</div>
                    </div>
                    <div class="sepa-field sepa-field--panel">
                        <div class="text-subtitle-2 text-medium-emphasis mb-1">SEPA-Ermächtigung</div>
                        <div
                            v-if="sepaMandateTextHtml"
                            class="text-body-2 sepa-richtext"
                            v-html="sepaMandateTextHtml"></div>
                        <div v-else class="text-body-2 text-medium-emphasis">Nicht hinterlegt</div>
                    </div>
                </template>

                <v-form v-else ref="sepaForm" v-model="isFormValid" @submit.prevent="save">
                    <v-row dense>
                        <v-col cols="12">
                            <v-switch
                                v-model="form.sepa_online_enabled"
                                label="Sollen Benutzer Online-SEPA bestätigen können?"
                                color="primary"
                                density="comfortable"
                                hide-details />
                        </v-col>
                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">Zahlungsempfänger</div>
                            <ItsRichTextEditor v-model="form.sepa_payee" />
                        </v-col>
                        <v-col cols="12">
                            <div class="text-subtitle-2 mb-2">SEPA-Ermächtigung</div>
                            <ItsRichTextEditor v-model="form.sepa_mandate_text" />
                        </v-col>
                        <v-col cols="12" class="d-flex justify-end">
                            <v-btn color="primary" variant="flat" @click="save">
                                Speichern
                            </v-btn>
                        </v-col>
                    </v-row>
                </v-form>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapState } from 'pinia'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

export default {
    components: { ItsGridBox, ItsRichTextEditor },

    data() {
        return {
            isEditing: false,
            isFormValid: false,
            form: {
                sepa_online_enabled: false,
                sepa_payee: '',
                sepa_mandate_text: '',
            },
        }
    },

    computed: {
        ...mapState(useRestaurantStore, ['sepaSettings']),

        sepaPayeeHtml() {
            return this.normalizeRichTextContent(this.sepaSettings?.sepa_payee)
        },

        sepaMandateTextHtml() {
            return this.normalizeRichTextContent(this.sepaSettings?.sepa_mandate_text)
        },
    },

    methods: {
        beginEdit() {
            this.form.sepa_online_enabled = this.sepaSettings?.sepa_online_enabled === true
            this.form.sepa_payee = this.sepaSettings?.sepa_payee || ''
            this.form.sepa_mandate_text = this.sepaSettings?.sepa_mandate_text || ''
            this.isEditing = true
        },

        cancelEdit() {
            this.isEditing = false
        },

        async save() {
            const restaurantStore = useRestaurantStore()
            const result = await restaurantStore.updateSepaSettings({
                restaurant_sepa_online_enabled: this.form.sepa_online_enabled,
                restaurant_sepa_payee: this.form.sepa_payee,
                restaurant_sepa_mandate_text: this.form.sepa_mandate_text,
            })

            if (result) {
                await restaurantStore.loadSettings()
                this.isEditing = false
            }
        },

        previewSepaForm() {
            window.open('/api/admin/restaurant/sepa-settings/preview', '_blank', 'noopener')
        },

        normalizeRichTextContent(value) {
            const content = String(value || '').trim()

            if (! content) {
                return ''
            }

            const plainText = content
                .replace(/<br\s*\/?>/gi, '\n')
                .replace(/<\/p>/gi, '\n')
                .replace(/<[^>]+>/g, '')
                .replace(/&nbsp;/gi, ' ')
                .trim()

            if (! plainText) {
                return ''
            }

            if (content.includes('<')) {
                return content
            }

            return content
                .split(/\r?\n/)
                .map((line) => `<p>${line || '<br>'}</p>`)
                .join('')
        },
    },
}
</script>

<style scoped>
.sepa-field--panel {
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.78);
    padding: 16px 18px;
}

.sepa-richtext {
    color: rgb(15, 23, 42);
}

.sepa-richtext :deep(p) {
    margin: 0 0 0.6em;
}

.sepa-richtext :deep(p:last-child) {
    margin-bottom: 0;
}

.sepa-richtext :deep(p:empty),
.sepa-richtext :deep(p > br:only-child) {
    min-height: 1em;
}
</style>
