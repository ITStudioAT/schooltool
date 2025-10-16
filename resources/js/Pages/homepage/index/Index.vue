<template>
    <v-container
        fluid
        class="h-100 w-100 d-flex align-center justify-center bg-background"
    >
        <v-card class="mx-auto w-100" max-width="600" tile flat color="primary">
            <v-card-title class="d-flex flex-row align-center">
                <img src="/storage/images/logo.png" alt="Logo" class="logo" />
                <div class="ml-2">Hello World</div>
            </v-card-title>
            <v-card-text> </v-card-text>
        </v-card>
    </v-container>
</template>
<script>
import { mapWritableState } from "pinia";
import { useHomepageStore } from "@/stores/homepage/HomepageStore";
export default {
    components: {},

    async beforeMount() {
        this.homepageStore = useHomepageStore();
    },

    unmounted() {},

    data() {
        return {
            homepageStore: null,
            is_more_content: false,
        };
    },

    computed: {
        ...mapWritableState(useHomepageStore, [
            "config",
            "is_loading",
            "error",
        ]),
    },

    methods: {},
};
</script>
<style scoped>
.logo {
    display: block;
    max-height: 64px; /* or 2em, relative to font size */
    height: auto;
    width: auto;
    object-fit: contain;
}
</style>
