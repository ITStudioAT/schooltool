<template>
    <v-container fluid class="pa-2">
        <AdminPageHeader class="mb-3" location="Helpers" :section="activePanelLabel" />

        <v-sheet class="pa-2 mb-3 rounded-lg" border>
            <div class="d-flex flex-wrap ga-2" role="group" aria-label="Helpers-Bereiche">
                <v-btn
                    variant="flat"
                    :color="activePanel === 'klassensprecherwahl' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'klassensprecherwahl'"
                    prepend-icon="mdi-account-group-outline"
                    @click="selectPanel('klassensprecherwahl')">
                    Klassensprecherwahl
                </v-btn>
                <v-btn
                    variant="flat"
                    :color="activePanel === 'matura' ? 'primary' : undefined"
                    :aria-pressed="activePanel === 'matura'"
                    prepend-icon="mdi-school-outline"
                    @click="selectPanel('matura')">
                    Matura
                </v-btn>
            </div>
        </v-sheet>

        <v-card variant="outlined" rounded="lg">
            <v-card-text>
                <h2 class="text-h6 mb-2">{{ activePanelLabel }}</h2>
                <p class="mb-0">Dieser Bereich ist in Vorbereitung.</p>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import AdminPageHeader from '@/pages/admin/components/AdminPageHeader.vue'

export default {
    components: { AdminPageHeader },

    computed: {
        activePanel() {
            return this.$route.query.panel === 'matura' ? 'matura' : 'klassensprecherwahl'
        },
        activePanelLabel() {
            return this.activePanel === 'matura' ? 'Matura' : 'Klassensprecherwahl'
        },
    },

    methods: {
        selectPanel(panel) {
            if (this.activePanel === panel) return

            const query = { ...this.$route.query }
            if (panel === 'matura') {
                query.panel = 'matura'
            } else {
                delete query.panel
            }

            this.$router.push({ path: this.$route.path, query })
        },
    },
}
</script>
