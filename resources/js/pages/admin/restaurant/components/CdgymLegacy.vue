<template>
    <v-col cols="12">
        <v-card class="cdgym-legacy-card" rounded="lg" elevation="0">
            <div class="cdgym-legacy-card__header">
                <div>
                    <div class="cdgym-legacy-card__eyebrow">Alte Version</div>
                    <h2 class="cdgym-legacy-card__title">CDGYM Mittagessen auf cdgym.info</h2>
                </div>
            </div>

            <v-divider class="my-4" />

            <div class="cdgym-legacy-card__stats">
                <div
                    v-for="stat in liveStats"
                    :key="stat.key"
                    class="cdgym-legacy-card__stat">
                    <v-icon :icon="stat.icon" size="22" color="primary" />
                    <div>
                        <div class="cdgym-legacy-card__stat-value">
                            <span v-if="isLoadingStats">...</span>
                            <span v-else>{{ stat.value }}</span>
                        </div>
                        <div class="cdgym-legacy-card__stat-label">{{ stat.label }}</div>
                    </div>
                </div>
            </div>

            <v-alert
                v-if="statsError"
                class="mt-4"
                type="error"
                variant="tonal"
                density="comfortable">
                {{ statsError }}
            </v-alert>

            <div v-if="statsLoadedAt" class="cdgym-legacy-card__loaded-row">
                <div class="cdgym-legacy-card__loaded-at">
                    Live-Daten geladen: {{ statsLoadedAt }}
                </div>

                <v-btn
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-refresh"
                    :loading="isLoadingStats"
                    :disabled="isLoadingStats"
                    @click="loadLegacyStats">
                    Aktualisieren
                </v-btn>
            </div>

            <v-divider class="my-4" />

            <section class="cdgym-legacy-card__comparison">
                <div>
                    <div class="cdgym-legacy-card__section-label">Vergleich mit der App</div>
                    <h3 class="cdgym-legacy-card__section-title">Würden bei einem Update geändert</h3>
                </div>

                <div class="cdgym-legacy-card__comparison-grid">
                    <div
                        v-for="item in updatePreviewStats"
                        :key="item.key"
                        class="cdgym-legacy-card__comparison-item">
                        <label class="cdgym-legacy-card__checkbox">
                            <input
                                v-model="selectedImportItems"
                                type="checkbox"
                                :value="item.importKey"
                                :disabled="isImporting || isLoadingStats">
                            <span>Übernehmen</span>
                        </label>
                        <div class="cdgym-legacy-card__comparison-body">
                            <v-icon :icon="item.icon" size="22" color="primary" />
                            <div>
                                <div class="cdgym-legacy-card__comparison-value">
                                    <span v-if="isLoadingStats">...</span>
                                    <span v-else>{{ item.value }}</span>
                                </div>
                                <div class="cdgym-legacy-card__comparison-label">{{ item.label }}</div>
                                <div class="cdgym-legacy-card__comparison-detail">{{ item.detail }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="hasSelectedImportItems" class="cdgym-legacy-card__import-row">
                    <v-btn
                        color="primary"
                        variant="flat"
                        prepend-icon="mdi-database-import-outline"
                        :loading="isImporting"
                        :disabled="isImporting || isLoadingStats"
                        @click="importSelectedItems">
                        Importieren
                    </v-btn>
                </div>

                <v-alert
                    v-if="importMessage"
                    type="success"
                    variant="tonal"
                    density="comfortable">
                    {{ importMessage }}
                </v-alert>

                <v-alert
                    v-if="importError"
                    type="error"
                    variant="tonal"
                    density="comfortable">
                    {{ importError }}
                </v-alert>
            </section>
        </v-card>
    </v-col>
</template>

<script>
export default {
    data() {
        return {
            legacyStats: null,
            isLoadingStats: false,
            statsError: '',
            selectedImportItems: [],
            isImporting: false,
            importMessage: '',
            importError: '',
        }
    },

    computed: {
        liveStats() {
            const stats = this.legacyStats || {}

            return [
                {
                    key: 'foods',
                    label: 'Anzahl Speisen',
                    value: this.formatCount(stats.foods_count),
                    icon: 'mdi-silverware-variant',
                },
                {
                    key: 'menus',
                    label: 'Anzahl Menüs',
                    value: this.formatCount(stats.menus_count),
                    icon: 'mdi-food-takeout-box-outline',
                },
                {
                    key: 'menu-plans',
                    label: 'Anzahl Menüpläne',
                    value: this.formatCount(stats.menu_plans_count),
                    icon: 'mdi-calendar-text-outline',
                },
                {
                    key: 'bookings',
                    label: 'Anzahl Bestellungen',
                    value: this.formatCount(stats.bookings_count),
                    icon: 'mdi-clipboard-check-outline',
                },
                {
                    key: 'lunch-users',
                    label: 'User mit lunch_user',
                    value: this.formatCount(stats.lunch_users_count),
                    icon: 'mdi-account-check-outline',
                },
                {
                    key: 'lunch-admins',
                    label: 'User mit lunch_admin',
                    value: this.formatCount(stats.lunch_admins_count),
                    icon: 'mdi-account-tie-outline',
                },
            ]
        },
        statsLoadedAt() {
            if (! this.legacyStats?.loaded_at) {
                return ''
            }

            return new Intl.DateTimeFormat('de-AT', {
                dateStyle: 'short',
                timeStyle: 'medium',
            }).format(new Date(this.legacyStats.loaded_at))
        },
        updatePreviewStats() {
            const preview = this.legacyStats?.update_preview || {}
            const foods = preview.foods || {}
            const menus = preview.menus || {}
            const menuPlans = preview.menu_plans || preview.menu_plan_entries || {}
            const bookings = preview.bookings || {}
            const lunchUsers = preview.lunch_users || {}
            const lunchAdmins = preview.lunch_admins || {}

            return [
                {
                    key: 'foods',
                    importKey: 'foods',
                    label: 'Speisen',
                    value: this.formatCount(foods.total),
                    detail: [
                        `Neu: ${this.formatCount(foods.to_create)}`,
                        `Geändert: ${this.formatCount(foods.to_update)}`,
                    ].join(' / '),
                    icon: 'mdi-silverware-variant',
                },
                {
                    key: 'menus',
                    importKey: 'menus',
                    label: 'Menüs',
                    value: this.formatCount(menus.total),
                    detail: [
                        `Neu: ${this.formatCount(menus.to_create)}`,
                        `Geändert: ${this.formatCount(menus.to_update)}`,
                    ].join(' / '),
                    icon: 'mdi-food-takeout-box-outline',
                },
                {
                    key: 'menu-plan-entries',
                    importKey: 'menu_plans',
                    label: 'Anzahl Menüpläne',
                    value: this.formatCount(menuPlans.total),
                    detail: [
                        `Neu: ${this.formatCount(menuPlans.to_create)}`,
                        `Geändert: ${this.formatCount(menuPlans.to_update)}`,
                    ].join(' / '),
                    icon: 'mdi-calendar-check-outline',
                },
                {
                    key: 'bookings',
                    importKey: 'bookings',
                    label: 'Bestellungen',
                    value: this.formatCount(bookings.total),
                    detail: [
                        `Neu: ${this.formatCount(bookings.to_create)}`,
                        `Geändert: ${this.formatCount(bookings.to_update)}`,
                    ].join(' / '),
                    icon: 'mdi-clipboard-check-outline',
                },
                {
                    key: 'lunch-users',
                    importKey: 'lunch_users',
                    label: 'Lunch-User',
                    value: this.formatCount(lunchUsers.total),
                    detail: [
                        `Neue User: ${this.formatCount(lunchUsers.to_create)}`,
                        `Bestehende ohne Rolle: ${this.formatCount(lunchUsers.existing_users_to_assign)}`,
                    ].join(' / '),
                    icon: 'mdi-account-check-outline',
                },
                {
                    key: 'lunch-admins',
                    importKey: 'lunch_admins',
                    label: 'Lunch-Admins',
                    value: this.formatCount(lunchAdmins.total),
                    detail: [
                        `Neue User: ${this.formatCount(lunchAdmins.to_create)}`,
                        `Bestehende ohne Rolle: ${this.formatCount(lunchAdmins.existing_users_to_assign)}`,
                    ].join(' / '),
                    icon: 'mdi-account-tie-outline',
                },
            ]
        },
        hasSelectedImportItems() {
            return this.selectedImportItems.length > 0
        },
    },

    mounted() {
        this.loadLegacyStats()
    },

    methods: {
        async loadLegacyStats() {
            this.isLoadingStats = true
            this.statsError = ''

            try {
                const response = await axios.get('/api/admin/restaurant/cdgym/legacy-stats')
                this.legacyStats = response?.data?.data || null
            } catch (error) {
                this.statsError = error.response?.data?.message || 'Die Live-Daten konnten nicht geladen werden.'
            } finally {
                this.isLoadingStats = false
            }
        },
        async importSelectedItems() {
            if (! this.hasSelectedImportItems) {
                return
            }

            this.isImporting = true
            this.importMessage = ''
            this.importError = ''

            try {
                await axios.post('/api/admin/restaurant/cdgym/legacy-import', {
                    items: this.selectedImportItems,
                })
                this.importMessage = 'Import abgeschlossen.'
                this.selectedImportItems = []
                await this.loadLegacyStats()
            } catch (error) {
                this.importError = error.response?.data?.message || 'Der Import konnte nicht ausgeführt werden.'
            } finally {
                this.isImporting = false
            }
        },
        formatCount(value) {
            const normalized = Number(value)

            if (! Number.isFinite(normalized)) {
                return '-'
            }

            return new Intl.NumberFormat('de-AT').format(normalized)
        },
    },
}
</script>

<style scoped>
.cdgym-legacy-card {
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, 0.1);
    color: #0f172a;
    padding: 22px;
}

