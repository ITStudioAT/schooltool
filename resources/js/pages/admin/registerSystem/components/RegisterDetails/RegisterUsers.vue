<template>
    <v-col cols="12" md="6" xl="4">
        <its-grid-box color="primary" title="Benutzer" class="w-100" :disabled="action_2 != ''">
            <div class="d-flex flex-row align-start">
                <v-card tile flat color="transparent" class="w-100">
                    <v-card-text>
                        <!-- SEARCHFIELD -->
                        <SearchField :store="registerUserStore" selected_field="selected_register_users" />

                        <!-- Abwählen / Auswählen-->
                        <v-card tile flat color="transparent" class="d-flex flex-row flex-wrap align-center ga-2 mt-2" :disabled="action_2 != ''">
                            <v-btn color="primary" slim flat tile class="text-caption" @click="selectAll">
                                Alle auswählen [{{ register_users.length - selected_register_users.length }}]
                            </v-btn>
                            <v-btn color="primary" slim flat tile class="text-caption" @click="unselectAll">Alle abwählen [{{ selected_register_users.length }}]</v-btn>
                        </v-card>

                        <!-- RECORDS -->
                        <v-list dense variant="elevated" select-strategy="leaf" v-model:selected="selected_register_users" color="success-lighten-2">
                            <v-list-item v-for="item in register_users" :key="item.id" :value="item.id">
                                <template v-slot:title>
                                    <div class="d-flex flex-row align-center justify-space-between">
                                        <div class="w-100">
                                            <div class="text-body-1">
                                                {{ item.last_name + ' ' + item.first_name + ' (' + item.email + ')' }}
                                            </div>
                                            <div class="text-body-2 d-flex flex-row flex-wrap align-center w-100" v-for="booking in item.registerDateBookings">
                                                <div>{{ booking.student_last_name + ' ' + booking.student_first_name }}</div>
                                                <div class="d-flex flex-row align-center ml-2" v-if="booking.student_birthdate">
                                                    <v-icon size="x-small" icon="mdi-cake" />
                                                    <div class="ml-1">{{ booking.student_birthdate }}</div>
                                                </div>
                                                <template v-for="(sibling, si) in (booking.siblings || [])" :key="si">
                                                    <div class="ml-3 text-medium-emphasis d-flex flex-row align-center">
                                                        <v-icon size="x-small" icon="mdi-account-multiple" class="mr-1" />
                                                        {{ sibling.last_name + ' ' + (sibling.first_name || '') }}
                                                        <span v-if="sibling.birthdate" class="ml-1 d-flex flex-row align-center">
                                                            <v-icon size="x-small" icon="mdi-cake" class="mr-1" />
                                                            {{ sibling.birthdate }}
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </v-list-item>
                        </v-list>

                        <!-- PAGINATION-->
                        <Pagination :meta="meta" :store="registerUserStore" selected_field="selected_register_users" />
                    </v-card-text>
                </v-card>

                <!-- Bereinigen -->
                <v-card tile flat color="transparent" style="width: 150px" class="d-flex flex-column ga-2" v-if="count_deletable_users > 0">
                    <!-- AUSWAHl EGAL -->
                    <div class="text-body-1 font-weight-medium">Bereinigung Benutzer</div>
                    <div class="text-caption">Sie können alle Benutzer löschen, die keine Anmeldungen haben</div>
                    <div>Anzahl: {{ count_deletable_users }}</div>
                    <div class="d-flex flex-column ga-2">
                        <v-btn block tile flat color="warning" class="text-caption" prepend-icon="mdi-vacuum" @click="deleteUsers">Bereinigen</v-btn>
                    </div>
                </v-card>
            </div>
        </its-grid-box>
    </v-col>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'
import SearchField from '@/pages/components/SearchField.vue'
import Pagination from '@/pages/components/Pagination.vue'
import FileUpload from '@/pages/components/FileUpload.vue'

// SPECIFIC

import { useRegisterUserStore } from '@/stores/admin/RegisterUserStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    components: { Pagination, SearchField, ItsMenuButton, ItsGridBox, FileUpload },

    async beforeMount() {
        this.adminStore = useAdminStore()
        this.registerUserStore = useRegisterUserStore()
        this.register_id = this.selected_register.id
        await this.registerUserStore.index()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            registerUserStore: null,
            is_valid: false,
            upload_file: null,
            is_uploading: false,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['action', 'action_2', 'config', 'selected_register']),
        ...mapWritableState(useRegisterUserStore, [
            'register_users',
            'meta',
            'selected_register_users',
            'search_string',
            'data',
            'answer',
            'register_id',
            'count',
            'count_deletable_users',
        ]),
    },

    methods: {
        onUploadStart() {
            this.is_uploading = true
        },

        async deleteUsers() {
            this.action = 'deleteUsers'
            await this.registerUserStore.deleteRegisterUsers()
            await this.registerUserStore.index()
            this.action = ''
        },

        selectAll() {
            this.selected_register_users = this.register_users.map((item) => item.id)
        },
        unselectAll() {
            this.selected_register_users = []
        },
    },
}
</script>
