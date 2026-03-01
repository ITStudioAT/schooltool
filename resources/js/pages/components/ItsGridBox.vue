<template>
    <v-sheet class="mb-2 its-grid-box" :class="[`its-grid-box--${variant}`, { 'its-grid-box--disabled': disabled }]">
        <v-card flat :rounded="variant === 'overview' ? 'xl' : '0'" :color="cardColor" class="h-100 py-0 its-grid-box__card" :disabled="disabled">
            <v-card-title class="its-grid-box__title">
                <div class="d-flex flex-row ga-2 align-center">
                    <div v-if="icon && variant === 'overview'" class="its-grid-box__icon-wrap">
                        <v-icon :icon="icon" size="16" />
                    </div>
                    <v-icon :icon="icon" v-else-if="icon" />
                    <slot name="title">
                        <div v-if="title" class="its-grid-box__title-text">{{ title }}</div>
                    </slot>
                    <v-spacer />
                    <slot name="header-actions" />
                </div>
                <div class="text-caption its-grid-box__subtitle" v-if="subtitle">{{ subtitle }}</div>
            </v-card-title>
            <v-card-text :class="bodyClass" class="pt-2 h-100 its-grid-box__content">
                <slot>
                    {{ text }}
                </slot>
            </v-card-text>
        </v-card>
    </v-sheet>
</template>

<script>
export default {
    props: {
        title: {
            type: String,
            default: '',
        },
        subtitle: {
            type: String,
            default: '',
        },
        text: {
            type: String,
            default: '',
        },
        color: {
            type: String,
            default: 'secondary',
        },
        icon: {
            type: String,
            default: '',
        },
        disabled: {
            type: Boolean,
            default: false,
        },
        variant: {
            type: String,
            default: 'default',
        },
    },

    data() {
        return {}
    },

    computed: {
        cardColor() {
            if (this.variant === 'overview') {
                return 'transparent'
            }

            return this.color || 'secondary'
        },
        bodyClass() {
            if (this.variant === 'overview') {
                return 'its-grid-box__content-surface'
            }

            return 'bg-' + this.color + '-lighten-4'
        },
    },

    methods: {},
}
</script>

<style scoped>
.its-grid-box--overview .its-grid-box__card {
    border: 1px solid rgba(15, 23, 42, 0.12);
    background:
        radial-gradient(circle at top right, rgba(37, 99, 235, 0.18), transparent 52%),
        linear-gradient(150deg, rgba(255, 255, 255, 0.95), rgba(241, 245, 249, 0.93));
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.11),
        inset 0 1px 0 rgba(255, 255, 255, 0.7);
    animation: overview-card-in 220ms ease-out;
}

.its-grid-box--overview .its-grid-box__title {
    padding: 14px 16px 12px;
    border-bottom: 1px solid rgba(30, 41, 59, 0.1);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.5), rgba(219, 234, 254, 0.36));
}

.its-grid-box__icon-wrap {
    width: 26px;
    height: 26px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: rgb(var(--v-theme-primary));
    background: rgba(59, 130, 246, 0.14);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.its-grid-box__title-text {
    font-size: 0.98rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: 0;
    color: #0f172a;
}

.its-grid-box__subtitle {
    margin-top: 4px;
    color: rgba(30, 41, 59, 0.72);
}

.its-grid-box--overview .its-grid-box__content {
    padding: 12px;
}

.its-grid-box__content-surface {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.84);
    backdrop-filter: blur(1px);
}

.its-grid-box--overview :deep(.v-card--variant-outlined) {
    border-color: rgba(15, 23, 42, 0.13);
    background: rgba(255, 255, 255, 0.8);
}

.its-grid-box--overview :deep(.v-list-item) {
    border-bottom: 1px solid rgba(30, 41, 59, 0.08);
}

.its-grid-box--overview :deep(.v-list-item:last-child) {
    border-bottom: 0;
}

.its-grid-box--overview :deep(.v-btn-toggle) {
    border-radius: 10px;
    border: 1px solid rgba(37, 99, 235, 0.22);
    background: rgba(248, 250, 252, 0.92);
}

.its-grid-box--disabled {
    opacity: 0.74;
}

@keyframes overview-card-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