.cdgym-legacy-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.cdgym-legacy-card__eyebrow {
    color: #b45309;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
}

.cdgym-legacy-card__title {
    font-size: 1.35rem;
    font-weight: 750;
    line-height: 1.2;
    margin: 3px 0 0;
}

.cdgym-legacy-card__stats {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.cdgym-legacy-card__stat {
    align-items: center;
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 8px;
    display: flex;
    gap: 12px;
    min-height: 90px;
    padding: 14px;
}

.cdgym-legacy-card__stat-value {
    font-size: 1.7rem;
    font-weight: 800;
    line-height: 1;
}

.cdgym-legacy-card__stat-label {
    color: #475569;
    font-size: 0.82rem;
    font-weight: 700;
    margin-top: 5px;
}

.cdgym-legacy-card__loaded-row {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    margin-top: 10px;
}

.cdgym-legacy-card__loaded-at {
    color: #64748b;
    font-size: 0.82rem;
}

.cdgym-legacy-card__comparison {
    display: grid;
    gap: 14px;
}

.cdgym-legacy-card__section-label {
    color: #b45309;
    font-size: 0.74rem;
    font-weight: 750;
    text-transform: uppercase;
}

.cdgym-legacy-card__section-title {
    font-size: 1rem;
    font-weight: 750;
    margin: 2px 0 0;
}

.cdgym-legacy-card__comparison-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.cdgym-legacy-card__comparison-item {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 8px;
    display: grid;
    gap: 10px;
    min-height: 104px;
    padding: 14px;
}

.cdgym-legacy-card__comparison-body {
    align-items: flex-start;
    display: flex;
    gap: 12px;
}

.cdgym-legacy-card__checkbox {
    align-items: center;
    color: #475569;
    display: flex;
    font-size: 0.74rem;
    font-weight: 700;
    gap: 6px;
}

.cdgym-legacy-card__checkbox input {
    accent-color: #2563eb;
    height: 15px;
    width: 15px;
}

.cdgym-legacy-card__comparison-value {
    font-size: 1.45rem;
    font-weight: 800;
    line-height: 1;
}

.cdgym-legacy-card__comparison-label {
    color: #334155;
    font-size: 0.82rem;
    font-weight: 750;
    margin-top: 5px;
}

.cdgym-legacy-card__comparison-detail {
    color: #64748b;
    font-size: 0.78rem;
    line-height: 1.35;
    margin-top: 4px;
}

.cdgym-legacy-card__import-row {
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 700px) {
    .cdgym-legacy-card__header {
        align-items: stretch;
        flex-direction: column;
    }

    .cdgym-legacy-card__comparison-grid,
    .cdgym-legacy-card__stats {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 701px) and (max-width: 1100px) {
    .cdgym-legacy-card__comparison-grid,
    .cdgym-legacy-card__stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
