<template>
    <v-container fluid class="materials-page ma-0 w-100 pa-2">
        <MaterialsMenu v-model="main_action" :disabled="isMenuLocked" />
        <v-row class="w-100" dense>
            <v-col cols="12" lg="10" xl="9" class="mx-auto">
                <MaterialsOverviewView v-if="main_action === 'overview'" />
                <MaterialsNewView v-if="main_action === 'new_material'" @menu-lock-change="setMenuLocked" />
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { useAdminStore } from '@/stores/admin/AdminStore'
import MaterialsMenu from './components/navigation/MaterialsMenu.vue'
import MaterialsOverviewView from './components/views/MaterialsOverviewView.vue'
import MaterialsNewView from './components/views/MaterialsNewView.vue'

export default {
    name: 'Materials',
    components: {
        MaterialsMenu,
        MaterialsOverviewView,
        MaterialsNewView,
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
        this.setMenuLocked(false)
    },
    unmounted() {
        this.setMenuLocked(false)
    },
    watch: {
        main_action(value) {
            if (value !== 'new_material') {
                this.setMenuLocked(false)
            }
        },
    },
    methods: {
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
