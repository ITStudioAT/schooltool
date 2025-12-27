<template>
    <v-app>
        <v-layout>
            <v-main>
                <router-view />
                <ItsNotification />
                <v-overlay :model-value="is_loading > 0" class="align-center justify-center" contained opacity="0.1">
                    <!-- <v-progress-circular indeterminate size="70" width="7" /> -->
                    <!--
                    <v-progress-circular indeterminate size="small" />
                    -->
                    <!--
                    <v-progress-linear indeterminate stream buffer-value="0" color="primary" />
                    -->

                    <div class="loading-squares">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
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
<style>
.loading-squares {
    display: flex;
    gap: 8px;
}
.loading-squares span {
    width: 12px;
    height: 12px;
    animation: pulse 1.4s infinite ease-in-out both;
}
.loading-squares span:nth-child(1) {
    background: #f39200; /* rot */
    animation-delay: -0.32s;
}
.loading-squares span:nth-child(2) {
    background: #3aaa35; /* grün */
    animation-delay: -0.16s;
}
.loading-squares span:nth-child(3) {
    background: #37474f; /* blau */
    animation-delay: 0s;
}

@keyframes pulse {
    0%,
    80%,
    100% {
        transform: scale(0);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
