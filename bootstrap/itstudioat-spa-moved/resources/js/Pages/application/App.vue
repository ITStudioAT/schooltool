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
                        <v-btn text variant="text">Impressum</v-btn>
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
import { useApplicationStore } from "@/stores/application/ApplicationStore";


export default {

    components: {},

    async beforeMount() {
        this.applicationStore = useApplicationStore(); this.applicationStore.initialize(this.$router);
    },

    unmounted() {
    },

    data() {
        return {
            applicationStore: null,
        };
    },

    computed: {
        ...mapWritableState(useApplicationStore, ['is_loading', 'error']),

    },

    methods: {

    }

}
</script>
