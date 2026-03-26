<template>
    <v-sheet rounded="xl" class="schoolyears-nav mb-3" :class="{ 'is-locked': action !== '' }">
        <div class="schoolyears-nav__label">Schuljahr</div>
        <div class="schoolyears-nav__buttons">
            <v-btn
                v-for="schoolyear in schoolyears"
                :key="schoolyear.id"
                rounded="xl"
                :color="schoolyear.id === selected_schoolyear?.id ? 'success' : 'secondary'"
                :variant="schoolyear.id === selected_schoolyear?.id ? 'flat' : 'tonal'"
                class="schoolyears-nav__button"
                :disabled="action !== ''"
                @click="setActiveSchoolyear(schoolyear)">
                <v-icon size="16" :icon="schoolyear.id === selected_schoolyear?.id ? 'mdi-check-circle-outline' : 'mdi-calendar-month-outline'" class="mr-2" />
                {{ schoolyear.name }}
            </v-btn>
        </div>
    </v-sheet>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolyearStore } from '@/stores/admin/SchoolyearStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.schoolyearStore = useSchoolyearStore()
        await this.schoolyearStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            schoolyearStore: null,
            is_valid: false,
            data: {},
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'selected_school', 'selected_schoolyear', 'selected_register', 'action']),
        ...mapWritableState(useSchoolyearStore, ['schoolyears']),
    },

    methods: {
        abort() {
            this.action = ''
            this.data = {}
        },

        async setActiveSchoolyear(schoolyear) {
            await this.schoolyearStore.setActiveSchoolyear(schoolyear.id)
            await this.adminStore.loadConfig()
            this.selected_schoolyear = schoolyear
            this.selected_register = null
        },
    },
}
</script>

<style scoped>
.schoolyears-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px 12px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.schoolyears-nav__label {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
    flex-shrink: 0;
}

.schoolyears-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.schoolyears-nav__button {
    height: 32px !important;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
    font-size: 0.88rem;
}

.schoolyears-nav.is-locked {
    opacity: 0.68;
    pointer-events: none;
}
</style>
