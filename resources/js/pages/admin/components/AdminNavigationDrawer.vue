<template>
    <v-navigation-drawer
        v-if="isVisible"
        v-model="isOpen"
        :color="shellColor"
        :style="shellTextColor ? { color: shellTextColor } : undefined">
        <v-toolbar
            :color="shellColor"
            :style="shellTextColor ? { color: shellTextColor } : undefined">
            <v-toolbar-title>
                <img :src="'/storage/images/' + config?.logo" alt="Logo" class="logo" height="24" />
            </v-toolbar-title>
            <v-spacer></v-spacer>
            <v-btn icon="mdi-menu-close" @click="isOpen = false" v-if="isOpen" />
        </v-toolbar>
        <v-progress-linear v-if="isLoading > 0" indeterminate color="light-blue-lighten-3" />
        <v-list>
            <template v-for="(item, i) in config?.menu || []" :key="i">
                <v-divider v-if="item.divider_before" class="mx-4 my-3" :opacity="0.65" :thickness="1" />
                <v-menu
                    v-if="Array.isArray(item.children) && item.children.length > 0"
                    location="end top"
                    offset="8"
                    :close-on-content-click="true">
                    <template #activator="{ props: hopperMenuActivatorProps }">
                        <v-list-item
                            v-bind="hopperMenuActivatorProps"
                            :title="item.title"
                            :prepend-icon="item.icon"
                            :disabled="isMenuInteractionDisabled || !item.is_active" />
                    </template>
                    <v-list density="comfortable" style="min-width: 280px;">
                        <template v-for="(child, childIndex) in item.children" :key="`${i}-${childIndex}`">
                            <v-list-item
                                v-if="child.to"
                                :exact="false"
                                :title="child.title"
                                :subtitle="child.subtitle"
                                :prepend-icon="child.icon"
                                v-bind="routeItemBindings(child)"
                                :disabled="isMenuInteractionDisabled || !child.is_active"
                                @click="$emit('navigate-menu-route', child.to)">
                                <template v-if="child.status_icon" #append>
                                    <v-icon
                                        :icon="child.status_icon"
                                        :color="child.status_color || 'warning'"
                                        :title="child.status_title || ''"
                                        size="small" />
                                </template>
                            </v-list-item>
                            <v-list-item
                                v-else-if="child.click"
                                :exact="false"
                                :title="child.title"
                                :subtitle="child.subtitle"
                                :prepend-icon="child.icon"
                                :disabled="isMenuInteractionDisabled || !child.is_active"
                                @click="$emit('call-item-click', child)" />
                        </template>
                    </v-list>
                </v-menu>
                <v-list-item
                    v-else-if="item.href"
                    :exact="false"
                    :title="item.title"
                    :prepend-icon="item.icon"
                    :href="item.href"
                    target="_blank"
                    :disabled="isMenuInteractionDisabled || !item.is_active" />
                <v-list-item
                    v-else-if="item.to"
                    :exact="false"
                    :title="item.title"
                    :subtitle="item.subtitle"
                    :prepend-icon="item.icon"
                    v-bind="routeItemBindings(item)"
                    :disabled="isMenuInteractionDisabled || !item.is_active"
                    @click="$emit('navigate-menu-route', item.to)">
                    <template v-if="item.status_icon" #append>
                        <v-icon
                            :icon="item.status_icon"
                            :color="item.status_color || 'warning'"
                            :title="item.status_title || ''"
                            size="small" />
                    </template>
                </v-list-item>
                <v-list-item
                    v-else-if="item.click"
                    :exact="false"
                    :title="item.title"
                    :prepend-icon="item.icon"
                    :disabled="isMenuInteractionDisabled"
                    @click="$emit('call-item-click', item)" />
            </template>
        </v-list>
    </v-navigation-drawer>
</template>

<script>
export default {
    props: {
        modelValue: {
            type: Boolean,
            required: true,
        },
        isVisible: {
            type: Boolean,
            required: true,
        },
        config: {
            type: Object,
            default: null,
        },
        shellColor: {
            type: String,
            default: 'primary',
        },
        shellTextColor: {
            type: String,
            default: null,
        },
        isLoading: {
            type: Number,
            required: true,
        },
        isMenuInteractionDisabled: {
            type: Boolean,
            required: true,
        },
    },

    emits: ['update:modelValue', 'navigate-menu-route', 'call-item-click'],

    computed: {
        isOpen: {
            get() {
                return this.modelValue
            },
            set(value) {
                this.$emit('update:modelValue', value)
            },
        },
    },

    methods: {
        routeItemBindings(item) {
            return Array.isArray(item?.active_paths) && item.active_paths.length > 0
                ? { active: this.isMenuItemActive(item) }
                : {}
        },
        isMenuItemActive(item) {
            const activePaths = Array.isArray(item?.active_paths) ? item.active_paths : []

            if (!activePaths.length) {
                return false
            }

            const currentPath = this.normalizeAdminPath(this.$route?.path)
            const exact = !!item.active_exact

            return activePaths.some((activePath) => {
                const normalizedActivePath = this.normalizeAdminPath(activePath)

                if (exact) {
                    return currentPath === normalizedActivePath
                }

                return currentPath === normalizedActivePath || currentPath.startsWith(`${normalizedActivePath}/`)
            })
        },
        normalizeAdminPath(path) {
            if (typeof path !== 'string') {
                return ''
            }

            return path.replace(/\/+$/, '')
        },
    },
}
</script>
