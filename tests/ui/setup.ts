import '@testing-library/jest-dom/vitest'
import { config } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './msw/server'

const unresolvedVuetifyComponentNames = [
    'v-alert',
    'v-autocomplete',
    'v-btn',
    'v-btn-toggle',
    'v-card',
    'v-card-actions',
    'v-card-text',
    'v-card-title',
    'v-chip',
    'v-col',
    'v-dialog',
    'v-expand-transition',
    'v-form',
    'v-icon',
    'v-list',
    'v-list-item',
    'v-menu',
    'v-progress-circular',
    'v-progress-linear',
    'v-row',
    'v-select',
    'v-skeleton-loader',
    'v-spacer',
    'v-table',
    'v-text-field',
    'v-tooltip',
]

const createPassiveVuetifyStub = (componentName) => defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () => h(componentName, attrs, slots.default?.())
    },
})

config.global.stubs = {
    ...config.global.stubs,
    ...Object.fromEntries(
        unresolvedVuetifyComponentNames.map((componentName) => [
            componentName,
            createPassiveVuetifyStub(componentName),
        ]),
    ),
}

config.global.config.warnHandler = (message) => {
    if (/Failed to resolve component:\s*v-/i.test(message)) {
        throw new Error(`Missing Vuetify test stub: ${message}`)
    }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())
