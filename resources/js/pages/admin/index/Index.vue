<template>
    <v-container fluid class="ma-0 w-100 pa-2" v-if="config.is_auth">
        <v-row class="w-100" no-gutters v-if="config">
            <v-col cols="12" md="6" xl="4">
                <its-grid-box color="primary" class="h-100 w-100">
                    <template #title>
                        <div class="d-flex flex-row align-center justify-space-between w-100">
                            <div>Schooltool</div>
                            <div class="text-caption">Version: {{ config?.version }}</div>
                        </div>
                    </template>
                    <v-card tile flat color="primary">
                        <v-card-text class="text-caption text-sm-body-1">
                            <v-row no-gutters="" dense>
                                <v-col cols="4">
                                    Status
                                    <v-btn
                                        icon="mdi-refresh"
                                        class="ml-2"
                                        flat
                                        tile
                                        size="x-small"
                                        @click="runTests"
                                        variant="outlined"
                                        :disabled="queue_test_status == 'running'" />
                                </v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="test_step != 999">
                                        <div>
                                            Tests aktiv
                                            <v-icon size="small" icon="mdi-dots-circle mdi-spin" />
                                        </div>
                                    </div>
                                    <div v-if="test_step == 999">fertig geprüft</div>
                                </v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="test_step == 999">
                                        <v-icon icon="mdi-circle " color="success" v-if="all_tests_result == 1" />
                                        <v-icon icon="mdi-circle " color="error" v-if="all_tests_result != 1" />
                                    </div>
                                </v-col>
                            </v-row>

                            <v-row no-gutters="" dense>
                                <v-col cols="4">Warteschlange</v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="queue_test_status == 'waiting'">Test wartend</div>
                                    <div v-if="queue_test_status == 'running'">
                                        Test aktiv
                                        <v-icon size="small" icon="mdi-dots-circle mdi-spin" />
                                    </div>
                                    <div v-if="queue_test_status == 'finished'">fertig geprüft</div>
                                </v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="queue_test_status == 'finished'">
                                        <v-icon icon="mdi-checkbox-marked " color="success" v-if="queue_test_result == 1" />
                                        <v-icon icon="mdi-checkbox-marked" color="error" v-if="queue_test_result != 1" />
                                    </div>
                                </v-col>
                            </v-row>

                            <v-row no-gutters="" dense>
                                <v-col cols="4">Cron-Job (1-2 min.)</v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="cron_test_status == 'waiting'">Test wartend</div>
                                    <div v-if="cron_test_status == 'running'">
                                        Test aktiv
                                        <v-icon size="small" icon="mdi-dots-circle mdi-spin" />
                                    </div>
                                    <div class="text-body-2" v-if="cron_test_status == 'finished'">{{ cron_status?.health_at }}</div>
                                </v-col>
                                <v-col cols="4" class="text-right">
                                    <div v-if="cron_test_status == 'finished'">
                                        <v-icon icon="mdi-checkbox-marked " color="success" v-if="cron_test_result == 1" />
                                        <v-icon icon="mdi-checkbox-marked" color="error" v-if="cron_test_result != 1" />
                                    </div>
                                </v-col>
                            </v-row>
                        </v-card-text>
                    </v-card>

                    <!-- BENUTZER -->
                    <v-card tile flat color="primary" class="mt-4">
                        <v-card-title>Angemeldeter Benutzer</v-card-title>
                        <v-card-text class="text-body-1">
                            {{ config?.user?.last_name + ' ' + config?.user?.first_name }}
                        </v-card-text>
                        <v-card-text class="text-body-2">
                            <div class="d-flex flex-row">
                                <span class="text-decoration-underline">Rollen</span>
                                <span>:</span>
                            </div>
                            <div v-for="role in config.user.roles" :key="role">{{ role }}</div>
                        </v-card-text>
                    </v-card>

                    <!-- ADMINS -->
                    <v-card tile flat color="primary" class="mt-4">
                        <v-card-title>Admins</v-card-title>
                        <v-card-text>
                            <div v-for="admin in school_admins">
                                <div class="d-flex flex-row flex-wrap align-center ga-2">
                                    <div class="text-body-1">{{ admin.last_name + ' ' + admin.first_name }}</div>
                                    <div class="text-caption">({{ admin.roles.join(', ') }})</div>
                                </div>
                            </div>
                        </v-card-text>
                    </v-card>

                    <!-- LIZENZEN -->
                    <v-card tile flat color="primary" class="mt-4">
                        <v-card-title>Lizenzen</v-card-title>
                        <v-card-text>
                            <div v-for="licence in school_licences">
                                <div class="d-flex flex-row flex-wrap align-center justify-space-between">
                                    <div class="d-flex flex-row flex-wrap align-center ga-2">
                                        <div v-if="new Date(licence.valid_until) >= new Date()"><v-icon icon="mdi-circle " color="success" /></div>
                                        <div v-if="new Date(licence.valid_until) < new Date()"><v-icon icon="mdi-circle " color="error" /></div>
                                        <div class="text-body-1">{{ licence.name }}</div>
                                    </div>
                                    <div class="text-body-2">
                                        <div v-if="new Date(licence.valid_until) >= new Date()">aktiv bis: {{ licence.valid_until }}</div>
                                        <div v-if="new Date(licence.valid_until) < new Date()">abgelaufen seit: {{ licence.valid_until }}</div>
                                    </div>
                                </div>
                                <div class="text-caption font-italic" v-if="licence.long_name">{{ licence.long_name }}</div>
                                <div class="text-body-2 text-right">{{ 'Kosten pro Jahr: EUR ' + licence.price_per_year }}</div>
                            </div>
                        </v-card-text>
                    </v-card>
                </its-grid-box>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolStore } from '@/stores/admin/SchoolStore'
