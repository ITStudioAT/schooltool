<template>
    <!-- SUCHOPTIONEN -->
    <!-- nur, wenn Suchoptionen übergeben wurden -->
    <!-- nur, wenn nicht SHOW ausgewählt wurde-->
    <div class="d-flex flex-row flex-wrap align-center ga-2 mb-2 bg-secondary pa-2"
        v-if="search_options.length > 0 && !slots.show">
        <div v-for="(search_option, i) in search_options" :key="i">
            <v-btn-toggle mandatory v-if="search_option.type === 'toggle'" v-model="search_model[search_option.field]"
                density="compact" divided tile @update:model-value="onSearchInput">
                <v-btn class="text-caption" v-for="(option, j) in search_option.options" :key="j" :value="option.value"
                    :color="search_model[search_option.field] === option.value ? 'success' : undefined">
                    {{ option.name }}
                </v-btn>
            </v-btn-toggle>
        </div>
    </div>

    <v-card flat rounded="0" :color="color ? color : 'secondary'" style="overflow: visible;">
        <!-- Überschrift -->
        <v-card-title>
            <div class="d-flex flex-row ga-2">
                <v-icon :icon="icon" v-if="icon" />
                <div>{{ title }}</div>
            </div>
        </v-card-title>
        <div v-if="!slots.show">
            <!-- Suchfeld -->
            <v-card-text v-if="show_search_field">
                <v-text-field clearable density="comfortable" append-icon="mdi-magnify" hide-details
                    v-model="search_model.search_string" @keydown.enter="onSearchInput" @click:append="onSearchInput"
                    @click:clear="onSearchInput"></v-text-field>
            </v-card-text>

            <slot name="menu" :selected_items="modelStore.selected_items" v-if="!!slots.menu" />

            <v-card-text>
                <v-list density="compact" :select-strategy="multiple ? 'classic' : 'single-leaf'"
                    style="overflow: visible;" v-model:selected="modelStore.selected_items">
                    <!-- Alle Einträge -->
                    <v-list-item density="compact" :value="item" v-for="(item, i) in items" class="align-center"
                        :class="i % 2 == 0 ? '' : 'bg-secondary-lighten-2'" color="success">
                        <v-row dense class="align-center">
                            <v-col cols="10" class="py-0">
                                <v-row dense no-gutters class="mt-1">
                                    <slot name="content" :item="item" />
                                </v-row>
                            </v-col>
                            <!-- Menü -->
                            <v-col cols="2" class="d-flex justify-end py-0">
                                <v-list-item-action v-if="!!slots.actions">
                                    <v-speed-dial contained location="bottom left" transition="false">
                                        <template v-slot:activator="{ props: activatorProps }">
                                            <v-btn size="x-small" rounded="0" v-bind="activatorProps" flat
                                                variant="text" icon="mdi-dots-vertical"></v-btn>
                                        </template>
                                        <slot name="actions" :item="item" />
                                    </v-speed-dial>
                                </v-list-item-action>
                            </v-col>
                        </v-row>
                    </v-list-item>
                </v-list>
            </v-card-text>

            <!-- PAGINATION -->
            <v-card-text v-if="modelStore.pagination" class="mt-0 pt-0">
                <div class="d-flex flex-row align-center justify-space-between text-body-2 mb-2">
                    <div>{{ 'Gesamt: ' + modelStore.pagination.total }}</div>
                    <div>Seite {{ modelStore.pagination.current_page + ' von ' + modelStore.pagination.last_page }}
                    </div>
                    <div>{{ modelStore.pagination.per_page + ' pro Seite' }}</div>
                </div>
                <div class="d-flex flex-row align-center justify-space-between">

                    <div class="d-flex flex-row align-center ga-2">
                        <v-btn flat size="small" variant="tonal" icon="mdi-chevron-double-left"
                            :disabled="!modelStore.pagination.prev_page"
                            @click="index(model, search_model, modelStore.pagination.first_page)" />
                        <v-btn flat size="small" variant="tonal" icon="mdi-chevron-left"
                            :disabled="!modelStore.pagination.prev_page"
                            @click="index(model, search_model, modelStore.pagination.prev_page)" />
                    </div>
                    <div>

                    </div>
                    <div class="d-flex flex-row align-center ga-2">
                        <v-btn flat size="small" variant="tonal" icon="mdi-chevron-right"
                            :disabled="!modelStore.pagination.next_page"
                            @click="index(model, search_model, modelStore.pagination.next_page)" />
                        <v-btn flat size="small" variant="tonal" icon="mdi-chevron-double-right"
                            :disabled="!modelStore.pagination.next_page"
                            @click="index(model, search_model, modelStore.pagination.last_page)" />
                    </div>

                </div>
            </v-card-text>
        </div>

        <!-- Anzeige eines Datensatzes -->
        <v-card-text v-if="!!slots.show">
            <slot name="show" />
        </v-card-text>

    </v-card>
