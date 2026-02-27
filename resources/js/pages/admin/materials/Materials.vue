<template>
    <v-container fluid class="materials-page ma-0 w-100 pa-2">
        <MaterialsMenu v-model="main_action" :disabled="isMenuLocked" />
        <v-row class="w-100" dense>
            <v-col cols="12" lg="10" xl="9" class="mx-auto">
                <MaterialsOverviewView v-if="main_action === 'overview'" />
                <MaterialsInboxView v-if="main_action === 'inbox'" />
                <MaterialsSharesView v-if="main_action === 'shares'" />
                <MaterialsNewView v-if="main_action === 'new_material'" @menu-lock-change="setMenuLocked" />
                <MaterialsSettingsView
                    v-if="main_action === 'settings'"
                    :initial-selected-action="initialSettingsAction"
                    :initial-selected-subject-action="initialSubjectAction"
                    @menu-lock-change="setMenuLocked" />
                <MaterialsSettingsView
                    v-if="main_action === 'subjects'"
                    standalone
                    initial-selected-action="subjects"
                    @menu-lock-change="setMenuLocked" />
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { useAdminStore } from '@/stores/admin/AdminStore'
import MaterialsMenu from './components/navigation/MaterialsMenu.vue'
import MaterialsOverviewView from './components/views/MaterialsOverviewView.vue'
import MaterialsInboxView from './components/views/MaterialsInboxView.vue'
import MaterialsSharesView from './components/views/MaterialsSharesView.vue'
import MaterialsNewView from './components/views/MaterialsNewView.vue'
import MaterialsSettingsView from './components/views/MaterialsSettingsView.vue'

export default {
    name: 'Materials',
    components: {
        MaterialsMenu,
        MaterialsOverviewView,
        MaterialsInboxView,
        MaterialsSharesView,
        MaterialsNewView,
        MaterialsSettingsView,
    },
    data() {
        return {
            adminStore: null,
            main_action: 'overview',
            isMenuLocked: false,
        }
    },
    beforeMount() {
        this.adminStore = useAdminStore()
        this.applyRouteSelection()
        this.setMenuLocked(false)
    },
    unmounted() {
        this.setMenuLocked(false)
    },
    watch: {
        '$route.query': {
            deep: true,
            handler() {
                this.applyRouteSelection()
            },
        },
        main_action(value) {
            if (value !== 'new_material' && value !== 'settings') {
                this.setMenuLocked(false)
            }
        },
    },
    computed: {
        initialSettingsAction() {
            const value = String(this.$route?.query?.settings_action || '').trim()
            return value || null
        },
        initialSubjectAction() {
            const value = String(this.$route?.query?.subject_action || '').trim()
            return value || null
        },
    },
    methods: {
        applyRouteSelection() {
            if (this.$route?.path !== '/admin/materials') return
            const queryValue = String(this.$route?.query?.main_action || '').trim()
            const allowed = ['overview', 'inbox', 'shares', 'subjects', 'new_material', 'settings']
            if (allowed.includes(queryValue)) {
                this.main_action = queryValue
            }
        },
        setMenuLocked(value) {
            const locked = !!value
            this.isMenuLocked = locked
            if (this.adminStore) {
                this.adminStore.is_navigation_locked = locked
            }
        },
    },
}
</script>

<style scoped>
.materials-page {
    --pumpkin: #fd802e;
    --charcoal: #233d4c;
    --cream: #f8efe7;
    --pumpkin-light: #ff8f42;
    min-height: 100%;
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #182a35 0%, var(--charcoal) 46%, #182a35 100%);
}

.materials-page::before,
.materials-page::after {
    content: '';
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.materials-page::before {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -120px;
    background: var(--pumpkin);
}

.materials-page::after {
    width: 340px;
    height: 340px;
    bottom: -120px;
    left: -130px;
    background: #ff9f5e;
}

:deep(.materials-shell) {
    position: relative;
    z-index: 1;
    border: 1px solid rgba(253, 128, 46, 0.35);
    background: rgba(248, 239, 231, 0.96);
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.2);
    color: var(--charcoal);
}

:deep(.subline) {
    color: #314d5d;
}

</style>
