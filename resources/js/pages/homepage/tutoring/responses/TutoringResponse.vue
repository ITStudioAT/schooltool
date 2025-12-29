<template>
    <v-container class="d-flex flex-column align-center justify-center w-100 h-100">
        <!-- Überschrift SCHOOLTOOL / NACHHILFETOOL-->

        <v-card flat tile class="mt-4">
            <div class="d-flex flex-row justify-center">
                <div class="text-h6">
                    <span class="text-secondary">NACH</span>
                    <span class="text-third font-weight-bold">HILFE</span>
                    <span class="text-secondary">TOOL</span>
                </div>
            </div>
        </v-card>
        <v-card flat tile color="primary">
            <v-card-title v-if="title">{{ title }}</v-card-title>
            <v-card-subtitle>{{ subtitle }}</v-card-subtitle>
            <v-card-text style="white-space: pre-line">
                {{ text }}
            </v-card-text>
            <v-card-text class="text-h6 text-secondary" v-if="status">
                {{ status }}
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
export default {
    components: {},

    async beforeMount() {},

    async mounted() {},

    unmounted() {},

    data() {
        return {
            title: this.$route.query.title || null,
            subtitle: this.$route.query.subtitle || null,
            text: this.$route.query.text || null,
            status: this.$route.query.status || null,
        }
    },

    computed: {},

    watch: {},

    methods: {},
}
</script>
