<template>
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Lehrer" class="w-100">
            <div class="d-flex flex-row align-center justify-space-between text-body-1">
                <div class="font-weight-medium">Aktive Lehrer:</div>
                <div class="d-flex flex-row align-center ga-2">
                    <div>
                        {{ teachers.count_active }}
                    </div>
                </div>
            </div>

            <div class="d-flex flex-row align-center justify-space-between text-body-1">
                <div class="font-weight-medium">Lehrer in Liste:</div>
                <div class="d-flex flex-row align-center ga-2">
                    <div>
                        {{ teachers.count }}
                    </div>
                </div>
            </div>

            <v-card tile flat color="transparent">
                <v-card-text class="d-flex flex-row align-center ga-2">
                    <ItsMenuButton title="Lehrer" subtitle="verwalten" icon="mdi-school" color="primary" @click="main_action = 'teachers'" />
                    <ItsMenuButton title="Lehrerliste" subtitle="verwalten" icon="mdi-view-list" color="primary" @click="main_action = 'teachers_list'" />
                </v-card-text>
                <v-card-text class="text-body-2">Die Lehrerliste dient dazu, festzulegen, welche Personen sich am System als Lehrer:innen anmelden dürfen.</v-card-text>
            </v-card>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { mapWritableState } from 'pinia'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'

export default {
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        this.schoolStore = useSchoolStore()
        const selectedSchoolId = this.config?.selected_school?.id || null
        if (selectedSchoolId) {
            await this.schoolStore.loadSchoolInfos(selectedSchoolId)
        }
    },

    data() {
        return {
            schoolStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['main_action', 'config']),
        ...mapWritableState(useSchoolStore, ['teachers']),
    },
}
</script>
