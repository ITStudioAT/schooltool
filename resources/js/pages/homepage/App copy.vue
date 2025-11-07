<template>
    <v-app>
        <v-layout class="bg-background">
            <v-main>
                <router-view />
                <ItsNotification />

                <!-- overlay spinner instead of removing the router-view -->
                <v-overlay :model-value="is_loading > 0" class="align-center justify-center" contained>
                    <v-progress-circular indeterminate size="70" width="7" />
                </v-overlay>
            </v-main>

            <v-footer app>
                <v-row justify="center" no-gutters>
                    <v-col cols="12" class="text-center">
                        <v-btn text variant="text" to="/homepage/impressum">Impressum</v-btn>
                    </v-col>
                </v-row>
            </v-footer>
        </v-layout>
    </v-app>
</template>

<script setup>
import ItsNotification from '@/pages/components/ItsNotification.vue'
</script>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore()
    },

    unmounted() {},

    data() {
        return {
            homepageStore: null,
        }
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'error', 'school', 'licence', 'is_loading']),
    },

    methods: {},
}
</script>
