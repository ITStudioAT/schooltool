<template>
    <div class="restaurant-page">
        <section class="restaurant-hero">
            <div class="restaurant-shell">
                <router-link to="/" class="restaurant-back-link">
                    <v-icon size="18">mdi-arrow-left</v-icon>
                    <span>Zur Startseite</span>
                </router-link>

                <div class="restaurant-hero-card">
                    <div class="restaurant-hero-copy">
                        <div class="restaurant-eyebrow">SchoolTool Restaurant</div>
                        <h1 class="restaurant-title">Restaurant</h1>
                        <div class="restaurant-school-selector">
                            <label class="restaurant-school-selector__label">Schule auswählen</label>
                            <v-select
                                v-model="selectedSchoolShortName"
                                :items="selectableSchools"
                                item-title="long_name"
                                item-value="short_name"
                                placeholder="Schule wählen…"
                                variant="outlined"
                                density="compact"
                                hide-details
                                class="restaurant-school-selector__select"
                                @update:model-value="onSchoolSelected"
                            />
                        </div>

                        <div class="restaurant-actions">
                            <router-link to="/" class="restaurant-action restaurant-action--secondary">Zur Übersicht</router-link>
                            <a href="/admin/restaurant/menu-plans" class="restaurant-action-text">Zur Verwaltung</a>
                        </div>
                    </div>

                    <div class="restaurant-status-card">
                        <div class="restaurant-status-card__label">{{ schoolInfoName }}</div>
                        <div
                            v-if="restaurantIntroHtml"
                            class="restaurant-status-card__intro"
                            v-html="restaurantIntroHtml" />
                        <div v-else class="restaurant-status-card__intro restaurant-status-card__intro--empty">
                            Kein Informationstext hinterlegt.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="restaurant-auth">
            <div class="restaurant-shell">
                <div class="restaurant-auth-card">
                    <div class="restaurant-auth-card__icon">
                        <v-icon size="28">mdi-account-circle-outline</v-icon>
                    </div>
                    <div class="restaurant-auth-card__copy">
                        <h2 class="restaurant-auth-card__title">Anmelden oder Registrieren</h2>
                        <p class="restaurant-auth-card__text">Melden Sie sich an, um Bestellungen aufzugeben und Ihren Speiseplan einzusehen.</p>
                    </div>
                    <div class="restaurant-auth-card__actions">
                        <v-btn color="#ea580c" variant="flat" rounded="lg" class="text-none font-weight-bold" @click="showLoginDialog = true">Anmelden</v-btn>
                        <v-btn color="#ea580c" variant="outlined" rounded="lg" class="text-none font-weight-bold" @click="showRegisterDialog = true">Registrieren</v-btn>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="menuPlans.length" class="restaurant-plans">
            <div class="restaurant-shell">
                <div class="rp-section-header">
                    <v-icon icon="mdi-silverware-fork-knife" size="22" class="rp-section-header__icon" />
                    <h2 class="rp-section-header__title">Aktuelle Speisepläne</h2>
                </div>

                <div v-for="plan in menuPlans" :key="plan.id" class="rp-plan" :class="{ 'rp-plan--orderable': plan.is_orderable }">
                    <div class="rp-plan__header">
                        <div class="rp-plan__header-left">
                            <div class="rp-plan__title">{{ plan.title || 'Menüplan' }}</div>
                            <div class="rp-plan__range">{{ formatDate(plan.start_date) }} – {{ formatDate(plan.end_date) }}</div>
                        </div>
                        <div v-if="plan.is_orderable" class="rp-plan__badge-group">
                            <div class="rp-plan__badge">
                                <v-icon icon="mdi-cart-check" size="15" />
                                <span>Bestellbar</span>
                            </div>
                            <div v-if="countdownFor(plan)" class="rp-plan__timer">
                                <v-icon icon="mdi-timer-outline" size="13" />
                                <span>noch {{ countdownFor(plan) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rp-days">
                        <div
                            v-for="group in groupEntriesByDate(plan.entries)"
                            :key="group.date"
                            class="rp-day">
                            <div class="rp-day__header">
                                <span class="rp-day__weekday">{{ weekdayLabel(group.date) }}</span>
                                <span class="rp-day__date">{{ formatDateShort(group.date) }}</span>
                            </div>

                            <div class="rp-day__menus">
                                <div v-for="entry in group.entries" :key="entry.id" class="rp-menu">
                                    <div class="rp-menu__top">
                                        <div class="rp-menu__title">{{ entry.menu_title || entry.menu?.title || 'Menü' }}</div>
                                        <div v-if="entry.price" class="rp-menu__price">{{ formatPrice(entry.price) }}</div>
                                    </div>

                                    <div v-if="entry.menu?.foods?.length" class="rp-menu__foods">
                                        <span v-for="food in entry.menu.foods" :key="food.id" class="rp-menu__food">
                                            {{ food.title }}
                                            <span v-if="food.allergens?.length" class="rp-menu__allergens">({{ food.allergens.join(', ') }})</span>
                                        </span>
                                    </div>

                                    <div v-if="entry.eating_times?.length" class="rp-menu__times">
                                        <v-icon icon="mdi-clock-outline" size="13" class="rp-menu__times-icon" />
                                        <span v-for="(et, i) in entry.eating_times" :key="et.id">
                                            {{ et.eating_time }} Uhr<span v-if="i < entry.eating_times.length - 1">, </span>
                                        </span>
                                    </div>

                                    <div v-if="entry.comments" class="rp-menu__comments">{{ entry.comments }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else class="restaurant-content">
            <div class="restaurant-shell">
                <div class="rp-empty">
                    <v-icon icon="mdi-silverware-fork-knife" size="40" class="rp-empty__icon" />
                    <p class="rp-empty__text">Derzeit sind keine Speisepläne verfügbar.</p>
                </div>
            </div>
        </section>
        <v-dialog v-model="showLoginDialog" persistent max-width="440">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Anmelden</v-card-title>
                <v-card-text class="px-6">
                    <v-text-field label="E-Mail" variant="outlined" density="compact" class="mb-3" />
                    <v-text-field label="Passwort" type="password" variant="outlined" density="compact" />
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" color="secondary" @click="showLoginDialog = false">Abbrechen</v-btn>
                    <v-btn variant="flat" color="#ea580c" class="text-none font-weight-bold" @click="showLoginDialog = false">Anmelden</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-dialog v-model="showRegisterDialog" persistent max-width="440">
            <v-card rounded="xl">
                <v-card-title class="pt-5 px-6 font-weight-bold">Registrieren</v-card-title>
                <v-card-text class="px-6">
                    <v-text-field label="Vorname" variant="outlined" density="compact" class="mb-3" />
                    <v-text-field label="Nachname" variant="outlined" density="compact" class="mb-3" />
                    <v-text-field label="E-Mail" variant="outlined" density="compact" class="mb-3" />
                    <v-text-field label="Passwort" type="password" variant="outlined" density="compact" />
                </v-card-text>
                <v-card-actions class="px-6 pb-5">
                    <v-spacer />
                    <v-btn variant="text" color="secondary" @click="showRegisterDialog = false">Abbrechen</v-btn>
                    <v-btn variant="flat" color="#ea580c" class="text-none font-weight-bold" @click="showRegisterDialog = false">Registrieren</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import { mapWritableState } from 'pinia'
import { useHomepageStore } from '@/stores/homepage/HomepageStore'

export default {
    name: 'HomepageRestaurantPage',

    async beforeMount() {
        this.homepageStore = useHomepageStore()

        const schoolFromUrl = this.$route?.query?.school ?? null

        if (! this.config) {
            await this.homepageStore.loadConfig(schoolFromUrl, this.$route?.query?.app ?? null)
        }

        if (this.config?.school?.short_name) {
            this.selectedSchoolShortName = this.config.school.short_name
        }

        await this.homepageStore.loadSchoolsForTool('Restaurant')
        await this.loadMenuPlans()
    },

    data() {
        return {
            homepageStore: null,
            selectedSchoolShortName: null,
            menuPlans: [],
            showLoginDialog: false,
            showRegisterDialog: false,
            tickNow: Date.now(),
            tickInterval: null,
        }
    },

    mounted() {
        this.tickInterval = setInterval(() => { this.tickNow = Date.now() }, 1000)
    },

    beforeUnmount() {
        if (this.tickInterval) clearInterval(this.tickInterval)
    },

    computed: {
        ...mapWritableState(useHomepageStore, ['config']),
        restaurantIntroHtml() {
            return this.config?.restaurant?.user_information_intro_html || ''
        },
        schoolInfoName() {
            return this.config?.school?.long_name || this.config?.school?.short_name || 'Keine Schule ausgewählt'
        },
        orderableMenuPlansCount() {
            return Number(this.config?.restaurant?.orderable_menu_plans_count || 0)
        },
        visibleMenuPlansCount() {
            return Number(this.config?.restaurant?.visible_menu_plans_count || 0)
        },
        selectableSchools() {
            return this.homepageStore?.schools || []
        },
        currentSchoolShortName() {
            return this.config?.school?.short_name || null
        },
    },

    methods: {
        async onSchoolSelected(shortName) {
            if (shortName) {
                this.$router.replace({ query: { ...this.$route.query, school: shortName } })
                await this.homepageStore.loadConfig(shortName, 'restaurant')
                this.selectedSchoolShortName = this.config?.school?.short_name || null
                await this.loadMenuPlans()
            }
        },

        async loadMenuPlans() {
            if (! this.currentSchoolShortName) {
                this.menuPlans = []
                return
            }

            try {
                const response = await axios.get('/api/homepage/restaurant/menu-plans', {
                    params: { school: this.currentSchoolShortName },
                })
                this.menuPlans = response.data?.plans || []
            } catch {
                this.menuPlans = []
            }
        },

        groupEntriesByDate(entries) {
            if (! entries?.length) return []

            const grouped = {}
            for (const entry of entries) {
                const date = entry.plan_date
                if (! grouped[date]) {
                    grouped[date] = { date, entries: [] }
                }
                grouped[date].entries.push(entry)
            }

            return Object.values(grouped).sort((a, b) => a.date.localeCompare(b.date))
        },

        weekdayLabel(isoDate) {
            const days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa']
            return days[new Date(`${isoDate}T00:00:00`).getDay()]
        },

        formatDate(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
                day: '2-digit', month: '2-digit', year: 'numeric',
            })
        },

        formatDateShort(isoDate) {
            return new Date(`${isoDate}T00:00:00`).toLocaleDateString('de-AT', {
                day: '2-digit', month: '2-digit',
            })
        },

        formatPrice(value) {
            const num = parseFloat(value)
            return isNaN(num) ? value : `€ ${num.toFixed(2).replace('.', ',')}`
        },

        countdownFor(plan) {
            if (! plan.orderable_until) return null

            const diff = new Date(plan.orderable_until).getTime() - this.tickNow
            if (diff <= 0) return null

            const totalSeconds = Math.floor(diff / 1000)
            const days = Math.floor(totalSeconds / 86400)
            const hours = Math.floor((totalSeconds % 86400) / 3600)
            const minutes = Math.floor((totalSeconds % 3600) / 60)
            const seconds = totalSeconds % 60

            if (days > 0) return `${days} Tage ${hours} Stunden ${minutes} Minuten`
            if (hours > 0) return `${hours} Stunden ${minutes} Minuten ${seconds} Sekunden`
            return `${minutes} Minuten ${seconds} Sekunden`
        },
    },
}
</script>

<style scoped>
.restaurant-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(255, 213, 128, 0.55), transparent 34%),
        radial-gradient(circle at top right, rgba(255, 247, 237, 0.7), transparent 28%),
        linear-gradient(180deg, #fff7ed 0%, #ffedd5 26%, #fff 100%);
    color: #1f2937;
}

