<template>
    <!-- BEHAVIOUR OVERVIEW -->
    <ItsGridBox variant="overview" v-if="action !== 'teaching_behaviour_new_or_edit'" color="primary" title="Verhalten" icon="mdi-account-alert" class="w-100" :disabled="action != ''">
        <!-- HEADER ACTIONS -->
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn v-if="!is_editing" icon="mdi-pencil" size="x-small" color="primary" variant="flat" @click="is_editing = true" />
            <v-btn v-if="is_editing" icon="mdi-check" size="x-small" color="success" variant="flat" @click="exitEditMode" />
        </div>

        <!-- NEUEN EINTRAG ANLEGEN -->
        <v-card v-if="is_editing" tile flat color="transparent" class="mt-4">
            <ItsMenuButton title="Eintrag" subtitle="anlegen" icon="mdi-plus-circle-multiple" color="primary" @click="newEntry" />
        </v-card>

        <!-- ALLE EINTRÄGE ANZEIGEN -->
        <v-list density="compact" class="bg-transparent">
            <v-list-item v-for="(entry, index) in behaviour_entries" :key="index" class="px-0">
                <div class="d-flex flex-row align-center justify-space-between w-100">
                    <div class="text-body-1 font-weight-medium">{{ entry.short_name }} - {{ entry.name }}</div>
                    <div v-if="is_editing" class="d-flex flex-row align-center ga-1">
                        <v-btn flat tile size="x-small" color="warning" icon="mdi-delete" @click="startDelete(index)" v-if="delete_index !== index" />
                        <v-btn flat tile size="x-small" color="success" icon="mdi-delete-off" @click="delete_index = null" v-if="delete_index === index" />
                        <v-btn flat tile size="x-small" color="error" icon="mdi-delete" @click="deleteEntry(index)" v-if="delete_index === index" />
                        <v-btn flat tile size="x-small" color="primary" icon="mdi-pencil" @click="editEntry(index)" v-if="delete_index !== index" />
                    </div>
                </div>
                <v-divider class="mt-2" />
            </v-list-item>
        </v-list>

        <v-alert v-if="!behaviour_entries.length" type="info" variant="tonal" class="mt-2">
            Noch keine Verhaltens-Einträge vorhanden.
        </v-alert>
    </ItsGridBox>

    <!-- EDIT/NEW ENTRY FORM -->
    <ItsGridBox variant="overview" color="primary" :title="edit_index !== null ? 'Eintrag ändern' : 'Neuer Eintrag'" icon="mdi-account-alert" class="w-100 mt-4" v-if="action == 'teaching_behaviour_new_or_edit'">
        <div class="d-flex flex-row align-center justify-end mt-2 ga-2">
            <v-btn icon="mdi-check" size="x-small" color="success" variant="flat" :disabled="!is_valid" @click="save" />
            <v-btn icon="mdi-close" size="x-small" color="warning" variant="flat" @click="abort" />
        </div>
        <v-card tile flat color="transparent" class="w-100">
            <v-card-text>
                <v-form ref="form" v-model="is_valid" @submit.prevent="save" class="mb-4">
                    <div class="text-caption text-text">Bitte geben Sie die Felder ein (* = Pflichtfeld)</div>
                    <v-text-field autofocus v-model="data.short_name" label="Kurzzeichen (z. B. E für Ermahnung)*" :rules="[required(), maxLength(10)]" />
                    <v-text-field v-model="data.name" label="Bezeichnung *" :rules="[required(), maxLength(255)]" />
                </v-form>
            </v-card-text>
        </v-card>
    </ItsGridBox>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, ItsMenuButton },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        await this.teachingStore.loadSettings()
    },

    data() {
        return {
            adminStore: null,
            teachingStore: null,
            is_valid: false,
            is_editing: false,
            data: {
                short_name: '',
                name: '',
            },
            edit_index: null,
            delete_index: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action']),
        ...mapWritableState(useTeachingStore, ['settings']),
        behaviour_entries() {
            const entries = this.settings?.teaching_behaviour || []
            return [...entries].sort((a, b) => (a.short_name || '').localeCompare(b.short_name || '', 'de'))
        },
    },

    watch: {
        'data.short_name'(val) {
            if (val && val !== val.toUpperCase()) {
                this.data.short_name = val.toUpperCase()
            }
        },
    },

    methods: {
        newEntry() {
            this.data = { short_name: '', name: '' }
            this.edit_index = null
            this.is_editing = true
            this.action = 'teaching_behaviour_new_or_edit'
        },

        editEntry(index) {
            const entry = this.behaviour_entries[index]
            this.data = { ...entry }
            this.edit_index = index
            this.is_editing = true
            this.action = 'teaching_behaviour_new_or_edit'
        },

        abort() {
            this.action = ''
            this.edit_index = null
            this.is_editing = true
        },

        exitEditMode() {
            this.is_editing = false
            this.delete_index = null
        },

        startDelete(index) {
            this.delete_index = index
        },

        async save() {
            const entries = [...this.behaviour_entries]

            if (this.edit_index !== null) {
                entries[this.edit_index] = { ...this.data }
            } else {
                entries.push({ ...this.data })
            }

            await this.teachingStore.saveSettings({ teaching_behaviour: entries })
            this.action = ''
            this.edit_index = null
            this.is_editing = true
        },

        async deleteEntry(index) {
            const entries = [...this.behaviour_entries]
            entries.splice(index, 1)

            await this.teachingStore.saveSettings({ teaching_behaviour: entries })
            this.delete_index = null
        },
    },
}
</script>
