<template>
    <v-col cols="12">
        <v-sheet rounded="xl" class="curricula-submenu mb-3 pa-2">
            <div class="curricula-submenu__inner">
                <v-btn
                    v-for="item in submenuItems"
                    :key="item.key"
                    size="small"
                    rounded="xl"
                    :variant="sub_action === item.key ? 'flat' : 'tonal'"
                    :color="sub_action === item.key ? 'primary' : 'secondary'"
                    class="curricula-submenu__btn"
                    @click="sub_action = item.key">
                    <v-icon size="16" :icon="item.icon" class="mr-2" />
                    {{ item.label }}
                </v-btn>
            </div>
        </v-sheet>

        <CurriculaOverview v-if="sub_action === 'overview'" />
    </v-col>
</template>

<script>
import CurriculaOverview from './CurriculaOverview.vue'

export default {
    name: 'TeachingCurricula',
    components: { CurriculaOverview },
    data() {
        return {
            sub_action: 'overview',
            submenuItems: [
                { key: 'overview', label: 'Übersicht', icon: 'mdi-view-list-outline' },
            ],
        }
    },
}
</script>

<style scoped>
.curricula-submenu {
    border: 1px solid rgba(99, 102, 241, 0.2);
    background: rgba(15, 23, 42, 0.7);
}

.curricula-submenu__inner {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.curricula-submenu__btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
}
</style>
