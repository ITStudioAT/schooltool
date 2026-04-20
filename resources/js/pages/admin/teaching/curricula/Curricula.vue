<template>
    <v-col cols="12">
        <v-sheet rounded="xl" class="curricula-submenu mb-3 pa-2">
            <div class="curricula-submenu__inner">
                <v-btn
                    v-for="item in visibleSubmenuItems"
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

            </div>
        </v-sheet>

        <div v-if="isResolvingCurriculum" class="curricula-loading text-center py-6">
            <v-progress-circular indeterminate color="primary" size="28" class="mb-2" />
            <div>Curriculum wird geladen...</div>
        </div>

        <CurriculaOverview
            v-else-if="sub_action === 'overview' && !selectedCurriculum"
            @select="openCurriculum" />

        <CurriculumDetail
            v-else-if="sub_action === 'overview' && selectedCurriculum"
            :curriculum="selectedCurriculum"
            @back="closeCurriculum"
            @updated="updateCurriculum" />

        <CurriculaPrint
            v-else-if="sub_action === 'print'"
            :curriculum="selectedCurriculum"
            @back="returnFromPrint" />
    </v-col>
</template>

<script>
import CurriculaOverview from './CurriculaOverview.vue'
import CurriculumDetail from './CurriculumDetail.vue'
import CurriculaPrint from './CurriculaPrint.vue'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

export default {
    name: 'TeachingCurricula',
    components: { CurriculaOverview, CurriculumDetail, CurriculaPrint },
    data() {
        return {
            curriculumStore: useCurriculumStore(),
            sub_action: 'overview',
            selectedCurriculum: null,
            isResolvingCurriculum: false,
            routeSyncToken: 0,
            submenuItems: [
                { key: 'overview', label: 'Übersicht', icon: 'mdi-view-list-outline' },
                { key: 'print', label: 'Ausdruck', icon: 'mdi-printer-outline', requiresCurriculum: true },
            ],
        }
    },
    computed: {
        visibleSubmenuItems() {
            return this.submenuItems.filter((item) => !item.requiresCurriculum || this.selectedCurriculum)
        },
    },
    watch: {
        '$route.query.curriculum': {
            immediate: true,
            async handler(curriculumId) {
                await this.syncSelectedCurriculumFromRoute(curriculumId)
            },
        },
        '$route.query.view': {
            immediate: true,
            handler(view) {
                const validKeys = this.submenuItems.map((i) => i.key)
                this.sub_action = validKeys.includes(view) ? view : 'overview'
            },
        },
    },
    methods: {
        handleSubmenu(key) {
            this.sub_action = key
            this.setViewQuery(key)
            if (key === 'overview') {
                this.closeCurriculum()
            }
        },
        returnFromPrint() {
            this.sub_action = 'overview'
            this.setViewQuery('overview')
        },
        openCurriculum(curriculum) {
            this.selectedCurriculum = curriculum
            this.setCurriculumQuery(curriculum?.id ?? null)
        },
        updateCurriculum(curriculum) {
            if (!this.selectedCurriculum || this.selectedCurriculum.id !== curriculum.id) return
            this.selectedCurriculum = curriculum
            this.setCurriculumQuery(curriculum?.id ?? null)
        },
        closeCurriculum() {
            this.selectedCurriculum = null
            this.setCurriculumQuery(null)
        },
        setViewQuery(view) {
            const nextQuery = { ...this.$route.query }

            if (view && view !== 'overview') {
                nextQuery.view = view
            } else {
                delete nextQuery.view
            }

            this.$router.replace({ query: nextQuery }).catch(() => {})
        },
        setCurriculumQuery(curriculumId) {
            const nextQuery = { ...this.$route.query }

            if (curriculumId) {
                nextQuery.curriculum = String(curriculumId)
            } else {
                delete nextQuery.curriculum
            }

            this.$router.replace({ query: nextQuery }).catch(() => {})
        },
        async syncSelectedCurriculumFromRoute(curriculumId) {
            const normalizedId = Number(curriculumId)
            const token = this.routeSyncToken + 1
            this.routeSyncToken = token

            if (!Number.isInteger(normalizedId) || normalizedId <= 0) {
                this.selectedCurriculum = null
                this.isResolvingCurriculum = false
                return
            }

            if (Number(this.selectedCurriculum?.id || 0) === normalizedId) {
                this.isResolvingCurriculum = false
                return
            }

            this.isResolvingCurriculum = true

            try {
                const curriculum = await this.curriculumStore.show(normalizedId)

                if (this.routeSyncToken !== token) {
                    return
                }

                if (curriculum && Number(curriculum.id || 0) === normalizedId) {
                    this.selectedCurriculum = curriculum
                    return
                }

                this.selectedCurriculum = null
                this.setCurriculumQuery(null)
            } finally {
                if (this.routeSyncToken === token) {
                    this.isResolvingCurriculum = false
                }
            }
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

.curricula-loading {
    color: #cbd5f5;
}
</style>
