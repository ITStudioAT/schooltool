<template>
    <v-container fluid class="ma-0 w-100 pa-2">
        <v-row class="w-100" no-gutters v-if="config">
            <v-col cols="12" md="6" lg="4" xl="3">
                <its-grid-box color="primary" title="🟢 System läuft ordnungsgemäß" class="h-100 w-100">
                    <v-card tile flat color="primary">
                        <v-card-title>Angemeldeter Benutzer</v-card-title>
                        <v-card-text class="text-body-1">
                            {{ config?.user?.last_name + ' ' + config?.user?.first_name }}
                        </v-card-text>
                        <v-card-text class="text-body-2">
                            <div class="d-flex flex-row">
                                <span class="text-decoration-underline">Rollen</span>
                                <span>:</span>
                            </div>
                            <div v-for="role in user(config?.user?.id)?.roles" :key="role">{{ role }}</div>
                        </v-card-text>
                    </v-card>

                    <v-card tile flat color="primary" class="mt-4">
                        <v-card-title>Admins</v-card-title>
                        <v-card-text>
                            <div v-for="admin in school_admins">
                                <div class="d-flex flex-row flex-wrap align-center ga-2">
                                    <div class="text-body-1">{{ admin.last_name + ' ' + admin.first_name }}</div>
                                    <div class="text-caption">({{ admin.roles.join(', ') }})</div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <v-card tile flat color="primary" class="mt-4">
                        <v-card-title>Lizenzen</v-card-title>
                        <v-card-text>
                            <div v-for="licence in school_licences">
                                <div class="d-flex flex-row flex-wrap align-center justify-space-between">
                                    <div class="d-flex flex-row flex-wrap align-center ga-2">
                                        <div v-if="new Date(licence.valid_until) >= new Date()">🟢</div>
                                        <div v-if="new Date(licence.valid_until) < new Date()">🔴</div>
                                        <div class="text-body-1">{{ licence.name }}</div>
                                    </div>
                                    <div class="text-body-2">
                                        <div v-if="new Date(licence.valid_until) >= new Date()">aktiv bis: {{ licence.valid_until }}</div>
                                        <div v-if="new Date(licence.valid_until) < new Date()">abgelaufen seit: {{ licence.valid_until }}</div>
                                    </div>
                                </div>
                                <div class="text-caption font-italic" v-if="licence.long_name">{{ licence.long_name }}</div>
                                <div class="text-body-2 text-right">{{ 'Kosten pro Jahr: EUR ' + licence.price_per_year }}</div>
                            </div>
                        </v-card-text>
                    </v-card>
                </its-grid-box>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolStore = useSchoolStore()
        await this.schoolStore.loadSchoolInfos(this.config?.selected_school?.id)
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        ...mapWritableState(useSchoolStore, ['school_licences', 'school_admins']),
    },

    methods: {
        user(id) {
            return this.school_admins.find((a) => a.id === id)
        },
    },
}
</script>
