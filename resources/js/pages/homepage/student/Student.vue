<template>
    <div class="lernportal-page">
        <div class="bg-shape bg-shape-1"></div>
        <div class="bg-shape bg-shape-2"></div>

        <section class="hero">
            <div class="hero-card">
                <div class="hero-topline">
                    <v-btn class="back-btn" variant="text" prepend-icon="mdi-arrow-left" @click="$router.push('/')">Zur Startseite</v-btn>
                    <div class="chip-brand">Unterricht</div>
                </div>

                <h1 class="hero-title">Unterricht</h1>
                <p class="hero-subtitle">Überblick bewahren. Inhalte kennen. Noten erfahren.</p>

                <div class="hero-badges">
                    <span class="hero-badge">{{ weekdayLabel }}</span>
                    <span class="hero-badge dark">{{ dateLabel }}</span>
                    <span v-if="school" class="hero-badge">{{ school.long_name }}</span>
                </div>
            </div>
        </section>

        <section v-if="!school" class="schools-cover">
            <div class="schools-card">
                <div class="schools-head">
                    <v-icon size="24">mdi-school-outline</v-icon>
                    <h2>Verfügbare Schulen</h2>
                    <v-chip class="schools-count" size="small">{{ schools?.length || 0 }}</v-chip>
                </div>

                <p class="schools-copy">Diese Schulen sind aktuell für den Unterrichtsbereich verfügbar.</p>

                <div v-if="showSchoolsGrid" class="schools-grid">
                    <div
                        v-for="schoolItem in schools"
                        :key="schoolItem.id"
                        class="school-item"
                        :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                        @click="selected_school_id = schoolItem.id">
                        <div class="school-main">
                            <span class="school-long">{{ schoolItem.long_name }}</span>
                            <span class="school-short">{{ schoolItem.short_name }}</span>
                        </div>
                    </div>
                </div>

                <template v-else-if="hasManySchools">
                    <v-form ref="schoolSearchForm" v-model="is_school_search_valid">
                        <v-text-field
                            v-model="school_search"
                            label="Schule suchen (Name oder Kürzel)"
                            variant="outlined"
                            prepend-inner-icon="mdi-magnify"
                            class="school-search-field"
                            clearable
                            :rules="[maxLength(255)]"
                            hide-details="auto"
                            @update:modelValue="validateSchoolSearchForm" />
                    </v-form>

                    <div v-if="!school_search?.trim()" class="schools-hint">
                        <v-icon size="22">mdi-information-outline</v-icon>
                        <span>Bitte Suchtext eingeben. Es wird in Name und Kürzel gesucht.</span>
                    </div>

                    <div v-else-if="filteredSchoolsForSearch.length" class="schools-grid">
                        <div
                            v-for="schoolItem in filteredSchoolsForSearch"
                            :key="schoolItem.id"
                            class="school-item"
                            :class="{ 'is-active': Number(selected_school_id) === Number(schoolItem.id) }"
                            @click="selected_school_id = schoolItem.id">
                            <div class="school-main">
                                <span class="school-long">{{ schoolItem.long_name }}</span>
                                <span class="school-short">{{ schoolItem.short_name }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="schools-empty">
                        <v-icon size="22">mdi-alert-circle-outline</v-icon>
                        <span>Keine passende Schule gefunden.</span>
                    </div>
                </template>

                <div v-else-if="schools?.length" class="schools-hint">
                    <v-icon size="22">mdi-information-outline</v-icon>
                    <span>Bitte eine Schule auswählen.</span>
                </div>

                <div v-else class="schools-empty">
                    <v-icon size="22">mdi-alert-circle-outline</v-icon>
                    <span>Keine verfügbaren Schulen gefunden.</span>
                </div>
            </div>
        </section>

        <section v-if="school" class="login-cover">
            <div class="login-card">
                <div class="login-head">
                    <v-icon size="26">mdi-account-school</v-icon>
                    <h2>Login-Bereich</h2>
                </div>
                <p class="login-copy">Die Schule ist ausgewählt. Du kannst jetzt deine E-Mail eingeben.</p>

                <v-form ref="loginForm" v-model="is_login_email_valid">
                    <div class="login-fields">
                        <v-text-field
                            v-model="login_email"
                            label="E-Mail"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-email-outline"
                            :rules="[required(), mail(), maxLength(255)]"
                            hide-details="auto" />
                    </div>
                    <div class="login-actions">
                        <v-btn color="warning" variant="flat" rounded="pill" :disabled="!canContinueWithEmail" @click="continueWithPassword">Weiter mit Kennwort</v-btn>
                        <v-btn color="primary" variant="outlined" rounded="pill" :disabled="!canContinueWithEmail" @click="continueWithoutPassword">Weiter ohne Kennwort</v-btn>
                    </div>
                </v-form>
            </div>
        </section>
    </div>
</template>

<script>
import { useValidationRulesSetup } from '@/helpers/rules'
import { mapWritableState } from 'pinia'
import { useStudentStore } from '@/stores/student/StudentStore'

export default {
    setup() {
        return useValidationRulesSetup()
    },

    async beforeMount() {
        this.studentStore = useStudentStore()
        await this.studentStore.loadConfig()
        if (this.selected_school_id) {
            this.school = this.schools?.find((item) => Number(item.id) === Number(this.selected_school_id)) || null
        } else if (this.schools?.length === 1) {
            this.selected_school_id = this.schools[0].id
        }
    },

    data() {
        return {
            studentStore: null,
            school_search: '',
            login_email: '',
            is_school_search_valid: true,
            is_login_email_valid: false,
        }
    },

    computed: {
        ...mapWritableState(useStudentStore, ['config', 'schools', 'selected_school_id', 'school']),
        maxSchoolsShown() {
            const raw = this.config?.config?.schooltool?.teaching_max_schools_shown
            const parsed = Number(raw)
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 20
        },
        showSchoolsGrid() {
            return Array.isArray(this.schools) && this.schools.length > 0 && this.schools.length <= this.maxSchoolsShown
        },
        hasManySchools() {
            return Array.isArray(this.schools) && this.schools.length > this.maxSchoolsShown
        },
        filteredSchoolsForSearch() {
            if (!this.hasManySchools) return []

            const query = (this.school_search || '').trim().toLocaleLowerCase('de')
            if (!query) return []

            return this.schools.filter((item) => {
                const longName = String(item.long_name || '').toLocaleLowerCase('de')
                const shortName = String(item.short_name || '').toLocaleLowerCase('de')
                return longName.includes(query) || shortName.includes(query)
            })
        },
        canContinueWithEmail() {
            const value = String(this.login_email || '').trim()
            if (!value) return false
            return this.required()(value) === true && this.mail()(value) === true && this.maxLength(255)(value) === true
        },
        weekdayLabel() {
            return new Intl.DateTimeFormat('de-AT', { weekday: 'long' }).format(new Date())
        },
        dateLabel() {
            return new Intl.DateTimeFormat('de-AT', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date())
        },
    },

    watch: {
        selected_school_id(newId) {
            this.school = this.schools?.find((item) => Number(item.id) === Number(newId)) || null
        },
    },

    methods: {
        async validateSchoolSearchForm() {
            if (!this.$refs.schoolSearchForm) return
            this.is_school_search_valid = false
            await this.$refs.schoolSearchForm.validate()
        },

        async validateLoginForm() {
            this.is_login_email_valid = false
            await this.$refs.loginForm.validate()
            return this.is_login_email_valid
        },

        async continueWithPassword() {
            const isValid = await this.validateLoginForm()
            if (!isValid) return
            // Login flow is intentionally not implemented yet.
            const data = {
                type: 'login_with_password',
                school_id: this.selected_school_id,
                email: this.login_email.trim(),
            }
            await this.studentStore.loginStepEmail(data)
        },

        async continueWithoutPassword() {
            const isValid = await this.validateLoginForm()
            if (!isValid) return
            const data = {
                type: 'login_without_password',
                school_id: this.selected_school_id,
                email: this.login_email.trim(),
            }
            await this.studentStore.loginStepEmail(data)
        },
    },
}
</script>

<style scoped>
.lernportal-page {
    --pumpkin: #fd802e;
    --charcoal: #233d4c;
    --cream: #f8efe7;
    min-height: 100vh;
    padding: 28px 20px 56px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(150deg, #182a35 0%, var(--charcoal) 46%, #182a35 100%);
    font-family: 'Poppins', 'Nunito', sans-serif;
}

.bg-shape {
    position: absolute;
    border-radius: 999px;
    filter: blur(90px);
    opacity: 0.35;
    pointer-events: none;
}

.bg-shape-1 {
    width: 340px;
    height: 340px;
    top: -80px;
    right: -120px;
    background: var(--pumpkin);
}

.bg-shape-2 {
    width: 340px;
    height: 340px;
    bottom: -120px;
    left: -130px;
    background: #ff9f5e;
}

.hero,
.schools-cover,
.login-cover {
    max-width: 960px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.hero-card {
    background: linear-gradient(155deg, var(--pumpkin) 0%, #ff8f42 100%);
    border-radius: 24px;
    padding: 26px;
    box-shadow: 0 20px 50px rgba(8, 16, 20, 0.35);
    color: var(--charcoal);
}

.hero-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.back-btn {
    color: var(--charcoal);
    font-weight: 700;
    letter-spacing: 0.2px;
    border-radius: 999px;
}

.chip-brand {
    padding: 7px 13px;
    border-radius: 999px;
    background: rgba(35, 61, 76, 0.15);
    font-size: 0.8rem;
    font-weight: 700;
    border: 1px solid rgba(35, 61, 76, 0.28);
}

.hero-title {
    margin: 20px 0 10px;
    font-size: clamp(1.8rem, 4vw, 3rem);
    line-height: 1.05;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.hero-subtitle {
    margin: 0;
    font-size: 1.06rem;
    max-width: 640px;
    font-weight: 600;
}

.hero-badges {
    margin-top: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.hero-badge {
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.82rem;
    border: 1px solid rgba(35, 61, 76, 0.25);
    background: rgba(248, 239, 231, 0.65);
}

.hero-badge.dark {
    color: var(--pumpkin);
    background: var(--charcoal);
    border-color: rgba(253, 128, 46, 0.55);
}

.login-cover {
    margin-top: 26px;
}

.schools-cover {
    margin-top: 26px;
}

.schools-card {
    background: rgba(248, 239, 231, 0.96);
    border: 1px solid rgba(253, 128, 46, 0.35);
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.2);
}

.schools-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--charcoal);
    flex-wrap: wrap;
}

.schools-head h2 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 800;
}

.schools-count {
    margin-left: auto;
    background: rgba(35, 61, 76, 0.1);
    color: #233d4c;
    font-weight: 700;
}

.schools-copy {
    margin: 10px 0 16px;
    color: #314d5d;
    font-weight: 500;
}

.school-search-field {
    margin-bottom: 12px;
}

.schools-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.school-item {
    background: rgba(35, 61, 76, 0.06);
    border: 1px solid rgba(35, 61, 76, 0.18);
    border-radius: 14px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.school-item.is-active {
    border-color: rgba(58, 170, 53, 0.65);
    background: rgba(58, 170, 53, 0.12);
}

.school-main {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.school-long {
    color: #1d3645;
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1.2;
}

.school-short {
    color: #48606f;
    font-weight: 600;
    font-size: 0.82rem;
}

.schools-empty {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6e5a47;
    font-weight: 600;
    background: rgba(253, 128, 46, 0.12);
    border-radius: 12px;
    padding: 10px 12px;
}

.schools-hint {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1d3645;
    font-weight: 600;
    background: rgba(35, 61, 76, 0.1);
    border-radius: 12px;
    padding: 10px 12px;
}

.login-card {
    background: rgba(248, 239, 231, 0.96);
    border: 1px solid rgba(253, 128, 46, 0.35);
    border-radius: 24px;
    padding: 26px;
    box-shadow: 0 14px 38px rgba(0, 0, 0, 0.2);
}

.login-head {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--charcoal);
}

.login-head h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
}

.login-copy {
    margin: 10px 0 18px;
    color: #314d5d;
    font-weight: 500;
}

.login-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.login-actions {
    margin-top: 12px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 740px) {
    .lernportal-page {
        padding: 18px 14px 38px;
    }

    .hero-card,
    .schools-card,
    .login-card {
        padding: 20px;
        border-radius: 18px;
    }

    .schools-grid {
        grid-template-columns: 1fr;
    }
}
</style>
