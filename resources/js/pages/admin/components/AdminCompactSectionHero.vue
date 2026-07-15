<template>
    <v-sheet rounded="xl" class="admin-compact-section-hero" :style="heroStyle">
        <div class="admin-compact-section-hero__bg-orb admin-compact-section-hero__bg-orb--left"></div>
        <div class="admin-compact-section-hero__bg-orb admin-compact-section-hero__bg-orb--right"></div>

        <div class="admin-compact-section-hero__content">
            <div class="admin-compact-section-hero__top-row">
                <div class="admin-compact-section-hero__heading">
                    <div class="admin-compact-section-hero__eyebrow">{{ eyebrow }}</div>
                    <h1 class="admin-compact-section-hero__title">{{ title }}</h1>
                </div>

                <div class="admin-compact-section-hero__chips">
                    <v-chip
                        v-if="showCurrentUserChip && currentUserChipText"
                        size="small"
                        :variant="userChipVariant"
                        :color="userChipColor"
                        :prepend-icon="userChipIcon"
                        class="admin-compact-section-hero__chip-user">
                        {{ userChipPrefix }}: {{ currentUserChipText }}
                    </v-chip>

                    <v-chip
                        v-for="(chip, index) in normalizedChips"
                        :key="chip.key || `admin-compact-section-hero-chip-${index}`"
                        :size="chip.size || 'small'"
                        :variant="chip.variant || defaultChipVariant"
                        :color="chip.color || defaultChipColor"
                        :prepend-icon="chip.icon || undefined"
                        :class="chip.class || undefined">
                        {{ chip.text }}
                    </v-chip>
                </div>
            </div>

            <div v-if="normalizedStatusItems.length || normalizedProgress !== null" class="admin-compact-section-hero__status-row">
                <div class="admin-compact-section-hero__status-items">
                    <div
                        v-for="(item, index) in normalizedStatusItems"
                        :key="item.key || `admin-compact-section-hero-status-${index}`"
                        class="admin-compact-section-hero__status-item">
                        <v-icon v-if="item.icon" :icon="item.icon" size="17" />
                        <span>{{ item.text }}</span>
                    </div>
                </div>

                <div v-if="normalizedProgress !== null" class="admin-compact-section-hero__progress">
                    <div class="admin-compact-section-hero__progress-heading">
                        <v-icon :icon="progressIcon" size="19" />
                        <div class="admin-compact-section-hero__progress-labels">
                            <span class="admin-compact-section-hero__progress-label">{{ progressLabel }}</span>
                            <span
                                v-if="progressSecondaryLabel"
                                class="admin-compact-section-hero__progress-secondary-label">
                                {{ progressSecondaryLabel }}
                            </span>
                        </div>
                    </div>
                    <div class="admin-compact-section-hero__progress-track">
                        <v-progress-linear
                            :model-value="normalizedProgress"
                            :aria-label="progressLabel"
                            color="#86efac"
                            bg-color="#ef4444"
                            :bg-opacity="0.62"
                            height="9"
                            rounded
                            rounded-bar />
                        <span
                            v-if="normalizedProgressMarker !== null"
                            class="admin-compact-section-hero__progress-marker"
                            :style="{ left: `${normalizedProgressMarker}%` }"
                            :aria-label="progressMarkerLabel"
                            :title="progressMarkerLabel"></span>
                    </div>
                </div>
            </div>
        </div>
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
        chips: {
            type: Array,
            default: () => [],
        },
        statusItems: {
            type: Array,
            default: () => [],
        },
        progress: {
            type: Number,
            default: null,
        },
        progressLabel: {
            type: String,
            default: 'Fortschritt',
        },
        progressSecondaryLabel: {
            type: String,
            default: '',
        },
        progressMarker: {
            type: Number,
            default: null,
        },
        progressMarkerLabel: {
            type: String,
            default: 'Markierung',
        },
        progressIcon: {
            type: String,
            default: 'mdi-chart-timeline-variant-shimmer',
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
                '--admin-compact-section-hero-primary': this.primaryColor,
                '--admin-compact-section-hero-secondary': this.secondaryColor,
                '--admin-compact-section-hero-left-orb-color': this.leftOrbColor,
                '--admin-compact-section-hero-right-orb-color': this.rightOrbColor,
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
        normalizedStatusItems() {
            return (Array.isArray(this.statusItems) ? this.statusItems : [])
                .filter(Boolean)
                .map((item) => ({
                    ...item,
                    text: item.text ? String(item.text).trim() : '',
                }))
                .filter((item) => item.text !== '')
        },
        normalizedProgress() {
            return typeof this.progress === 'number' && Number.isFinite(this.progress)
                ? Math.max(0, Math.min(100, Math.round(this.progress)))
                : null
        },
        normalizedProgressMarker() {
            return typeof this.progressMarker === 'number' && Number.isFinite(this.progressMarker)
                ? Math.max(0, Math.min(100, this.progressMarker))
                : null
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
.admin-compact-section-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: linear-gradient(132deg, var(--admin-compact-section-hero-primary), var(--admin-compact-section-hero-secondary));
    color: #ffffff;
}

.admin-compact-section-hero__bg-orb {
    position: absolute;
    width: 180px;
    height: 180px;
    border-radius: 999px;
    filter: blur(12px);
    opacity: 0.3;
    pointer-events: none;
}

.admin-compact-section-hero__bg-orb--left {
    top: -78px;
    left: -48px;
    background: radial-gradient(circle at center, var(--admin-compact-section-hero-left-orb-color) 0%, rgba(103, 232, 249, 0.08) 72%);
}

.admin-compact-section-hero__bg-orb--right {
    right: -44px;
    bottom: -94px;
    background: radial-gradient(circle at center, var(--admin-compact-section-hero-right-orb-color) 0%, rgba(165, 180, 252, 0.08) 72%);
}

.admin-compact-section-hero__content {
    position: relative;
    padding: 12px 16px;
}

.admin-compact-section-hero__top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.admin-compact-section-hero__heading {
    flex-shrink: 0;
}

.admin-compact-section-hero__eyebrow {
    font-size: 0.69rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.8;
}

.admin-compact-section-hero__title {
    margin-top: 2px;
    font-size: clamp(1.2rem, 2vw, 1.55rem);
    line-height: 1.08;
    font-weight: 750;
}

.admin-compact-section-hero__chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 6px;
}

