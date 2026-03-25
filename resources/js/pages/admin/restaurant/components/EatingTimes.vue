<template>
    <v-col cols="12">
        <ItsGridBox variant="overview" color="primary" title="Speisezeiten" icon="mdi-clock-outline">
            <template #header-actions>
                <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-plus" @click="openDialog">
                    Neue Speisezeit
                </v-btn>
            </template>

            <v-alert v-if="!eatingTimeStore.eatingTimeCount" type="info" variant="tonal" class="mb-3">
                Noch keine Speisezeiten vorhanden.
            </v-alert>

            <v-list v-else density="comfortable" class="bg-transparent pa-0">
                <v-list-item
                    v-for="entry in eatingTimeStore.sortedEatingTimes"
                    :key="entry.id"
                    :title="`${entry.eating_time} Uhr`">
                    <template #prepend>
                        <v-icon icon="mdi-clock-time-four-outline" class="mr-2" />
                    </template>
                    <template #append>
                        <div class="d-flex ga-1">
                            <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="openEditDialog(entry)" />
                            <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="openDeleteDialog(entry)" />
                        </div>
                    </template>
                </v-list-item>
            </v-list>
        </ItsGridBox>

        <v-dialog v-model="formDialog" max-width="400" persistent>
            <v-card rounded="xl">
                <v-card-title>{{ editingId ? 'Speisezeit bearbeiten' : 'Neue Speisezeit' }}</v-card-title>

                <v-card-text>
                    <v-form ref="formRef" v-model="isFormValid" @submit.prevent="save">
                        <v-text-field
                            v-model="timeInput"
                            label="Uhrzeit"
                            placeholder="z.B. 11:30"
                            variant="outlined"
                            density="comfortable"
                            autofocus
                            hint="Format: HH:MM (z.B. 11:30 oder 12:45)"
                            persistent-hint
                            :rules="[required(), timeFormat()]" />
                    </v-form>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeDialog">Abbrechen</v-btn>
                    <v-btn color="primary" variant="flat" @click="save">
                        {{ editingId ? 'Speisezeit aktualisieren' : 'Speisezeit speichern' }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="deleteDialog" max-width="400" persistent>
            <v-card rounded="xl">
                <v-card-title>Speisezeit löschen</v-card-title>

                <v-card-text>
                    <div class="text-body-1">
                        Soll <strong>{{ pendingDelete?.eating_time }} Uhr</strong> wirklich gelöscht werden?
                    </div>
                </v-card-text>

                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" @click="closeDeleteDialog">Abbrechen</v-btn>
                    <v-btn color="error" variant="flat" @click="confirmDelete">Löschen</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useEatingTimeStore } from '@/stores/admin/restaurant/EatingTimeStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox },

    data() {
        return {
            formDialog: false,
            deleteDialog: false,
            isFormValid: false,
            timeInput: '',
            editingId: null,
            pendingDelete: null,
        }
    },

    computed: {
        eatingTimeStore() {
            return useEatingTimeStore()
        },
    },

    created() {
        if (! this.eatingTimeStore.isLoaded) {
            this.eatingTimeStore.load()
        }
    },

    methods: {
        timeFormat() {
            return (value) => /^\d{2}:\d{2}$/.test(value) || 'Format: HH:MM (z.B. 11:30)'
        },
        openDialog() {
            this.editingId = null
            this.timeInput = ''
            this.isFormValid = false
            this.formDialog = true
        },
        openEditDialog(entry) {
            this.editingId = entry.id
            this.timeInput = entry.eating_time
            this.isFormValid = false
            this.formDialog = true
        },
        closeDialog() {
            this.formDialog = false
            this.editingId = null
            this.timeInput = ''
        },
        async save() {
            this.isFormValid = false
            await this.$refs.formRef?.validate()

            if (! this.isFormValid) {
                return
            }

            const store = useEatingTimeStore()
            const result = this.editingId
                ? await store.update(this.editingId, this.timeInput)
                : await store.store(this.timeInput)

            if (result) {
                this.closeDialog()
            }
        },
        openDeleteDialog(entry) {
            this.pendingDelete = entry
            this.deleteDialog = true
        },
        closeDeleteDialog() {
            this.deleteDialog = false
            this.pendingDelete = null
        },
        async confirmDelete() {
            if (! this.pendingDelete) {
                return
            }

            await useEatingTimeStore().destroy(this.pendingDelete.id)
            this.closeDeleteDialog()
        },
    },
}
</script>
