<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <v-row class="mb-6" align="center">
            <v-col cols="12">
                <div class="text-h4 font-weight-bold mb-2">Neues Material</div>
                <div class="text-subtitle-1 subline">Wähle eine passende Möglichkeit: Button, Dateiablage oder Link.</div>
            </v-col>
        </v-row>

        <v-row v-if="!createFormOpen" dense>
            <v-col cols="12" md="4">
                <MaterialsAddOption @add-material="openCreateForm" />
            </v-col>

            <v-col cols="12" md="4">
                <MaterialsDropOption />
            </v-col>

            <v-col cols="12" md="4">
                <MaterialsLinkOption />
            </v-col>
        </v-row>

        <v-expand-transition>
            <div v-if="createFormOpen" class="mt-6">
                <MaterialsCreateInlineForm
                    :title="createForm.title"
                    :description="createForm.description"
                    :is-saving="isSaving"
                    @update:title="createForm.title = $event"
                    @update:description="createForm.description = $event"
                    @save="saveNewMaterial"
                    @cancel="cancelCreateForm" />
            </div>
        </v-expand-transition>
    </v-card>
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import MaterialsAddOption from '../options/MaterialsAddOption.vue'
import MaterialsDropOption from '../options/MaterialsDropOption.vue'
import MaterialsLinkOption from '../options/MaterialsLinkOption.vue'
import MaterialsCreateInlineForm from '../forms/MaterialsCreateInlineForm.vue'

export default {
    name: 'MaterialsNewView',
    emits: ['menu-lock-change'],
    components: {
        MaterialsAddOption,
        MaterialsDropOption,
        MaterialsLinkOption,
        MaterialsCreateInlineForm,
    },
    data() {
        return {
            materialCardStore: null,
            createFormOpen: false,
            isSaving: false,
            createForm: {
                title: '',
                description: '',
            },
        }
    },
    beforeMount() {
        this.materialCardStore = useMaterialCardStore()
    },
    unmounted() {
        this.$emit('menu-lock-change', false)
    },
    methods: {
        openCreateForm() {
            this.createFormOpen = true
            this.$emit('menu-lock-change', true)
        },
        cancelCreateForm() {
            this.createFormOpen = false
            this.$emit('menu-lock-change', false)
            this.resetCreateForm()
        },
        resetCreateForm() {
            this.createForm = {
                title: '',
                description: '',
            }
        },
        async saveNewMaterial() {
            const title = String(this.createForm.title || '').trim()
            if (!title || this.isSaving) return

            this.isSaving = true
            const description = String(this.createForm.description || '').trim()

            const saved = await this.materialCardStore.quickStore({
                title,
                source_type: 'note',
                source_text: description || null,
            })

            this.isSaving = false

            if (saved) {
                this.cancelCreateForm()
            }
        },
    },
}
</script>