.restaurant-shell {
    width: min(1120px, calc(100% - 32px));
    margin: 0 auto;
}

.restaurant-hero {
    padding: 32px 0 36px;
}

.restaurant-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #9a3412;
    font-weight: 700;
    text-decoration: none;
    margin-bottom: 18px;
}

.restaurant-hero-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, 1fr);
    gap: 22px;
    padding: 28px;
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(251, 146, 60, 0.24);
    box-shadow: 0 24px 70px rgba(194, 65, 12, 0.12);
    backdrop-filter: blur(10px);
}

.restaurant-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(251, 146, 60, 0.12);
    color: #c2410c;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.restaurant-title {
    margin: 18px 0 12px;
    font-size: clamp(2.4rem, 4vw, 4rem);
    line-height: 0.94;
    font-weight: 900;
    color: #111827;
}

.restaurant-lead {
    max-width: 62ch;
    font-size: 1.02rem;
    line-height: 1.7;
    color: #4b5563;
}

.restaurant-lead--richtext :deep(p) {
    margin: 0 0 0.7em;
}

.restaurant-lead--richtext :deep(p:last-child) {
    margin-bottom: 0;
}

.restaurant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.restaurant-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 18px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 800;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.restaurant-action:hover {
    transform: translateY(-1px);
}

