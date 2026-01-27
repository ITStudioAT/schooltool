<template>
    <!-- SEARCH -->
    <v-col cols="12" md="6" xl="4">
        <ItsGridBox color="primary" title="Suche" icon="mdi-magnify" class="w-100">
            <v-card tile flat color="transparent" class="w-100">
                <v-alert type="info" class="mb-2">Die Suche bezieht sich auf die zuletzt importierten Daten aus Sokrates Bund.</v-alert>
                <v-form ref="form" v-model="is_valid" @submit.prevent="search()">
                    <v-text-field ref="search_string" autofocus clearable density="compact" flat rounded="0" label="Suchbegriff" v-model="search_string" @click:clear="search()" />

                    <v-btn block flat rounded="0" color="primary" type="submit" class="mt-2">Suchen</v-btn>
                </v-form>
            </v-card>
            <v-card tile flat color="transparent" class="w-100">
                <!-- RECORDS -->
                <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_users" color="success-lighten-2" class="search-list">
                    <v-list-item dense v-for="(item, index) in import116" :key="item.id" :value="item.id" :class="{ 'even-row': index % 2 === 1 }">
                        <template v-slot:title>
                            <!-- Schüler:in -->
                            <div class="w-100">
                                <div class="text-body-1 d-flex flex-row align-center justify-space-between w-100 name-email-row">
                                    <div class="d-flex flex-row align-center ga-2 name-email-left">
                                        <div class="d-flex flex-row align-center ga-2">
                                            <div class="font-weight-medium student-name">
                                                {{ item.last_name + ' ' + item.first_name + ' (' + item.class + ')' }}
                                            </div>
                                            <v-icon
                                                size="20"
                                                class="mr-2"
                                                :color="item.sex == 'm' ? 'blue' : item.sex == 'w' ? 'red' : 'yellow'"
                                                :icon="item.sex == 'm' ? 'mdi-gender-male' : item.sex == 'w' ? 'mdi-gender-female' : 'mdi-gender-male-female'" />
                                        </div>
                                    </div>
                                    <div class="text-rigtht text-body-2 name-email-right">{{ item.email }}</div>
                                </div>
                            </div>
                            <!-- Zusatzinfos Schüler:in -->
                            <div class="text-body-1 d-flex flex-row align-center ga-2 w-100">
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.birth_date">
                                    <v-icon icon="mdi-cake" size="x-small" />
                                    <div class="text-body-2">{{ item.birth_date + ' (' + item.age + ')' }}</div>
                                </div>
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.phone_1">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.phone_1 }}</div>
                                </div>
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.phone_2">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.phone_2 }}</div>
                                </div>
                            </div>

                            <!-- Eltern 1 -->
                            <div class="w-100 text-pink-darken-3">
                                <div class="text-body-1 d-flex flex-row align-center justify-space-between w-100 name-email-row">
                                    <div class="d-flex flex-row align-center ga-2 name-email-left">
                                        <div class="d-flex flex-row align-center ga-2">
                                            {{ item.mother_name }}
                                        </div>
                                    </div>
                                    <div class="text-rigtht text-body-2 name-email-right">{{ item.mother_email }}</div>
                                </div>
                            </div>
                            <!-- Zusatzinfos Altern 1 -->
                            <div class="text-body-1 d-flex flex-row align-center ga-2 w-100 text-pink-darken-3">
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.mother_phone_1">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.mother_phone_1 }}</div>
                                </div>
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.mother_phone_2">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.mother_phone_2 }}</div>
                                </div>
                            </div>

                            <!-- Eltern 2 -->
                            <div class="w-100 text-indigo-darken-3">
                                <div class="text-body-1 d-flex flex-row align-center justify-space-between w-100 name-email-row">
                                    <div class="d-flex flex-row align-center ga-2 name-email-left">
                                        <div class="d-flex flex-row align-center ga-2">
                                            {{ item.father_name }}
                                        </div>
                                    </div>
                                    <div class="text-rigtht text-body-2 name-email-right">{{ item.father_email }}</div>
                                </div>
                            </div>
                            <!-- Zusatzinfos Altern 2 -->
                            <div class="text-body-1 d-flex flex-row align-center ga-2 w-100 text-indigo-darken-3">
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.father_phone_1">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.father_phone_1 }}</div>
                                </div>
                                <div class="text-body-1 d-flex flex-row align-center ga-2" v-if="item.father_phone_2">
                                    <v-icon icon="mdi-phone" size="x-small" />
                                    <div class="text-body-2">{{ item.father_phone_2 }}</div>
                                </div>
                            </div>
                        </template>
                    </v-list-item>
                </v-list>

                <!-- PAGINATION-->
                <Pagination20 :meta="meta" :store="teachingStore" index_method="search116" selected_field="selected_import116" />
            </v-card>
        </ItsGridBox>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useTeachingStore } from '@/stores/admin/teaching/TeachingStore'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import Pagination20 from '@/pages/components/Pagination20.vue'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { ItsGridBox, Pagination20 },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.teachingStore = useTeachingStore()
        this.teachingStore.search116()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            is_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'config']),
        ...mapWritableState(useTeachingStore, ['import116', 'search_string', 'selected_import116', 'meta']),
    },

    watch: {},

    methods: {
        search() {
            this.teachingStore.search116()
        },
    },
}
</script>
<style scoped>
.v-text-field :deep(input) {
    font-size: 24px;
}

.even-row {
    background-color: rgba(0, 0, 0, 0.1);
}

.search-list {
    box-shadow: none;
}

.search-list :deep(.v-list-item) {
    box-shadow: none;
}

.name-email-row {
    flex-wrap: wrap;
    row-gap: 2px;
}

.name-email-left {
    min-width: 0;
    flex: 1 1 auto;
}

.name-email-right {
    margin-left: auto;
    text-align: right;
    word-break: break-word;
    max-width: 100%;
    flex: 0 1 auto;
}
</style>
