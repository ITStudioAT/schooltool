<template>
    <div class="menu-plans-page">
        <v-container fluid class="ma-0 w-100 pa-2">
            <AdminSectionHero
                class="mb-3"
                eyebrow="Restaurant"
                title="Men&uuml;pl&auml;ne"
                :active-section="headerActiveSection"
                :chips="headerChips"
                :show-current-user-chip="true"
                focus-label="Seitenstatus"
                primary-color="#3f2b1d"
                secondary-color="#bf5f20"
                left-orb-color="#fde68a"
                right-orb-color="#fdba74" />

            <v-row dense>
                <v-col cols="12" lg="8">
                    <v-sheet rounded="xl" class="menu-plans-card pa-5">
                        <div class="menu-plans-card__eyebrow">Platzhalter</div>
                        <h2 class="menu-plans-card__title">Dummy-Seite f&uuml;r k&uuml;nftige Men&uuml;pl&auml;ne</h2>
                        <p class="menu-plans-card__copy">
                            Dieser Bereich ist vorbereitet. Hier k&ouml;nnen sp&auml;ter Wochen- oder Monatspl&auml;ne,
                            Ver&ouml;ffentlichungen und interne Abstimmungen rund um Men&uuml;folgen eingebaut werden.
                        </p>

                        <div class="menu-plans-card__actions">
                            <v-btn color="primary" rounded="xl" prepend-icon="mdi-silverware-fork-knife" to="/admin/restaurant">
                                Zum Restaurant
                            </v-btn>
                        </div>
                    </v-sheet>
                </v-col>

                <v-col cols="12" lg="4">
                    <v-sheet rounded="xl" class="menu-plans-side pa-5">
                        <div class="menu-plans-side__label">Slug</div>
                        <div class="menu-plans-side__value">menu-plans</div>
                        <div class="menu-plans-side__note">
                            Die Route ist aktiv und kann jetzt als Einstiegspunkt f&uuml;r die sp&auml;tere Umsetzung genutzt werden.
                        </div>
                    </v-sheet>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>

<script>
import AdminSectionHero from '@/pages/admin/components/AdminSectionHero.vue'
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    components: {
        AdminSectionHero,
    },

    computed: {
        ...mapState(useAdminStore, ['config']),
        headerActiveSection() {
            return {
                icon: 'mdi-calendar-text-outline',
                label: 'Men\u00fcpl\u00e4ne',
                note: 'Dummy-Seite aktiv',
            }
        },
        headerChips() {
            return [
                {
                    text: this.config?.selected_school?.long_name || this.config?.selected_school?.short_name || 'Schule aktiv',
                    icon: 'mdi-domain',
                    color: 'white',
                },
                {
                    text: 'Route /admin/menu-plans',
                    icon: 'mdi-link-variant',
                    color: 'white',
                },
            ]
        },
    },
}
</script>

<style scoped>
.menu-plans-page {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(253, 230, 138, 0.18), transparent 32%),
        linear-gradient(180deg, #fff8f1 0%, #fffaf6 38%, #f6efe8 100%);
}

.menu-plans-card,
.menu-plans-side {
    height: 100%;
    border: 1px solid rgba(126, 66, 24, 0.12);
    background: rgba(255, 255, 255, 0.84);
    box-shadow: 0 22px 44px rgba(94, 43, 10, 0.08);
}

.menu-plans-card__eyebrow,
.menu-plans-side__label {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #a16207;
}

.menu-plans-card__title {
    margin-top: 10px;
    font-size: clamp(1.45rem, 2vw, 2rem);
    line-height: 1.1;
    color: #4a2d16;
}

.menu-plans-card__copy,
.menu-plans-side__note {
    margin-top: 14px;
    font-size: 1rem;
    line-height: 1.65;
    color: #6b4f3a;
}

.menu-plans-card__actions {
    margin-top: 22px;
}

.menu-plans-side__value {
    margin-top: 10px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #7c2d12;
}
</style>
