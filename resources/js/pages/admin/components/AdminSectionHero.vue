<template>
    <v-sheet rounded="xl" class="admin-section-hero" :style="heroStyle">
        <div class="admin-section-hero__bg-orb admin-section-hero__bg-orb--left"></div>
        <div class="admin-section-hero__bg-orb admin-section-hero__bg-orb--right"></div>

        <v-row class="ma-0" align="stretch" dense>
            <v-col cols="12" lg="8" class="pa-2 pa-md-4">
                <div class="admin-section-hero__eyebrow">{{ eyebrow }}</div>
                <h1 class="admin-section-hero__title">{{ title }}</h1>
                <div class="admin-section-hero__chips">
                    <v-chip
                        v-if="showCurrentUserChip && currentUserChipText"
                        size="small"
                        :variant="userChipVariant"
                        :color="userChipColor"
                        :prepend-icon="userChipIcon"
                        class="admin-section-hero__chip-user">
                        {{ userChipPrefix }}: {{ currentUserChipText }}
                    </v-chip>

                    <v-chip
                        v-for="(chip, index) in normalizedChips"
                        :key="chip.key || `admin-section-hero-chip-${index}`"
                        :size="chip.size || 'small'"
                        :variant="chip.variant || defaultChipVariant"
                        :color="chip.color || defaultChipColor"
                        :prepend-icon="chip.icon || undefined"
                        :class="chip.class || undefined">
                        {{ chip.text }}
                    </v-chip>

                    <slot name="chips"></slot>
                </div>
            </v-col>

            <v-col cols="12" lg="4" class="pa-2 pa-md-4">
                <v-card variant="tonal" color="white" class="admin-section-hero__focus-card" rounded="xl">
                    <v-card-text class="pa-4">
                        <div class="admin-section-hero__focus-label">{{ focusLabel }}</div>
                        <div class="admin-section-hero__focus-value">
                            <v-icon size="18" :icon="resolvedActiveSection.icon" />
                            <span>{{ resolvedActiveSection.label }}</span>
                        </div>
                        <div v-if="resolvedActiveSection.note" class="admin-section-hero__focus-note">{{ resolvedActiveSection.note }}</div>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </v-sheet>
</template>

<script>
import { mapState } from 'pinia'
import { useAdminStore } from '@/stores/admin/AdminStore'

export default {
    props: {
        eyebrow: {
            type: String,
            required: true,
        },
        title: {
            type: String,
            required: true,
        },
        activeSection: {
            type: Object,
            default: () => ({
                icon: 'mdi-home',
                label: 'Uebersicht',
                note: '',
            }),
        },
        chips: {
            type: Array,
            default: () => [],
        },
        showCurrentUserChip: {
            type: Boolean,
            default: false,
        },
        userChipPrefix: {
            type: String,
            default: 'Benutzer',
        },
        userChipIcon: {
            type: String,
            default: 'mdi-account-circle',
        },
        userChipColor: {
            type: String,
            default: 'light-blue-lighten-3',
        },
        userChipVariant: {
            type: String,
            default: 'flat',
        },
        defaultChipColor: {
            type: String,
            default: 'white',
        },
        defaultChipVariant: {
            type: String,
            default: 'tonal',
        },
        focusLabel: {
            type: String,
            default: 'Aktiver Bereich',
        },
        primaryColor: {
            type: String,
            default: '#0f172a',
        },
        secondaryColor: {
            type: String,
            default: '#1d4ed8',
        },
        leftOrbColor: {
            type: String,
            default: '#67e8f9',
        },
        rightOrbColor: {
            type: String,
            default: '#a5b4fc',
        },
    },

    computed: {
        ...mapState(useAdminStore, ['config']),
        heroStyle() {
            return {
                '--admin-section-hero-primary': this.primaryColor,
                '--admin-section-hero-secondary': this.secondaryColor,
                '--admin-section-hero-left-orb-color': this.leftOrbColor,
                '--admin-section-hero-right-orb-color': this.rightOrbColor,
            }
        },
        normalizedChips() {
            return (Array.isArray(this.chips) ? this.chips : [])
                .filter((chip) => chip && (chip.visible === undefined || chip.visible))
                .map((chip) => ({
                    ...chip,
                    text: chip.text ? String(chip.text).trim() : '',
                }))
                .filter((chip) => chip.text !== '')
        },
        resolvedActiveSection() {
            const section = this.activeSection || {}
            return {
                icon: section.icon || 'mdi-home',
                label: section.label || 'Uebersicht',
                note: section.note || '',
            }
        },
        currentUserChipText() {
            const user = this.config?.user || {}
            const firstName = user.first_name ? String(user.first_name).trim() : ''
            const lastName = user.last_name ? String(user.last_name).trim() : ''
            const fullName = `${firstName} ${lastName}`.trim()
            const email = user.email ? String(user.email).trim() : ''

            if (fullName && email) {
                return `${fullName} - ${email}`
            }

            return fullName || email || ''
        },
    },
}
</script>

<style scoped>
.admin-section-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: linear-gradient(132deg, var(--admin-section-hero-primary), var(--admin-section-hero-secondary));
    color: #ffffff;
}

.admin-section-hero__bg-orb {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 999px;
    filter: blur(12px);
    opacity: 0.34;
    pointer-events: none;
}

.admin-section-hero__bg-orb--left {
    top: -64px;
    left: -52px;
    background: radial-gradient(circle at center, var(--admin-section-hero-left-orb-color) 0%, rgba(103, 232, 249, 0.08) 72%);
}

.admin-section-hero__bg-orb--right {
    right: -58px;
    bottom: -70px;
    background: radial-gradient(circle at center, var(--admin-section-hero-right-orb-color) 0%, rgba(165, 180, 252, 0.08) 72%);
}

.admin-section-hero__eyebrow {
    font-size: 0.76rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.82;
}

.admin-section-hero__title {
    margin-top: 8px;
    font-size: clamp(1.4rem, 2.3vw, 2rem);
    line-height: 1.1;
    font-weight: 750;
}

.admin-section-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 18px;
}

.admin-section-hero__chip-user {
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.24);
}

.admin-section-hero__focus-card {
    border: 1px solid rgba(255, 255, 255, 0.26);
    background: rgba(255, 255, 255, 0.16) !important;
    backdrop-filter: blur(3px);
    height: 100%;
}

.admin-section-hero__focus-label {
    font-size: 0.72rem;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    opacity: 0.78;
    color: #fff;
}

.admin-section-hero__focus-value {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
}

.admin-section-hero__focus-note {
    margin-top: 6px;
    font-size: 0.82rem;
    opacity: 0.72;
    color: #fff;
}
</style>
