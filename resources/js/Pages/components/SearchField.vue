<template>
    <v-form ref="form" v-model="is_valid" @submit.prevent="search">
        <div class="d-flex flex-row align-start">
            <v-text-field
                clearable
                autofocus
                v-model="store.search_string"
                label="Suche"
                :rules="[maxLength(255)]"
                @click:clear="search" />
            <v-btn
                flat
                tile
                class="mt-1 ml-2"
                color="primary"
                variant="outlined"
                icon="mdi-magnify"
                type="submit"
                @click="search" />
        </div>
    </v-form>
</template>
<script>
import { useValidationRulesSetup } from '@/helpers/rules'
export default {
    setup() {
        return useValidationRulesSetup()
    },

    props: ['store', 'selected_field'],
    components: {},

    async beforeMount() {},

    unmounted() {},

    data() {
        return {
            is_valid: false,
        }
    },

    computed: {},

    methods: {
        async search() {
            this.is_valid = false
            await this.$refs.form.validate()
            if (!this.is_valid) return
            this.store[this.selected_field] = []
            await this.store.index()
        },
    },
}
</script>
