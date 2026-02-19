<template>
    <v-card class="materials-shell pa-4 pa-md-8" rounded="xl" elevation="0">
        <div class="text-h4 font-weight-bold mb-2">Einstellungen</div>
        <div class="text-subtitle-1 subline mb-4">Wähle einen Bereich aus.</div>

        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap ga-2 w-100 mb-5">
            <v-btn
                v-for="item in visibleMenuItems"
                :key="`materials-settings-${item.value}`"
                rounded="pill"
                size="small"
                variant="flat"
                :prepend-icon="item.icon"
                :class="[
                    'settings-menu-btn',
                    { 'settings-menu-btn--active': selectedAction === item.value },
                ]"
                @click="selectedAction = item.value">
                {{ item.label }}
            </v-btn>
        </v-card>

        <v-card variant="outlined" class="pa-4">
            <div class="text-h6 font-weight-bold mb-2">{{ selectedItemLabel }}</div>
            <div class="text-body-2 text-medium-emphasis mb-3">
                Diese Typen gelten nur für deine eigenen Materialien.
            </div>

            <v-alert v-if="!normalizedTypeOptions.length" type="info" variant="tonal" class="mb-3">
                Noch keine Materialtypen vorhanden.
            </v-alert>

            <div v-else class="d-flex flex-wrap ga-2 mb-3">
                <v-chip
                    v-for="option in normalizedTypeOptions"
                    :key="`settings-material-type-${option.id || option.value}`"
                    size="small"
                    variant="tonal"
                    color="primary"
                    prepend-icon="mdi-shape-outline">
                    {{ option.label }}
                </v-chip>
            </div>

            <v-btn
                v-if="canManageTypeValues"
                color="primary"
                variant="flat"
                prepend-icon="mdi-shape-outline"
                @click="typeManagerDialogOpen = true">
                Materialtypen verwalten
            </v-btn>
        </v-card>

        <MaterialTypeManagerDialog v-model="typeManagerDialogOpen" />
    </v-card>
</template>

<script>
import { useMaterialCardStore } from '@/stores/admin/materials/MaterialCardStore'
import MaterialTypeManagerDialog from '../forms/MaterialTypeManagerDialog.vue'

export default {
    name: 'MaterialsSettingsView',
    components: {
        MaterialTypeManagerDialog,
    },
    data() {
        return {
            materialCardStore: null,
            selectedAction: 'materials_types',
            typeManagerDialogOpen: false,
            menuItems: [
                { value: 'materials_types', label: 'Materialtypen', icon: 'mdi-shape-outline' },
            ],
        }
    },
    async beforeMount() {
        this.materialCardStore = useMaterialCardStore()
        if (!this.materialCardStore?.config) {
            await this.materialCardStore.loadConfig()
        }
    },
    computed: {
        visibleMenuItems() {
            return this.menuItems
        },
        selectedItemLabel() {
            const selected = this.visibleMenuItems.find((item) => item.value === this.selectedAction)
            if (selected) return selected.label
            return this.visibleMenuItems[0]?.label || 'Einstellungen'
        },
        normalizedTypeOptions() {
            const list = Array.isArray(this.materialCardStore?.config?.type_values)
                ? this.materialCardStore.config.type_values
                : []

            return list
                .map((option) => {
                    if (!option || typeof option !== 'object') return null
                    const id = Number(option.id)
                    const value = String(option.value || '').trim()
                    const label = String(option.label || value).trim()
                    if (!value || !label) return null
                    return {
                        id: Number.isFinite(id) && id > 0 ? id : null,
                        value,
                        label,
                    }
                })
                .filter(Boolean)
        },
        canManageTypeValues() {
            return this.materialCardStore?.config?.can_manage_type_values === true
        },
    },
    watch: {
        visibleMenuItems: {
            immediate: true,
            handler(items) {
                if (!Array.isArray(items) || !items.length) {
                    this.selectedAction = ''
                    return
                }
                if (!items.some((item) => item.value === this.selectedAction)) {
                    this.selectedAction = items[0].value
                }
            },
        },
    },
}
</script>

<style scoped>
.settings-menu-btn {
    border: 1px solid rgba(35, 61, 76, 0.22);
    background: rgba(248, 239, 231, 0.65);
    color: #233d4c;
    font-weight: 700;
}

.settings-menu-btn--active {
    border-color: rgba(253, 128, 46, 0.9);
    background: linear-gradient(155deg, #fd802e 0%, #ff8f42 100%);
    color: #233d4c;
}
</style>