.restaurant-action--secondary {
    background: rgba(255, 255, 255, 0.92);
    color: #9a3412;
    border: 1px solid rgba(251, 146, 60, 0.28);
}

.restaurant-action-text {
    display: inline-flex;
    align-items: center;
    color: #9a3412;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    opacity: 0.7;
    transition: opacity 0.15s ease;
}

.restaurant-action-text:hover {
    opacity: 1;
    text-decoration: underline;
}

.restaurant-school-selector {
    margin-top: 4px;
}

.restaurant-school-selector__label {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: #9a3412;
    margin-bottom: 6px;
}

.restaurant-school-selector__select {
    max-width: 380px;
}

.restaurant-auth {
    padding: 0 0 24px;
}

.restaurant-auth-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 20px 26px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(251, 146, 60, 0.18);
    box-shadow: 0 10px 30px rgba(120, 53, 15, 0.06);
}

.restaurant-auth-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 16px;
    background: rgba(251, 146, 60, 0.12);
    color: #c2410c;
    flex-shrink: 0;
}

.restaurant-auth-card__copy {
    flex: 1;
    min-width: 0;
}

.restaurant-auth-card__title {
    margin: 0 0 4px;
    font-size: 1.1rem;
    font-weight: 800;
    color: #111827;
}

.restaurant-auth-card__text {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
    color: #6b7280;
}

