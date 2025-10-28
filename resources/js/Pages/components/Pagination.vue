<template>
    <div class="text-caption d-flex flex-row align-center justify-space-between">
        <div>{{ meta.from + ' - ' + meta.to + ' von ' + meta.total }}</div>
        <div>{{ 'Seite ' + meta.current_page + ' von ' + meta.last_page }}</div>
    </div>

    <div class="d-flex flex-row align-center justify-space-between">
        <div>
            <v-btn
                flat
                tile
                class="mt-1 ml-2"
                color="primary"
                variant="outlined"
                icon="mdi-page-first"
                @click="firstPage"
                :disabled="meta.current_page == 1" />
        </div>
        <div>
            <v-btn
                flat
                tile
                class="mt-1 ml-2"
                color="primary"
                variant="outlined"
                icon="mdi-page-previous-outline"
                @click="prevPage"
                :disabled="meta.current_page == 1" />
        </div>
        <div>
            <v-btn
                flat
                tile
                class="mt-1 ml-2"
                color="primary"
                variant="outlined"
                icon="mdi-page-next-outline"
                @click="nextPage"
                :disabled="meta.current_page == meta.last_page" />
        </div>
        <div>
            <v-btn
                flat
                tile
                class="mt-1 ml-2"
                color="primary"
                variant="outlined"
                icon="mdi-page-last"
                @click="lastPage"
                :disabled="meta.current_page == meta.last_page" />
        </div>
    </div>
</template>
<script>
export default {
    props: ['meta', 'store', 'selected_field'],
    components: {},

    async beforeMount() {},

    unmounted() {},

    data() {
        return {}
    },

    computed: {},

    methods: {
        async firstPage() {
            this.store[this.selected_field] = []
            await this.store.index(1)
        },
        async prevPage() {
            this.store[this.selected_field] = []
            await this.store.index(this.store.meta.current_page - 1)
        },
        async nextPage() {
            this.store[this.selected_field] = []
            await this.store.index(this.store.meta.current_page + 1)
        },
        async lastPage() {
            this.store[this.selected_field] = []
            await this.store.index(this.store.meta.last_page)
        },
    },
}
</script>
