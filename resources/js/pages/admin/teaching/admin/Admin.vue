<template>
    <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 my-2 ml-1">
        <ItsMenuButton
            subtitle="Import 116"
            :icon="show_import116 ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_import116 ? 'success' : 'secondary'"
            @click="show_import116 = !show_import116" />
        <ItsMenuButton
            v-if="canManageSchoolHolidays"
            subtitle="Ferien"
            :icon="show_holidays ? 'mdi-eye' : 'mdi-eye-off'"
            :color="show_holidays ? 'success' : 'secondary'"
            @click="show_holidays = !show_holidays" />
    </v-card>

    <Import116 v-if="show_import116" />
    <Holidays v-if="canManageSchoolHolidays && show_holidays" />
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import Import116 from './import116/Import116.vue'
import Holidays from './holidays/Holidays.vue'

export default {
    components: { ItsMenuButton, Import116, Holidays },

    data() {
        return {
            show_import116: true,
            show_holidays: true,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config']),
        canManageSchoolHolidays() {
            const roles = this.config?.roles || []
            return roles.includes('admin') || roles.includes('super_admin') || roles.includes('teaching_admin')
        },
    },
}
</script>
