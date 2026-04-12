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
                    @click="handleSubmenu(item.key)">
                    <v-icon size="16" :icon="item.icon" class="mr-2" />
                    {{ item.label }}
                </v-btn>

                <v-chip
                    v-if="selectedCurriculum"
                    size="small"
                    color="primary"
                    variant="tonal"
                    closable
                    class="ml-2 font-weight-bold"
                    @click:close="closeCurriculum">
                    <v-icon size="14" class="mr-1">mdi-book-education-outline</v-icon>
                    {{ selectedCurriculum.title }}
                </v-chip>
            </div>
        </v-sheet>

        <CurriculaOverview
            v-if="!selectedCurriculum"
            @select="openCurriculum" />

        <CurriculumDetail
            v-else
            :curriculum="selectedCurriculum"
            @back="closeCurriculum"
            @updated="updateCurriculum" />
    </v-col>
</template>

<script>
import CurriculaOverview from './CurriculaOverview.vue'
import CurriculumDetail from './CurriculumDetail.vue'

export default {
    name: 'TeachingCurricula',
    components: { CurriculaOverview, CurriculumDetail },
    data() {
        return {
            sub_action: 'overview',
            selectedCurriculum: null,
            submenuItems: [
                { key: 'overview', label: 'Übersicht', icon: 'mdi-view-list-outline' },
            ],
        }
    },
    methods: {
        handleSubmenu(key) {
            this.sub_action = key
            this.selectedCurriculum = null
        },
        openCurriculum(curriculum) {
            this.selectedCurriculum = curriculum
        },
        updateCurriculum(curriculum) {
            if (!this.selectedCurriculum || this.selectedCurriculum.id !== curriculum.id) return
            this.selectedCurriculum = curriculum
        },
        closeCurriculum() {
            this.selectedCurriculum = null
        },
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
    align-items: center;
}

.curricula-submenu__btn {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
}
</style>
