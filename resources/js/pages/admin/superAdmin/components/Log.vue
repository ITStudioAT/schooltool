<template>
    <v-col cols="12" v-if="is_loaded">
        <its-grid-box color="primary" title="Log-File" class="w-100">
            <v-card tile flat color="transparent" class="w-100">
                <v-card tile flat color="transparent" class="d-flex flex-row ga-2 w-100 mb-2">
                    <its-menu-button subtitle="Löschen" icon="mdi-delete" color="warning" @click="delete_level++" v-if="delete_level == 0" />
                    <its-menu-button subtitle="Löschen" icon="mdi-delete-off" color="success" @click="delete_level = 0" v-if="delete_level == 1" />
                    <its-menu-button subtitle="Löschen" icon="mdi-delete" color="error" @click="deleteLog" v-if="delete_level == 1" />
                </v-card>
                <v-card-text>
                    <div style="white-space: pre-line" v-if="log">
                        {{ log }}
                    </div>
                    <div class="text-h6" v-else>Die Log-Datei ist leer</div>
                </v-card-text>
            </v-card>
        </its-grid-box>
    </v-col>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useLogStore } from '@/stores/admin/LogStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.logStore = useLogStore()
        await this.logStore.getLog()
        this.is_loaded = true
    },

    unmounted() {},

    data() {
        return {
            logStore: null,
            delete_level: 0,
            is_loaded: false,
        }
    },

    computed: {
        ...mapWritableState(useLogStore, ['log']),
    },

    methods: {
        async deleteLog() {
            await this.logStore.deleteLog()
            this.delete_level = 0
        },
    },
}
</script>
