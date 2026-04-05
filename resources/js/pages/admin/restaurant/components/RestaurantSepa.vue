<template>
    <v-col cols="12" class="restaurant-sepa-shell">
        <ItsGridBox variant="overview" color="primary" title="SEPA Verwaltung" icon="mdi-bank-transfer">
            <div class="pa-3">
                <v-alert
                    class="mb-3"
                    type="info"
                    variant="tonal"
                    border="start">
                    Hier werden alle <strong>Kunden</strong> angezeigt, die das SEPA-Lastschriftmandat online
                    beim Login oder bei der Registrierung bestätigt haben.
                </v-alert>

                <v-progress-linear v-if="isLoading" indeterminate color="primary" class="mb-3" />

                <v-alert v-else-if="errorMessage" type="error" variant="tonal" border="start" class="mb-3">
                    {{ errorMessage }}
                </v-alert>

                <template v-else>
                    <div class="restaurant-sepa__summary mb-3">
                        {{ totalUsers }} Benutzer mit Online-SEPA gefunden
                    </div>

                    <v-card v-if="sepaUsers.length === 0" variant="outlined" class="restaurant-sepa__empty">
                        <v-card-text class="text-body-1">
                            Keine Benutzer mit online bestätigtem SEPA gefunden.
                        </v-card-text>
                    </v-card>

                    <div v-else class="restaurant-sepa__list">
                        <v-card
                            v-for="user in sepaUsers"
                            :key="user.id"
                            variant="outlined"
                            class="restaurant-sepa__card">
                            <v-card-text class="restaurant-sepa__card-content">
                                <div class="restaurant-sepa__header">
                                    <div>
                                        <div class="restaurant-sepa__name">{{ user.name }}</div>
                                        <div class="restaurant-sepa__email">{{ user.email }}</div>
                                    </div>

                                    <v-chip color="primary" variant="tonal" size="small" class="restaurant-sepa__chip">
                                        {{ user.entry_point_label }}
                                    </v-chip>
                                </div>

                                <div class="restaurant-sepa__meta-row">
                                    <div class="restaurant-sepa__meta">
                                        <div v-if="user.schoolclass">
                                            <span class="restaurant-sepa__label">Klasse</span>
                                            <span>{{ user.schoolclass }}</span>
                                        </div>

                                        <div>
                                            <span class="restaurant-sepa__label">Online bestätigt</span>
                                            <span>{{ user.completed_at }}</span>
                                        </div>

                                        <div>
                                            <span class="restaurant-sepa__label">IDENTIFIKATION</span>
                                            <span class="restaurant-sepa__mono">{{ user.flow_uuid }}</span>
                                        </div>
                                    </div>

                                    <div class="restaurant-sepa__actions">
                                        <v-btn
                                            color="primary"
                                            variant="tonal"
                                            size="small"
                                            rounded="lg"
                                            prepend-icon="mdi-printer"
                                            @click="openPrint(user.flow_uuid)">
                                            Drucken
                                        </v-btn>
                                    </div>
                                </div>
                            </v-card-text>
                        </v-card>
                    </div>

                    <div v-if="totalUsers > 0" class="restaurant-sepa__pagination">
                        <div class="text-body-2 text-medium-emphasis">
                            {{ paginationSummary }}
                        </div>

                        <v-pagination
                            v-if="lastPage > 1"
                            v-model="currentPage"
                            :length="lastPage"
                            :total-visible="6"
                            density="comfortable"
                            @update:model-value="handlePageChange" />
                    </div>
                </template>
            </div>
        </ItsGridBox>
    </v-col>
</template>

<script>
import ItsGridBox from '@/pages/components/ItsGridBox.vue'

export default {
    components: { ItsGridBox },

    data() {
        return {
            sepaUsers: [],
            currentPage: 1,
            lastPage: 1,
            totalUsers: 0,
            pageFrom: 0,
            pageTo: 0,
            isLoading: false,
            errorMessage: '',
        }
    },

    mounted() {
        this.loadSepaUsers()
    },

    computed: {
        paginationSummary() {
            if (! this.totalUsers) {
                return '0 - 0 von 0'
            }

            return `${this.pageFrom || 1} - ${this.pageTo || this.sepaUsers.length} von ${this.totalUsers}`
        },
    },

    methods: {
        async handlePageChange(page) {
            await this.loadSepaUsers(page)
        },

        openPrint(flowUuid) {
            if (!flowUuid) {
                return
            }

            const url = `/api/admin/restaurant/sepa-users/${encodeURIComponent(flowUuid)}/print`

            if (typeof window !== 'undefined' && typeof window.open === 'function') {
                window.open(url, '_blank', 'noopener')
            }
        },

        async loadSepaUsers(page = 1) {
            this.isLoading = true
            this.errorMessage = ''

            try {
                const response = await axios.get('/api/admin/restaurant/sepa-users', {
                    params: {
                        page,
                    },
                })
                this.sepaUsers = response?.data?.data || []
                this.currentPage = Number(response?.data?.meta?.current_page || page || 1)
                this.lastPage = Number(response?.data?.meta?.last_page || 1)
                this.totalUsers = Number(response?.data?.meta?.total || 0)
                this.pageFrom = Number(response?.data?.meta?.from || 0)
                this.pageTo = Number(response?.data?.meta?.to || this.sepaUsers.length || 0)
            } catch (error) {
                this.errorMessage = error.response?.data?.message || 'Die SEPA-Benutzer konnten nicht geladen werden.'
                this.sepaUsers = []
                this.currentPage = 1
                this.lastPage = 1
                this.totalUsers = 0
                this.pageFrom = 0
                this.pageTo = 0
            } finally {
                this.isLoading = false
            }
        },
    },
}
</script>

<style scoped>
.restaurant-sepa__summary {
    color: rgb(30, 41, 59);
    font-size: 0.94rem;
    font-weight: 700;
}

.restaurant-sepa__list {
    display: grid;
    gap: 8px;
}

.restaurant-sepa__card {
    border-color: rgba(148, 163, 184, 0.22);
    background: rgba(255, 255, 255, 0.94);
}

.restaurant-sepa__card-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.restaurant-sepa__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}

.restaurant-sepa__name {
    color: rgb(15, 23, 42);
    font-size: 0.98rem;
    font-weight: 800;
}

.restaurant-sepa__email {
    color: rgb(71, 85, 105);
    font-size: 0.85rem;
}

.restaurant-sepa__chip {
    margin-top: 2px;
    flex-shrink: 0;
}

.restaurant-sepa__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 14px;
    flex: 1 1 auto;
    color: rgb(51, 65, 85);
    font-size: 0.84rem;
}

.restaurant-sepa__meta > div {
    display: inline-flex;
    flex-direction: column;
    gap: 2px;
}

.restaurant-sepa__label {
    color: rgb(100, 116, 139);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.restaurant-sepa__mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;
    word-break: break-all;
}

.restaurant-sepa__actions {
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
    flex: 0 0 auto;
}

.restaurant-sepa__meta-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.restaurant-sepa-shell {
    max-width: 960px;
    margin-inline: auto;
    width: 100%;
}

.restaurant-sepa__empty {
    border-color: rgba(148, 163, 184, 0.24);
    background: rgba(255, 255, 255, 0.92);
}

.restaurant-sepa__pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.9rem;
    padding-top: 0.85rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
}
</style>