import { useHealthStore } from '@/stores/admin/HealthStore'
import ItsMenuButton from '@/pages/components/ItsMenuButton.vue'
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsMenuButton, ItsGridBox },

    async beforeMount() {
        await axios.get('/sanctum/csrf-cookie')
        this.adminStore = useAdminStore()
        this.healthStore = useHealthStore()
        this.schoolStore = useSchoolStore()
        if (this.config?.is_auth) await this.schoolStore.loadSchoolInfos(this.config?.selected_school?.id)
        if (this.config?.is_auth) this.runTests()
    },

    unmounted() {},

    data() {
        return {
            adminStore: null,
            healthStore: null,
            schoolStore: null,
            test_step: 0,
            all_tests_result: 0,
            queue_test_status: 'waiting',
            queue_test_result: 0,
            cron_test_status: 'waiting',
            cron_test_result: 0,
        }
    },

    computed: {
        ...mapWritableState(useAdminStore, ['config', 'health']),
        ...mapWritableState(useHealthStore, ['data', 'data_2', 'cron_status']),
        ...mapWritableState(useSchoolStore, ['school_licences', 'school_admins']),
    },

    methods: {
        isAllowed(roles) {
            return this.config.user.roles.some((role) => roles.includes(role))
        },
        async runTests() {
            // Cron-Job-Status
            this.cron_test_status = 'running'
            await this.healthStore.checkCronStatus()
            this.cron_test_result = this.cron_status.is_healthy
            this.cron_test_status = 'finished'

            this.test_step = 0
            this.all_tests_result = 0
            this.queue_test_status = 'waiting'
            this.queue_test_result = 0

            this.queue_test_status = 'running'
            await this.healthStore.testQueue()
            // Mehrmals prüfen bis completed
            let attempts = 0
            let maxAttempts = 10
            let status = null
            let is_completed = false

            this.queue_test_result = 999
            while (attempts < maxAttempts && !is_completed) {
                status = await this.healthStore.checkQueueStatus(this.data.testId)

                if (status.is_completed) {
                    this.queue_test_result = 1
                    break
                } else {
                    await new Promise((resolve) => setTimeout(resolve, 1000)) // 1 Sekunde warten
                    attempts++
                }
            }
            this.queue_test_status = 'finished'

            this.all_tests_result = 1
            if (this.queue_test_result != 1 || this.cron_test_result != 1) this.all_tests_result = 999
            this.test_step = 999
        },

        user(id) {
            return this.school_admins.find((a) => a.id === id)
        },
    },
}
</script>