.restaurant-auth-card__actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.restaurant-status-card {
    align-self: stretch;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    padding: 22px;
    border-radius: 22px;
    background: linear-gradient(160deg, #7c2d12 0%, #c2410c 54%, #f97316 100%);
    color: #fff7ed;
}

.restaurant-status-card__label {
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.74;
}

.restaurant-status-card__intro {
    font-size: 0.92rem;
    line-height: 1.7;
    opacity: 0.94;
    margin-top: 8px;
}

.restaurant-status-card__intro :deep(p) {
    margin: 0 0 0.5em;
}

.restaurant-status-card__intro :deep(p:last-child) {
    margin-bottom: 0;
}

.restaurant-status-card__intro--empty {
    opacity: 0.5;
    font-style: italic;
}

/* ---- Menu Plans Section ---- */

.restaurant-plans,
.restaurant-content {
    padding: 0 0 56px;
}

.rp-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.rp-section-header__icon {
    color: #c2410c;
}

.rp-section-header__title {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0;
}

.rp-plan {
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(251, 146, 60, 0.18);
    box-shadow: 0 16px 40px rgba(120, 53, 15, 0.08);
    overflow: hidden;
}

.rp-plan + .rp-plan {
    margin-top: 20px;
}

.rp-plan--orderable {
    border-color: rgba(34, 197, 94, 0.3);
    box-shadow: 0 16px 40px rgba(34, 197, 94, 0.1);
}

.rp-plan__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 18px 22px;
    background: linear-gradient(135deg, #7c2d12 0%, #c2410c 100%);
    color: #fff;
}

.rp-plan--orderable .rp-plan__header {
    background: linear-gradient(135deg, #14532d 0%, #15803d 60%, #22c55e 100%);
}

.rp-plan__header-left {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.rp-plan__title {
    font-size: 1.12rem;
    font-weight: 800;
}

.rp-plan__range {
    font-size: 0.85rem;
    opacity: 0.85;
}

.rp-plan__badge-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.rp-plan__badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
    backdrop-filter: blur(4px);
}

.rp-plan__timer {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.12);
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.rp-days {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 0;
}

.rp-day {
    border-bottom: 1px solid rgba(251, 146, 60, 0.12);
    border-right: 1px solid rgba(251, 146, 60, 0.12);
}

.rp-day__header {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 14px 18px 8px;
    background: rgba(255, 247, 237, 0.5);
    border-bottom: 1px solid rgba(251, 146, 60, 0.1);
}

.rp-day__weekday {
    font-size: 0.88rem;
    font-weight: 800;
    color: #9a3412;
}

.rp-day__date {
    font-size: 0.82rem;
    color: #6b7280;
}

.rp-day__menus {
    padding: 10px 18px 14px;
    display: grid;
    gap: 10px;
}

.rp-menu {
    padding: 10px 14px;
    border-radius: 12px;
    background: rgba(255, 247, 237, 0.6);
    border: 1px solid rgba(251, 146, 60, 0.12);
}

.rp-menu__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
}

.rp-menu__title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1f2937;
}

