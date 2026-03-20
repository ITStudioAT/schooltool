<template>

    <v-app>
        <!-- Alle Dinge sind geladen -->
        <v-layout v-if="is_loading == 0" class="bg-background">
            <v-main>
                <router-view></router-view>
                <ItsNotification />
            </v-main>

            <v-footer app>
                <v-row justify="center" no-gutters>
                    <v-col cols="12" class="text-center">
                        <v-btn text variant="text">Impressum homepage</v-btn>
                    </v-col>
                </v-row>

            </v-footer>
        </v-layout>

        <!-- Es wird aktuell etwas geladen-->
        <v-container class="d-flex justify-center align-center" style="height: 100vh;" v-if="is_loading > 0">
            <v-progress-circular indeterminate size="70" width="7"></v-progress-circular>
        </v-container>

    </v-app>



</template>

<script setup>
import ItsNotification from "@/pages/components/ItsNotification.vue";
</script>

<script>
import { mapWritableState } from "pinia";
import { useHomepageStore } from "@/stores/homepage/HomepageStore";

export default {

    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore(); this.homepageStore.initialize(this.$router);
        this.homepageStore.config();
    },

    unmounted() {
    },

    data() {
        return {
            homepageStore: null,
        };
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config', 'is_loading', 'error']),

    },

    methods: {

    }

}
</script>
