<template>
    <v-app-bar flat color="primary" v-if="isVisible">
        <template #prepend>
            <v-btn icon="mdi-menu-open" v-if="!isDrawerOpen" @click="isDrawerOpen = true" />
            <img
                :src="`${selectedSchoolLogoSrc}?t=${Date.now()}`"
                alt="Logo"
                height="60px"
                class="pl-2"
                v-if="selectedSchoolLogoSrc" />
        </template>
        <template #title>
            {{ title }}
        </template>
    </v-app-bar>
</template>

<script>
export default {
    props: {
        modelValue: {
            type: Boolean,
            required: true,
        },
        isVisible: {
            type: Boolean,
            required: true,
        },
        selectedSchoolLogoSrc: {
            type: String,
            default: null,
        },
        title: {
            type: String,
            default: '',
        },
    },

    emits: ['update:modelValue'],

    computed: {
        isDrawerOpen: {
            get() {
                return this.modelValue
            },
            set(value) {
                this.$emit('update:modelValue', value)
            },
        },
    },
}
</script>