.rp-menu__price {
    font-size: 0.88rem;
    font-weight: 800;
    color: #c2410c;
    white-space: nowrap;
}

.rp-menu__foods {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 8px;
    margin-top: 6px;
}

.rp-menu__food {
    font-size: 0.82rem;
    color: #4b5563;
    line-height: 1.5;
}

.rp-menu__allergens {
    font-size: 0.75rem;
    color: #9ca3af;
}

.rp-menu__times {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 6px;
    font-size: 0.78rem;
    color: #6b7280;
}

.rp-menu__times-icon {
    color: #9ca3af;
}

.rp-menu__comments {
    margin-top: 6px;
    font-size: 0.8rem;
    color: #6b7280;
    font-style: italic;
    line-height: 1.5;
}

.rp-empty {
    text-align: center;
    padding: 48px 24px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.88);
    border: 1px solid rgba(251, 146, 60, 0.12);
}

.rp-empty__icon {
    color: #d4a373;
    opacity: 0.5;
    margin-bottom: 12px;
}

.rp-empty__text {
    margin: 0;
    font-size: 1rem;
    color: #6b7280;
}

@media (max-width: 960px) {
    .restaurant-hero-card {
        grid-template-columns: 1fr;
    }

    .restaurant-auth-card {
        flex-direction: column;
        text-align: center;
    }

    .restaurant-auth-card__actions {
        justify-content: center;
    }

    .rp-days {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

@media (max-width: 640px) {
    .restaurant-hero {
        padding-top: 20px;
    }

    .restaurant-shell {
        width: min(100% - 20px, 1120px);
    }

    .restaurant-hero-card {
        padding: 20px;
    }

    .restaurant-title {
        font-size: 2.4rem;
    }

    .restaurant-actions {
        flex-direction: column;
    }

    .rp-days {
        grid-template-columns: 1fr;
    }

    .rp-plan__header {
        padding: 14px 16px;
    }

    .rp-day__header {
        padding: 12px 14px 6px;
    }

    .rp-day__menus {
        padding: 8px 14px 12px;
    }

    .rp-menu {
        padding: 8px 10px;
    }
}
</style>
