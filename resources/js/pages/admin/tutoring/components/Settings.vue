<template>
    <v-col cols="12" md="8" xl="6" v-if="data">
        <section class="admin-card ai-glass-panel tutoring-settings-card" :class="{ 'is-disabled': action != '' && action != 'edit_settings' }">
            <div class="admin-card-head mb-3">
                <div class="tov-card-heading">
                    <div class="admin-card-eyebrow">Nachhilfe</div>
                    <h2 class="admin-card-title">Einstellungen</h2>
                </div>
            </div>

            <div class="kpi-sub mb-4">Grundkonfiguration für Benutzer-Freigabe und Angebotsregeln im Nachhilfe-Tool.</div>

            <!-- ANZEIGE EINSTELLUNGEN -->
            <div class="tutoring-settings-summary" v-if="action == ''">
                <div class="tutoring-settings-row">
                    <div class="tutoring-settings-row__label">Neuer Benutzer muss bestätigt werden?</div>
                    <div class="tutoring-settings-row__value">
                        <span class="tutoring-settings-badge" :class="data.tutoring_student_must_be_confirmed ? 'is-yes' : 'is-no'">
                            {{ data.tutoring_student_must_be_confirmed ? 'Ja' : 'Nein' }}
                        </span>
                    </div>
                </div>

                <div class="tutoring-settings-row" v-if="data.tutoring_student_must_be_confirmed">
                    <div class="tutoring-settings-row__label">E-Mail für Freigabe</div>
                    <div class="tutoring-settings-row__value tutoring-settings-row__value--mail">{{ data.tutoring_confirmer_email }}</div>
                </div>

                <div class="tutoring-settings-row">
                    <div class="tutoring-settings-row__label">Max. gleichzeitige Angebote pro Schüler:in</div>
                    <div class="tutoring-settings-row__value">
                        <span class="tutoring-settings-number">{{ data.tutoring_max_offers_per_student }}</span>
                        <span class="tutoring-settings-muted" v-if="Number(data.tutoring_max_offers_per_student) === 0"> (unbegrenzt)</span>
                    </div>
                </div>

                <div class="tutoring-settings-row">
                    <div class="tutoring-settings-row__label">Angebote für andere Schulen sichtbar</div>
                    <div class="tutoring-settings-row__value">
                        <span class="tutoring-settings-badge" :class="data.may_visible_for_other_schools ? 'is-yes' : 'is-no'">
                            {{ data.may_visible_for_other_schools ? 'Ja' : 'Nein' }}
                        </span>
                    </div>
                </div>

                <div class="tutoring-settings-card-menu">
                    <v-btn rounded="lg" color="primary" variant="tonal" prepend-icon="mdi-cog-edit" @click="editSettings">
                        Einstellungen ändern
                    </v-btn>
                </div>
            </div>

            <!-- ÄNDERN EINSTELLUNGEN -->
            <v-form
                v-if="action == 'edit_settings'"
                ref="form"
                v-model="is_valid"
                class="tutoring-settings-form"
                @submit.prevent="saveTutoringSettings(data_new)">
                <div class="tutoring-settings-form-section">
                    <v-switch label="Neuer Benutzer muss bestätigt werden?" v-model="data_new.tutoring_student_must_be_confirmed" color="success" tabindex="1" />

                    <v-text-field
                        v-model="data_new.tutoring_confirmer_email"
                        label="E-Mail dessen, der bestätigt"
                        :rules="[required(), mail()]"
                        v-if="data_new.tutoring_student_must_be_confirmed"
                        tabindex="1" />
                </div>

                <div class="tutoring-settings-form-section">
                    <v-text-field
                        v-model="data_new.tutoring_max_offers_per_student"
                        label="Anzahl gleichzeitiger Angebote pro Schüler:in (0 = unbegrenzt)"
                        :rules="[required(), min(0)]"
                        tabindex="2" />

                    <v-switch label="Angebote auch für andere Schulen?" v-model="data_new.may_visible_for_other_schools" color="success" tabindex="3" />
                </div>

                <div class="tutoring-settings-card-menu">
                    <v-btn color="warning" rounded="lg" prepend-icon="mdi-close" @click="abortSettings">Abbruch</v-btn>
                    <v-btn color="success" rounded="lg" prepend-icon="mdi-content-save" type="submit" tabindex="4">Speichern</v-btn>
                </div>
            </v-form>

            <div class="tutoring-settings-muted" v-if="action != '' && action != 'edit_settings'">
                Ein anderer Bereich ist gerade aktiv.
            </div>
        </section>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolToolStore } from '@/stores/admin/SchoolToolStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: {},

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolToolStore = useSchoolToolStore()
        await this.schoolToolStore.loadConfig()
        this.action = ''
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolToolStore: null,
            is_valid: false,
            data_new: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useSchoolToolStore, ['data']),
    },

    methods: {
        async saveTutoringSettings(data) {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            if (!(await this.schoolToolStore.saveTutoringSettings(data))) return
            this.action = ''
        },

        editSettings() {
            this.data_new = JSON.parse(JSON.stringify(this.data))
            this.action = 'edit_settings'
        },
        abortSettings() {
            this.action = ''
        },
    },
}
</script>

<style scoped src="@/../css/admin-index-page.css"></style>
<style scoped src="@/../css/admin-tutoring-overview-cards.css"></style>
<style scoped src="@/../css/admin-tutoring-settings-card.css"></style>
