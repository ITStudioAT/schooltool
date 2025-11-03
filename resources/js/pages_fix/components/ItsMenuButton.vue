<template>
    <v-card flat rounded="0" width="150" height="70" :color="color ? color : 'primary'"
        class="d-flex flex-column align-center justify-center">
        <div class="text-body-2 font-weight-medium" v-if="title">{{ title }}</div>
        <v-icon size="x-small" :icon="icon" v-if="icon" />
        <div class="text-caption" v-if="subtitle">{{ subtitle }}</div>
    </v-card>
</template>


<script>
export default {
    props: ['title', 'subtitle', 'icon', 'color'],

    data() {
        return {

        };
    },

    methods: {

    }

}
</script>