.admin-compact-section-hero__chip-user {
    font-weight: 700;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.2);
}

.admin-compact-section-hero__status-row {
    display: flex;
    align-items: center;
    gap: 18px;
    margin-top: 10px;
    padding-top: 9px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.admin-compact-section-hero__status-items {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}

.admin-compact-section-hero__status-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 650;
    white-space: nowrap;
}

.admin-compact-section-hero__progress {
    display: grid;
    flex: 0 1 560px;
    grid-template-columns: max-content minmax(180px, 1fr);
    align-items: center;
    gap: 14px;
    width: 560px;
    min-width: 440px;
    margin-left: auto;
    padding: 8px 12px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 14px;
    background: linear-gradient(120deg, rgba(255, 255, 255, 0.18), rgba(134, 239, 172, 0.1));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 8px 20px rgba(15, 23, 42, 0.14);
    backdrop-filter: blur(4px);
}

.admin-compact-section-hero__progress-heading {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.admin-compact-section-hero__progress-labels {
    display: grid;
    gap: 1px;
}

.admin-compact-section-hero__progress-label {
    font-size: 0.88rem;
    font-weight: 400;
    white-space: nowrap;
}

.admin-compact-section-hero__progress-secondary-label {
    font-size: 0.76rem;
    font-weight: 400;
    line-height: 1.15;
    opacity: 0.86;
    white-space: nowrap;
}

.admin-compact-section-hero__progress-track {
    position: relative;
}

.admin-compact-section-hero__progress-marker {
    position: absolute;
    top: 50%;
    z-index: 1;
    width: 2px;
    height: 17px;
    border-radius: 999px;
    background: #ffffff;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.48), 0 1px 4px rgba(15, 23, 42, 0.5);
    pointer-events: none;
    transform: translate(-50%, -50%);
}

@media (max-width: 960px) {
    .admin-compact-section-hero__top-row,
    .admin-compact-section-hero__status-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .admin-compact-section-hero__heading {
        flex-shrink: 1;
    }

    .admin-compact-section-hero__chips {
        justify-content: flex-start;
    }

    .admin-compact-section-hero__progress {
        width: 100%;
        min-width: 0;
        margin-left: 0;
    }
}

@media (max-width: 600px) {
    .admin-compact-section-hero__content {
        padding: 10px 12px;
    }

    .admin-compact-section-hero__progress {
        grid-template-columns: 1fr;
        gap: 5px;
    }
}
</style>