</template>

<script>
import { mapWritableState } from "pinia";
import { useModelStore } from "@/stores/admin/ModelStore";
import { useSlots } from 'vue';


export default {
    emits: ['saved'],
    props: ['title', 'color', 'icon', 'search_options', 'model', 'multiple', 'data', 'data_multiple', 'save_action', 'destroy_action', 'destroy_multiple_action', 'select_all', 'show_search_field', 'reload'],

    async beforeMount() {
        this.modelStore = useModelStore(); this.modelStore.initialize(this.$router);
        this.slots = useSlots();
        await this.changeSearchOptions();
    },

    mounted() {

    },

    data() {
        return {
            search_model: {},
            modelStore: null,
            slots: null,
        };
    },

    computed: {
        ...mapWritableState(useModelStore, ['selected_items']),
    },

    watch: {

        reload: {
            handler(newVal, oldVal) {

                this.modelStore.reload();
            },
            deep: true
        },


        search_options: {
            handler(newVal, oldVal) {
                this.changeSearchOptions();
            },
            deep: true
        },

        save_action: {
            handler(newVal, oldVal) {
                this.save(this.model, this.data, this.modelStore.pagination.current_page);
            },
            deep: true
        },

        destroy_action: {
            handler(newVal, oldVal) {
                this.destroy(this.model, this.data, this.modelStore.pagination.current_page);
            },
            deep: true
        },

        destroy_multiple_action: {
            handler(newVal, oldVal) {
                this.destroyMultiple(this.model, this.data_multiple, this.modelStore.pagination.current_page);
            },
            deep: true
        },

        select_all: {
            handler(newVal, oldVal) {
                if (newVal) {
                    this.modelStore.selected_items = this.items;
                } else {
                    this.modelStore.selected_items = [];
                }
            },
            deep: true
        },


    },

    computed: {
        ...mapWritableState(useModelStore, ['items', 'pagination']),
    },


    methods: {

        async changeSearchOptions() {
            if (this.search_options) {
                for (const searchOption of this.search_options) {
                    if (searchOption.type === 'toggle') {
                        const defaultOption = searchOption.options.find((o) => o.default);
                        this.search_model[searchOption.field] = defaultOption
                            ? defaultOption.value
                            : null;
                    }
                }
            }
            await this.index(this.model, this.search_model, 1);

        },

        async onSearchInput() {
            await this.index(this.model, this.search_model);
        },

        async index(model, search_model, page = 1) {
            await this.modelStore.index(model, search_model, page);
        },

        async save(model, data, page = 1) {
            this.selected_items = [];
            if (data.id) {
                // update
                await this.modelStore.update(model, data);
                // await this.index(model, this.search_model, page); 2025-05-06
                await this.modelStore.reload();
                this.$emit('updated');
            } else {
                // store
                await this.modelStore.store(model, data);
                await this.index(model, this.search_model, page);
                this.$emit('stored');
            }
        },

        async destroy(model, data, page = 1) {
            await this.modelStore.destroy(model, data);
            await this.index(model, this.search_model, page);
            this.selected_items = [];
            this.$emit('destroyed');
        },

        async destroyMultiple(model, data, page = 1) {
            await this.modelStore.destroyMultiple(model, data.map(item => item.id));
            await this.index(model, this.search_model, page);
            this.selected_items = [];
            this.$emit('multiple_destroyed');
        },


    }
};
</script>