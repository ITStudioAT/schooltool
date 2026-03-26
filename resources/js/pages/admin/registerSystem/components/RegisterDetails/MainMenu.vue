<template>
    <v-sheet rounded="xl" class="rd-nav mb-3" :class="{ 'is-locked': action !== '' }">
        <div class="rd-nav__buttons">
            <v-btn
                rounded="xl"
                :color="main_menu === '' ? 'primary' : 'secondary'"
                :variant="main_menu === '' ? 'flat' : 'tonal'"
                class="rd-nav__button"
                :disabled="action !== ''"
                @click="main_menu = ''">
                <v-icon size="18" icon="mdi-view-dashboard-outline" class="mr-2" />
                <span class="rd-nav__button-copy">
                    <span class="rd-nav__button-title">Übersicht</span>
                    <span class="rd-nav__button-meta">Termine & Aktionen</span>
                </span>
            </v-btn>

            <v-btn
                rounded="xl"
                :color="main_menu === 'register_users' ? 'primary' : 'secondary'"
                :variant="main_menu === 'register_users' ? 'flat' : 'tonal'"
                class="rd-nav__button"
                :disabled="action !== ''"
                @click="main_menu = 'register_users'">
                <v-icon size="18" icon="mdi-account-multiple" class="mr-2" />
                <span class="rd-nav__button-copy">
                    <span class="rd-nav__button-title">Benutzer</span>
                    <span class="rd-nav__button-meta">Angemeldete Personen</span>
                </span>
            </v-btn>
        </div>
    </v-sheet>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.adminStore = useAdminStore()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['main_menu', 'action']),
    },

    watch: {},
    methods: {},
}
</script>

<style scoped>
.rd-nav {
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(30, 41, 59, 0.8);
    padding: 10px;
    display: flex;
    align-items: center;
}

.rd-nav__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rd-nav__button {
    height: 40px !important;
    padding: 0 14px;
    text-transform: none;
    letter-spacing: 0;
    justify-content: flex-start;
}

.rd-nav__button-copy {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.2;
}

.rd-nav__button-title {
    font-weight: 650;
    font-size: 0.92rem;
}

.rd-nav__button-meta {
    font-size: 0.72rem;
    opacity: 0.85;
}

.rd-nav.is-locked {
    opacity: 0.68;
    pointer-events: none;
}
</style>
