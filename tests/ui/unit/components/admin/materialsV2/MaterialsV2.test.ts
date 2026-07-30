import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { config, flushPromises, shallowMount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'
import MaterialsV2 from '@/pages/admin/materialsV2/MaterialsV2.vue'
import { useMaterialsV2Store } from '@/stores/admin/materialsV2/MaterialsV2Store'

const notify = vi.fn()
const { routeQuery, routerReplace } = vi.hoisted(() => ({
    routeQuery: {} as Record<string, string>,
    routerReplace: vi.fn(() => Promise.resolve()),
}))

config.global.stubs = {
    'v-tab': {
        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'v-tabs': {
        template: '<div><slot /></div>',
    },
    MaterialsV2Calendar: false,
    MaterialsV2Header: false,
}

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}))

vi.mock('vue-router', () => ({
    useRoute: () => ({ query: routeQuery }),
    useRouter: () => ({ replace: routerReplace }),
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({ notify }),
}))

vi.mock('@/stores/admin/AdminStore', () => ({
    useAdminStore: () => ({
        config: {
            selected_school: {
                long_name: 'Christian-Doppler-Gymnasium Salzburg',
                logo: 'logos/cdgym.svg',
            },
        },
    }),
}))

function deferredResponse() {
    let resolve = (_value: unknown) => {}
    const promise = new Promise((promiseResolve) => {
        resolve = promiseResolve
    })

    return {
        promise,
        resolve,
    }
}

describe('MaterialsV2', () => {
    afterEach(() => {
        vi.useRealTimers()
    })

    beforeEach(() => {
        setActivePinia(createPinia())
        window.localStorage.clear()
        vi.clearAllMocks()
        Object.keys(routeQuery).forEach((key) => delete routeQuery[key])
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:screenshot-preview'),
        })
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        })
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (url === '/api/admin/materials-v2/config') {
                return {
                    data: {
                        categories: ['Termine', 'Screenshots', 'Links', 'Dateien', 'Notizen', 'Biologie', 'Mathematik'],
                        category_details: [
                            { name: 'Termine', items_count: 0 },
                            { name: 'Screenshots', items_count: 0 },
                            { name: 'Links', items_count: 0 },
                            { name: 'Dateien', items_count: 0 },
                            { name: 'Notizen', items_count: 0 },
                            { name: 'Biologie', items_count: 1 },
                            { name: 'Mathematik', items_count: 0 },
                        ],
                        cluster_details: [
                            { id: 3, name: 'Wochenplanung', items_count: 1 },
                        ],
                    },
                }
            }

            return {
                data: {
                    data: [
                        {
                            id: 1,
                            title: 'Photosynthese',
                            category: 'Biologie',
                            cluster: {
                                id: 3,
                                name: 'Wochenplanung',
                            },
                            description: 'Arbeitsblatt',
                            user_keywords: ['Biologie'],
                            generated_keywords: ['welche'],
                            automatic_tag_suggestions: [
                                {
                                    name: 'Chlorophyll',
                                    score: 9.75,
                                    rank: 1,
                                    language: 'de',
                                    attachment_id: 41,
                                },
                            ],
                            processing_status: 'ready',
                            processed_at: '2026-07-25T12:30:00+02:00',
                            attachments: [
                                {
                                    id: 41,
                                    original_name: 'arbeitsblatt.docx',
                                    mime_type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    size_bytes: 4096,
                                    keyword_extraction_status: 'ready',
                                    keyword_extraction_error: null,
                                    keywords_extracted_at: '2026-07-25T12:30:00+02:00',
                                    preview_url: '/api/admin/materials-v2/attachments/41/preview',
                                    download_url: '/api/admin/materials-v2/attachments/41/download',
                                },
                            ],
                        },
                    ],
                    meta: {
                        total: 1,
                        current_page: 1,
                        last_page: 1,
                    },
                },
            }
        })
    })

    it('loads the independent materials v2 endpoint on mount', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })

        await flushPromises()

        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: undefined,
                page: 1,
                per_page: 18,
            },
        })
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/config')
        expect(wrapper.find('.materials-v2-title').text()).toBe('Materialien')
        expect(wrapper.find('.materials-v2-school-name').text()).toBe('Christian-Doppler-Gymnasium Salzburg')
        expect(wrapper.find('.materials-v2-school-logo').attributes('src')).toBe('/storage/images/logos/cdgym.svg')
        expect(wrapper.find('.materials-v2-header-search').attributes('placeholder')).toBe(
            'Was suchst du? Wortteile und kleine Tippfehler sind erlaubt …',
        )
        expect(wrapper.find('.materials-v2-reminder-header-button').exists()).toBe(false)
        expect(wrapper.text()).toContain('Photosynthese')
        expect(wrapper.text()).toContain('Biologie')
    })

    it('keeps the latest search response and resets scoped state on unmount', async () => {
        vi.useFakeTimers()
        const initialResponse = deferredResponse()
        const searchResponse = deferredResponse()
        const itemResponses = [initialResponse.promise, searchResponse.promise]
        vi.mocked(axios.get).mockImplementation(async (url) => {
            if (url === '/api/admin/materials-v2/config') {
                return {
                    data: {
                        categories: ['Biologie'],
                        category_details: [{ name: 'Biologie', items_count: 1 }],
                    },
                }
            }

            return await itemResponses.shift()
        })
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        const component = wrapper.vm as any

        component.search = 'neu'
        await wrapper.vm.$nextTick()
        await vi.advanceTimersByTimeAsync(350)

        searchResponse.resolve({
            data: {
                data: [{ id: 2, title: 'Neues Ergebnis', processing_status: 'ready', attachments: [] }],
                meta: { total: 1, current_page: 1, last_page: 1 },
            },
        })
        await flushPromises()
        expect(component.items.map((item) => item.title)).toEqual(['Neues Ergebnis'])

        initialResponse.resolve({
            data: {
                data: [{ id: 1, title: 'Altes Ergebnis', processing_status: 'ready', attachments: [] }],
                meta: { total: 1, current_page: 1, last_page: 1 },
            },
        })
        await flushPromises()
        expect(component.items.map((item) => item.title)).toEqual(['Neues Ergebnis'])

        const materialsStore = useMaterialsV2Store()
        wrapper.unmount()

        expect(materialsStore.items).toEqual([])
        expect(materialsStore.categoryDetails).toEqual([])
        expect(materialsStore.loadError).toBe('')
    })

    it('shows a small screenshot preview in the overview', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.items[0] = {
            ...component.items[0],
            title: 'Tafelbild',
            category: 'Screenshots',
            attachments: [
                {
                    id: 42,
                    original_name: 'tafelbild.png',
                    mime_type: 'image/png',
                    preview_url: '/api/admin/materials-v2/attachments/42/preview',
                    download_url: '/api/admin/materials-v2/attachments/42/download',
                },
            ],
        }
        await wrapper.vm.$nextTick()

        const thumbnail = wrapper.find('.materials-v2-screenshot-thumbnail')
        expect(thumbnail.exists()).toBe(true)
        expect(thumbnail.attributes('src')).toBe('/api/admin/materials-v2/attachments/42/preview')
        expect(thumbnail.attributes('alt')).toBe('Screenshot: Tafelbild')
        expect(wrapper.find('.materials-v2-card--screenshot').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-screenshot-frame-bar').text()).toContain('Screenshot')

        await wrapper.find('.materials-v2-screenshot-thumbnail-button').trigger('click')

        expect(component.previewDialog.attachment.id).toBe(42)
        expect(component.previewDialog.open).toBe(true)
    })

    it('gives each system material a recognizable overview treatment', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.items = [
            {
                id: 11,
                title: 'Elternbrief',
                category: 'Dateien',
                processing_status: 'ready',
                user_keywords: [],
                automatic_tag_suggestions: [],
                attachments: [{
                    id: 111,
                    original_name: 'elternbrief.pdf',
                    mime_type: 'application/pdf',
                    size_bytes: 2048,
                    preview_url: '/preview/111',
                    download_url: '/download/111',
                }],
            },
            {
                id: 12,
                title: 'Konferenz',
                category: 'Termine',
                reminder_date: '2026-09-15',
                reminder_time: '14:30',
                attachments: [],
            },
            {
                id: 13,
                title: 'Schulwebsite',
                category: 'Links',
                link_url: 'https://schooltool.at/hilfe',
                attachments: [],
            },
            {
                id: 14,
                title: 'Idee',
                category: 'Notizen',
                description: 'Arbeitsauftrag vereinfachen',
                attachments: [],
            },
        ]
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.materials-v2-card--file').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-file-browser').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-file-kind').text()).toBe('PDF')

        expect(wrapper.find('.materials-v2-card--reminder').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-reminder-calendar-sheet').text()).toContain('SEP')
        expect(wrapper.find('.materials-v2-reminder-calendar-sheet').text()).toContain('15')

        expect(wrapper.find('.materials-v2-card--link').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-link-copy').text()).toContain('Web-Link')
        expect(wrapper.find('.materials-v2-link-copy').text()).toContain('schooltool.at/hilfe')

        expect(wrapper.find('.materials-v2-card--note').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-note-sheet').text()).toContain('Arbeitsauftrag vereinfachen')
    })

    it('restores category selections from the URL and writes every selection back to it', async () => {
        routeQuery.category = 'Biologie'
        routeQuery.view = 'compact'

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any

        expect(component.selectedCategory).toBe('Biologie')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: 'Biologie',
                page: 1,
                per_page: 18,
            },
        })

        vi.mocked(routerReplace).mockClear()

        const expectedQueries = {
            Termine: { category: 'Termine', view: 'standard' },
            Screenshots: { category: 'Screenshots' },
            Links: { category: 'Links' },
            Dateien: { category: 'Dateien' },
            Notizen: { category: 'Notizen' },
            Mathematik: { category: 'Mathematik', view: 'compact' },
        }

        for (const [category, query] of Object.entries(expectedQueries)) {
            component.selectCategory(category)
            await wrapper.vm.$nextTick()

            expect(routerReplace).toHaveBeenLastCalledWith({
                query,
            })
        }

        component.selectCategory('__all_categories__')
        await wrapper.vm.$nextTick()

        expect(routerReplace).toHaveBeenLastCalledWith({
            query: {},
        })
    })

    it('shows reminder month and week calendars and keeps their selection in the URL', async () => {
        routeQuery.category = 'Termine'
        routeQuery.view = 'calendar'
        routeQuery.calendar = 'week'
        routeQuery.date = '2026-09-15'
        vi.mocked(axios.get).mockImplementation(async (url, requestConfig) => {
            if (url === '/api/admin/materials-v2/config') {
                return {
                    data: {
                        categories: ['Termine'],
                        category_details: [{ name: 'Termine', items_count: 1 }],
                    },
                }
            }

            if (requestConfig?.params?.reminder_order) {
                const isNext = requestConfig.params.reminder_order === 'asc'

                return {
                    data: {
                        data: [
                            {
                                id: isNext ? 8 : 6,
                                title: isNext ? 'Konferenz' : 'Schulfest',
                                category: 'Termine',
                                reminder_date: isNext ? '2026-09-29' : '2026-09-02',
                                reminder_time: '09:00',
                            },
                        ],
                        meta: {
                            total: 1,
                            current_page: 1,
                            last_page: 1,
                        },
                    },
                }
            }

            return {
                data: {
                    data: [
                        {
                            id: 7,
                            title: 'Elternabend',
                            category: 'Termine',
                            description: 'Im Festsaal',
                            reminder_date: '2026-09-15',
                            reminder_time: '18:30',
                            user_keywords: [],
                            automatic_tag_suggestions: [],
                            attachments: [],
                            processing_status: 'ready',
                        },
                    ],
                    meta: {
                        total: 1,
                        current_page: 1,
                        last_page: 1,
                    },
                },
            }
        })

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any

        expect(component.reminderDisplayMode).toBe('calendar')
        expect(component.calendarDisplayMode).toBe('week')
        expect(component.reminderViewSelection).toBe('week')
        expect(wrapper.find('.materials-v2-calendar-previous').attributes('title')).toBe('Vorherige Woche')
        expect(wrapper.find('.materials-v2-calendar-today').attributes('title')).toBe('Heute')
        expect(wrapper.find('.materials-v2-calendar-next').attributes('title')).toBe('Nächste Woche')
        expect(wrapper.find('.materials-v2-calendar-previous-item').attributes('title')).toBe('Vorheriger Termin')
        expect(wrapper.find('.materials-v2-calendar-next-item').attributes('title')).toBe('Nächster Termin')
        expect(wrapper.findAll('.materials-v2-reminder-display-toggle')).toHaveLength(1)
        expect(wrapper.find('.materials-v2-calendar-display-toggle').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-reminder-display-toggle').text()).toContain('Standard')
        expect(wrapper.find('.materials-v2-reminder-display-toggle').text()).toContain('Monat')
        expect(wrapper.find('.materials-v2-reminder-display-toggle').text()).toContain('Woche')
        expect(component.calendarDays).toHaveLength(7)
        expect(wrapper.find('.materials-v2-calendar-grid--week').exists()).toBe(true)
        expect(wrapper.findAll('.materials-v2-calendar-day')).toHaveLength(7)
        expect(wrapper.find('.materials-v2-calendar-event').text()).toContain('Elternabend')
        component.loading = true
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.materials-v2-calendar-loading').text()).toContain('Termine werden geladen')
        expect(wrapper.find('.materials-v2-calendar-event').text()).toContain('Elternabend')

        component.loading = false
        await wrapper.vm.$nextTick()
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: 'Termine',
                reminder_from: '2026-09-14',
                reminder_to: '2026-09-20',
                page: 1,
                per_page: 48,
            },
        })

        vi.mocked(routerReplace).mockClear()
        await component.jumpToAdjacentReminder('next')
        await flushPromises()

        expect(component.calendarFocusDate).toBe('2026-09-29')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                category: 'Termine',
                reminder_from: '2026-09-16',
                reminder_to: undefined,
                reminder_order: 'asc',
                page: 1,
                per_page: 6,
            },
        })

        component.calendarFocusDate = '2026-09-15'
        await flushPromises()
        await component.jumpToAdjacentReminder('previous')
        await flushPromises()

        expect(component.calendarFocusDate).toBe('2026-09-02')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                category: 'Termine',
                reminder_from: undefined,
                reminder_to: '2026-09-14',
                reminder_order: 'desc',
                page: 1,
                per_page: 6,
            },
        })

        component.calendarFocusDate = '2026-09-15'
        await flushPromises()

        component.moveCalendar(1)
        await flushPromises()

        expect(component.calendarFocusDate).toBe('2026-09-22')
        expect(routerReplace).toHaveBeenLastCalledWith({
            query: {
                category: 'Termine',
                view: 'calendar',
                calendar: 'week',
                date: '2026-09-22',
            },
        })

        component.moveCalendar(-1)
        await flushPromises()

        expect(component.calendarFocusDate).toBe('2026-09-15')

        vi.mocked(routerReplace).mockClear()
        component.reminderViewSelection = 'month'
        await flushPromises()

        expect(component.reminderViewSelection).toBe('month')
        expect(component.calendarDays).toHaveLength(35)
        expect(wrapper.find('.materials-v2-calendar-grid--month').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-calendar-previous').attributes('title')).toBe('Vorheriger Monat')
        expect(wrapper.find('.materials-v2-calendar-next').attributes('title')).toBe('Nächster Monat')
        expect(routerReplace).toHaveBeenLastCalledWith({
            query: {
                category: 'Termine',
                view: 'calendar',
                calendar: 'month',
                date: '2026-09-15',
            },
        })

        component.moveCalendar(1)
        await flushPromises()

        expect(component.calendarFocusDate).toBe('2026-10-15')

        const today = new Date()
        const todayDate = [
            today.getFullYear(),
            String(today.getMonth() + 1).padStart(2, '0'),
            String(today.getDate()).padStart(2, '0'),
        ].join('-')

        component.showToday()
        await flushPromises()

        expect(component.calendarFocusDate).toBe(todayDate)

        component.reminderViewSelection = 'standard'
        await flushPromises()

        expect(component.reminderViewSelection).toBe('standard')
        expect(wrapper.find('.materials-v2-calendar').exists()).toBe(false)
        expect(component.activeCardDisplayMode).toBe('standard')
        expect(routerReplace).toHaveBeenLastCalledWith({
            query: {
                category: 'Termine',
                view: 'standard',
            },
        })
    })

    it('defaults reminder views to standard and calendar periods to month', async () => {
        routeQuery.category = 'Termine'

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any

        expect(component.reminderDisplayMode).toBe('standard')
        expect(component.calendarDisplayMode).toBe('month')
        expect(component.reminderViewSelection).toBe('standard')
        expect(component.activeCardDisplayMode).toBe('standard')
        expect(wrapper.find('.materials-v2-calendar').exists()).toBe(false)
    })

    it('opens the matching create dialog from each system category plus button', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': {
                        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const createButtons = wrapper.findAll('.materials-v2-system-tab-create-button')
        const component = wrapper.vm as any

        expect(createButtons).toHaveLength(5)
        expect(createButtons.map((button) => button.attributes('title'))).toEqual([
            'Termin hinzufügen',
            'Screenshot hinzufügen',
            'Link hinzufügen',
            'Dateien hinzufügen',
            'Notiz hinzufügen',
        ])

        await createButtons[0].trigger('click')
        expect(component.materialForm.category).toBe('Termine')
        expect(component.materialDialog.open).toBe(true)

        component.materialDialog.open = false
        await createButtons[1].trigger('click')
        expect(component.materialForm.category).toBe('Screenshots')
        expect(component.materialForm.title).toBe('Screenshot')
        expect(component.materialDialog.open).toBe(true)

        component.materialDialog.open = false
        await createButtons[2].trigger('click')
        expect(component.materialForm.category).toBe('Links')
        expect(component.materialForm.title).toBe('Link')
        expect(component.materialDialog.open).toBe(true)

        component.materialDialog.open = false
        await createButtons[3].trigger('click')
        expect(component.materialForm.category).toBe('Dateien')
        expect(component.materialForm.title).toBe('Dateien')
        expect(component.materialDialog.open).toBe(true)

        component.materialDialog.open = false
        await createButtons[4].trigger('click')
        expect(component.materialForm.category).toBe('Notizen')
        expect(component.materialForm.title).toBe('Notiz')
        expect(component.materialDialog.open).toBe(true)
    })

    it('uploads multiple files through the dedicated Dateien form', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openFileDialog()
        await wrapper.vm.$nextTick()

        expect(component.materialForm.category).toBe('Dateien')
        expect(component.materialForm.title).toBe('Dateien')
        expect(component.isFileForm).toBe(true)
        expect(component.materialDialogTitle).toBe('Dateien hinzufügen')
        expect(wrapper.find('.materials-v2-fixed-category').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-cluster-chooser').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-cluster-chooser').attributes()).toHaveProperty('always-filter')
        expect(component.clusterSearchFilter('Wochenplanung', 'Wochenplannung')).toBe(true)
        expect(component.clusterSearchFilter('Wochenplanung', 'Medien')).toBe(false)
        expect(wrapper.find('.materials-v2-file-input').attributes('label')).toBe('Dateien auswählen')
        expect(wrapper.find('.materials-v2-keywords-field').exists()).toBe(true)

        const worksheet = new File(['Bruchrechnen'], 'arbeitsblatt.txt', { type: 'text/plain' })
        const notes = new File(['# Planung'], 'planung.md', { type: 'text/markdown' })
        component.materialForm.attachments = [worksheet, notes]
        component.materialForm.clusterName = 'Wochenplanung'
        component.materialForm.keywords = 'Mathematik, Planung'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 11,
                    title: 'Dateien',
                    category: 'Dateien',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        const payload = vi.mocked(axios.post).mock.calls[0][1] as FormData

        expect(payload.get('category')).toBe('Dateien')
        expect(payload.get('cluster_name')).toBe('Wochenplanung')
        expect(payload.getAll('attachments[]')).toEqual([worksheet, notes])
        expect(payload.getAll('user_keywords[]')).toEqual(['Mathematik', 'Planung'])
        expect(component.selectedCategory).toBe('Dateien')
        expect(notify).toHaveBeenCalledWith({
            message: 'Dateien gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('creates a simple dated reminder without tag or attachment fields', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any

        expect(component.categoryOptions).toContain('Termine')

        component.openReminderDialog()
        await wrapper.vm.$nextTick()

        expect(component.materialDialog.open).toBe(true)
        expect(component.materialForm.category).toBe('Termine')
        expect(component.isReminderForm).toBe(true)
        expect(wrapper.find('.materials-v2-category-chooser').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-fixed-category').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-reminder-fields').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-keywords-field').exists()).toBe(false)
        expect(component.formatReminderDate('2026-09-15')).toContain('15.09.2026')

        component.materialForm.title = 'Elternabend'
        component.materialForm.reminderDate = '2026-09-15'
        component.materialForm.reminderTime = '18:30'
        component.materialForm.description = 'Festsaal'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Elternabend',
                    category: 'Termine',
                    reminder_date: '2026-09-15',
                    reminder_time: '18:30',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        const payload = vi.mocked(axios.post).mock.calls[0][1] as FormData

        expect(payload.get('category')).toBe('Termine')
        expect(payload.get('reminder_date')).toBe('2026-09-15')
        expect(payload.get('reminder_time')).toBe('18:30')
        expect(payload.getAll('user_keywords[]')).toEqual([])
        expect(payload.getAll('attachments[]')).toEqual([])
        expect(component.selectedCategory).toBe('Termine')
        expect(notify).toHaveBeenCalledWith({
            message: 'Termin gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('creates a screenshot from a pasted clipboard image', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openScreenshotDialog()
        await wrapper.vm.$nextTick()

        expect(component.materialForm.category).toBe('Screenshots')
        expect(component.materialForm.title).toBe('Screenshot')
        expect(component.isScreenshotForm).toBe(true)
        expect(wrapper.find('.materials-v2-category-chooser').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-fixed-category').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-screenshot-paste-zone').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-keywords-field').exists()).toBe(false)

        const nonImagePreventDefault = vi.fn()
        component.handleScreenshotPaste({
            preventDefault: nonImagePreventDefault,
            clipboardData: {
                items: [{ kind: 'string', type: 'text/plain' }],
            },
        })
        expect(nonImagePreventDefault).not.toHaveBeenCalled()

        const image = new File(['png'], 'clipboard.png', { type: 'image/png' })
        const preventDefault = vi.fn()
        component.handleScreenshotPaste({
            preventDefault,
            clipboardData: {
                items: [{
                    kind: 'file',
                    type: 'image/png',
                    getAsFile: () => image,
                }],
            },
        })
        await wrapper.vm.$nextTick()

        expect(preventDefault).toHaveBeenCalledOnce()
        expect(component.materialForm.attachments).toEqual([image])
        expect(component.screenshotPreviewUrl).toBe('blob:screenshot-preview')
        expect(URL.createObjectURL).toHaveBeenCalledWith(image)

        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 3,
                    title: 'Screenshot',
                    category: 'Screenshots',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        const payload = vi.mocked(axios.post).mock.calls[0][1] as FormData

        expect(payload.get('category')).toBe('Screenshots')
        expect(payload.getAll('attachments[]')).toEqual([image])
        expect(payload.get('reminder_date')).toBeNull()
        expect(payload.getAll('user_keywords[]')).toEqual([])
        expect(component.selectedCategory).toBe('Screenshots')
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:screenshot-preview')
        expect(notify).toHaveBeenCalledWith({
            message: 'Screenshot gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('creates a clickable link from pasted clipboard text', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.items = [{
            id: 9,
            title: 'Schooltool',
            category: 'Links',
            link_url: 'https://schooltool.at/admin/materials-v2',
            processing_status: 'ready',
            user_keywords: [],
            automatic_tag_suggestions: [],
            attachments: [],
        }]
        await wrapper.vm.$nextTick()

        const linkTarget = wrapper.find('.materials-v2-link-target')
        expect(linkTarget.attributes('href')).toBe('https://schooltool.at/admin/materials-v2')
        expect(linkTarget.attributes('target')).toBe('_blank')
        expect(linkTarget.attributes('rel')).toBe('noopener noreferrer')

        component.openLinkDialog()
        await wrapper.vm.$nextTick()

        expect(component.materialForm.category).toBe('Links')
        expect(component.materialForm.title).toBe('Link')
        expect(component.isLinkForm).toBe(true)
        expect(wrapper.find('.materials-v2-category-chooser').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-fixed-category').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-link-paste-zone').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-keywords-field').exists()).toBe(false)

        vi.mocked(axios.post).mockImplementation(async (url) => {
            if (url === '/api/admin/materials-v2/link-preview') {
                return {
                    data: {
                        data: {
                            reachable: true,
                            status: 'success',
                            url: 'https://schooltool.at/admin/materials-v2',
                            title: 'Schooltool Materialien',
                            message: 'Zieladresse erreichbar. Der Seitentitel wurde übernommen.',
                            http_status: 200,
                        },
                    },
                }
            }

            return {
                data: {
                    data: {
                        id: 9,
                        title: 'Schooltool Materialien',
                        category: 'Links',
                        link_url: 'https://schooltool.at/admin/materials-v2',
                    },
                },
            }
        })

        const invalidPreventDefault = vi.fn()
        component.handleLinkPaste({
            preventDefault: invalidPreventDefault,
            clipboardData: {
                getData: () => 'kein gültiger Link',
            },
        })
        expect(invalidPreventDefault).toHaveBeenCalledOnce()
        expect(component.formErrors.link_url).not.toEqual([])

        const preventDefault = vi.fn()
        await component.handleLinkPaste({
            preventDefault,
            clipboardData: {
                getData: () => 'schooltool.at/admin/materials-v2',
            },
        })
        await wrapper.vm.$nextTick()

        expect(preventDefault).toHaveBeenCalledOnce()
        expect(component.materialForm.linkUrl).toBe('https://schooltool.at/admin/materials-v2')
        expect(component.materialForm.title).toBe('Schooltool Materialien')
        expect(component.formErrors.link_url).toEqual([])
        expect(component.linkPreview.state).toBe('success')
        expect(component.linkPreview.checkedUrl).toBe('https://schooltool.at/admin/materials-v2')
        expect(wrapper.find('.materials-v2-link-status').exists()).toBe(true)

        await component.saveMaterial()
        await flushPromises()

        const saveCall = vi.mocked(axios.post).mock.calls
            .find(([url]) => url === '/api/admin/materials-v2/items')
        const payload = saveCall?.[1] as FormData

        expect(payload.get('category')).toBe('Links')
        expect(payload.get('link_url')).toBe('https://schooltool.at/admin/materials-v2')
        expect(payload.getAll('attachments[]')).toEqual([])
        expect(payload.getAll('user_keywords[]')).toEqual([])
        expect(component.selectedCategory).toBe('Links')
        expect(notify).toHaveBeenCalledWith({
            message: 'Link gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('warns about an unconfirmed target without overwriting a manual title', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openLinkDialog()
        component.materialForm.title = 'Mein eigener Titel'

        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    reachable: false,
                    status: 'warning',
                    url: 'https://nicht-erreichbar.example',
                    title: null,
                    message: 'Die Zieladresse ist derzeit nicht erreichbar.',
                    http_status: null,
                },
            },
        })

        await component.setLinkUrl('https://nicht-erreichbar.example')
        await flushPromises()

        expect(component.linkPreview.state).toBe('warning')
        expect(component.linkPreview.message).toContain('nicht erreichbar')
        expect(component.materialForm.title).toBe('Mein eigener Titel')
        expect(wrapper.find('.materials-v2-link-status').exists()).toBe(true)
    })

    it('creates a short note without tag or attachment fields', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openNoteDialog()
        await wrapper.vm.$nextTick()

        expect(component.materialForm.category).toBe('Notizen')
        expect(component.materialForm.title).toBe('Notiz')
        expect(component.isNoteForm).toBe(true)
        expect(wrapper.find('.materials-v2-category-chooser').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-fixed-category').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-cluster-chooser').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-note-body').attributes('label')).toBe('Notiz')
        expect(wrapper.find('.materials-v2-reminder-fields').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-screenshot-input').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-link-input').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-keywords-field').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-edit-attachments').exists()).toBe(false)

        component.materialForm.clusterName = 'Wochenplanung'
        component.materialForm.description = 'Arbeitsblätter für Montag kopieren.'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 10,
                    title: 'Notiz',
                    category: 'Notizen',
                    description: 'Arbeitsblätter für Montag kopieren.',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        const payload = vi.mocked(axios.post).mock.calls[0][1] as FormData

        expect(payload.get('title')).toBe('Notiz')
        expect(payload.get('category')).toBe('Notizen')
        expect(payload.get('cluster_name')).toBe('Wochenplanung')
        expect(payload.get('description')).toBe('Arbeitsblätter für Montag kopieren.')
        expect(payload.getAll('user_keywords[]')).toEqual([])
        expect(payload.getAll('attachments[]')).toEqual([])
        expect(component.selectedCategory).toBe('Notizen')
        expect(notify).toHaveBeenCalledWith({
            message: 'Notiz gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('defaults to compact and switches between persisted material layouts', async () => {
        routeQuery.category = 'Biologie'

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        expect(component.displayMode).toBe('compact')
        expect(component.materialColumnProps).toEqual({ cols: 12, sm: 6, md: 4, lg: 3, xl: 2 })
        expect(wrapper.find('.materials-v2-card--compact').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-card').attributes('role')).toBe('button')
        expect(wrapper.find('.materials-v2-card').attributes('tabindex')).toBe('0')
        expect(wrapper.find('.materials-v2-card').attributes('aria-label')).toBe('Details zu Photosynthese öffnen')
        expect(wrapper.text()).toContain('Groß')
        expect(wrapper.text()).toContain('Standard')
        expect(wrapper.text()).toContain('Kompakt')

        component.items[0].description = null
        component.items[0].user_keywords = ['Lichtreaktion']
        component.displayMode = 'large'
        await wrapper.vm.$nextTick()

        expect(component.materialColumnProps).toEqual({ cols: 12, md: 6, xl: 4 })
        expect(wrapper.find('.materials-v2-card--large').exists()).toBe(true)
        expect(wrapper.text()).toContain('Keine Beschreibung')
        expect(wrapper.text()).not.toContain('Deine Suchwörter')
        expect(wrapper.text()).toContain('Lichtreaktion')

        component.displayMode = 'standard'
        await wrapper.vm.$nextTick()

        expect(component.materialColumnProps).toEqual({ cols: 12, sm: 6, lg: 4, xl: 3 })
        expect(wrapper.find('.materials-v2-card--standard').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('Keine Beschreibung')
        expect(wrapper.text()).not.toContain('Deine Suchwörter')
        expect(wrapper.text()).toContain('Lichtreaktion')
        expect(window.localStorage.getItem('materials-v2-display-mode')).toBe('standard')

        component.displayMode = 'compact'
        await wrapper.vm.$nextTick()

        expect(component.materialColumnProps).toEqual({ cols: 12, sm: 6, md: 4, lg: 3, xl: 2 })
        expect(wrapper.find('.materials-v2-card--compact').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-description').exists()).toBe(false)
        expect(wrapper.find('.materials-v2-attachment-row').exists()).toBe(false)
        expect(window.localStorage.getItem('materials-v2-display-mode')).toBe('compact')

        await wrapper.find('.materials-v2-card').trigger('click')

        expect(component.materialDialog.open).toBe(true)
        expect(component.materialDialog.mode).toBe('edit')
        expect(component.materialDialog.item.id).toBe(1)

        wrapper.unmount()

        const persistedWrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        expect((persistedWrapper.vm as any).displayMode).toBe('compact')
    })

    it('shows a category selection list and combines the selected category with the search', async () => {
        vi.useFakeTimers()

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const systemTabs = wrapper.findAll('.materials-v2-system-tab')
        const categoryItems = wrapper.findAll('.materials-v2-category-item')
        expect(systemTabs).toHaveLength(7)
        expect(systemTabs[0].text()).toContain('Alle Materialien')
        expect(systemTabs[1].text()).toContain('Termine')
        expect(systemTabs[2].text()).toContain('Screenshots')
        expect(systemTabs[3].text()).toContain('Links')
        expect(systemTabs[4].text()).toContain('Dateien')
        expect(systemTabs[5].text()).toContain('Notizen')
        expect(systemTabs[6].text()).toContain('Clusters')
        expect(categoryItems).toHaveLength(2)
        expect(categoryItems[0].text()).toContain('Biologie')
        expect(categoryItems[1].text()).toContain('Mathematik')

        vi.mocked(axios.get).mockClear()
        const component = wrapper.vm as any
        component.search = 'Zellen'
        await categoryItems[0].trigger('click')
        await flushPromises()

        expect(component.selectedCategory).toBe('Biologie')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: 'Zellen',
                category: 'Biologie',
                page: 1,
                per_page: 18,
            },
        })
    })

    it('shows clusters after notes and loads the selected clusters assigned items', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        const systemTabs = wrapper.findAll('.materials-v2-system-tab')

        expect(systemTabs[5].text()).toContain('Notizen')
        expect(systemTabs[6].text()).toContain('Clusters')

        vi.mocked(axios.get).mockClear()
        component.selectCategory('__clusters__')
        await flushPromises()

        expect(component.isClusterView).toBe(true)
        expect(component.selectedClusterId).toBe(3)
        expect(component.selectedCluster.name).toBe('Wochenplanung')
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: undefined,
                cluster_id: 3,
                page: 1,
                per_page: 18,
            },
        })
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                section: 'clusters',
                cluster: '3',
            },
        })
    })

    it('creates a category from a persistent dialog', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const createButton = wrapper.find('.materials-v2-category-create-button')
        expect(createButton.exists()).toBe(true)

        await createButton.trigger('click')

        const component = wrapper.vm as any
        const categoryDialog = wrapper.find('.materials-v2-category-dialog')
        expect(component.categoryDialog.open).toBe(true)
        expect(categoryDialog.attributes()).toHaveProperty('persistent')

        component.categoryDialog.name = 'Physik'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 8,
                    name: 'Physik',
                },
            },
        })

        await component.saveCategory()
        await flushPromises()

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            name: 'Physik',
        })
        expect(component.categoryDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie gespeichert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('keeps the category dialog open and warns about duplicate casing', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCategoryDialog()
        component.categoryDialog.name = 'video'
        vi.mocked(axios.post).mockRejectedValueOnce({
            response: {
                status: 409,
                data: {
                    category_conflict: {
                        entered: 'video',
                        existing: 'Video',
                    },
                },
            },
        })

        await component.saveCategory()

        expect(component.categoryDialog.open).toBe(true)
        expect(component.categoryDialog.warning).toBe(
            'Die Kategorie „Video“ existiert bereits. Bitte wähle einen anderen Namen.',
        )
        expect(notify).not.toHaveBeenCalled()
    })

    it('edits a category from its list action', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': {
                        template: '<div><slot /><slot name="append" /></div>',
                    },
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const editButtons = wrapper.findAll('.materials-v2-category-edit-button')
        expect(editButtons).toHaveLength(2)

        await editButtons[0].trigger('click')

        const component = wrapper.vm as any
        expect(component.categoryDialog.mode).toBe('edit')
        expect(component.categoryDialog.originalName).toBe('Biologie')
        expect(component.categoryDialog.name).toBe('Biologie')

        component.categoryDialog.name = 'Naturkunde'
        vi.mocked(axios.put).mockResolvedValueOnce({
            data: {
                data: {
                    id: 8,
                    name: 'Naturkunde',
                },
            },
        })

        await component.saveCategory()
        await flushPromises()

        expect(axios.put).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            original_name: 'Biologie',
            name: 'Naturkunde',
        })
        expect(component.categoryDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie aktualisiert.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('shows item counters and only offers deletion for an empty category', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': {
                        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': {
                        template: '<span v-bind="$attrs"><slot /></span>',
                    },
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': {
                        template: '<div><slot /><slot name="append" /></div>',
                    },
                    'v-list-item-title': {
                        template: '<div><slot /></div>',
                    },
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const counters = wrapper.findAll('.materials-v2-category-item-count')
        const systemCounters = wrapper.findAll('.materials-v2-system-tab-count')
        const deleteButtons = wrapper.findAll('.materials-v2-category-delete-button')
        const reminderCreateButtons = wrapper.findAll('.materials-v2-reminder-create-button')
        const screenshotCreateButtons = wrapper.findAll('.materials-v2-screenshot-create-button')
        const linkCreateButtons = wrapper.findAll('.materials-v2-link-create-button')

        expect(systemCounters.map((counter) => counter.text())).toEqual(['1', '0', '0', '0', '0', '0', '1'])
        expect(counters.map((counter) => counter.text())).toEqual(['1', '0'])
        expect(deleteButtons).toHaveLength(1)
        expect(reminderCreateButtons).toHaveLength(0)
        expect(screenshotCreateButtons).toHaveLength(0)
        expect(linkCreateButtons).toHaveLength(0)

        await deleteButtons[0].trigger('click')

        const component = wrapper.vm as any
        expect(component.categoryDeleteDialog.open).toBe(true)
        expect(component.categoryDeleteDialog.name).toBe('Mathematik')

        vi.mocked(axios.delete).mockResolvedValueOnce({ data: null })
        await component.deleteCategory()
        await flushPromises()

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/materials-v2/categories', {
            data: {
                name: 'Mathematik',
            },
        })
        expect(component.categoryDeleteDialog.open).toBe(false)
        expect(notify).toHaveBeenCalledWith({
            message: 'Kategorie gelöscht.',
            type: 'success',
            timeout: 3200,
        })
    })

    it('opens attachments in an embedded browser preview', async () => {
        routeQuery.category = 'Biologie'
        window.localStorage.setItem('materials-v2-display-mode', 'large')

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })

        await flushPromises()

        expect(wrapper.find('[title="Vorschau"]').exists()).toBe(true)

        await wrapper.find('.materials-v2-attachment-name').trigger('click')

        const previewFrame = wrapper.find('.materials-v2-preview-frame')
        expect(previewFrame.exists()).toBe(true)
        expect(previewFrame.attributes('src')).toBe('/api/admin/materials-v2/attachments/41/preview')
        expect(previewFrame.attributes('title')).toBe('Vorschau: arbeitsblatt.docx')
    })

    it('asks whether to reuse a similar cluster before saving', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openNoteDialog()
        component.materialForm.description = 'Montag vorbereiten'
        component.materialForm.clusterName = 'Wochenplannung'
        vi.mocked(axios.post).mockRejectedValueOnce({
            response: {
                status: 409,
                data: {
                    cluster_suggestion: {
                        entered: 'Wochenplannung',
                        existing: 'Wochenplanung',
                    },
                },
            },
        })

        await component.saveMaterial()

        expect(component.clusterSuggestionDialog.open).toBe(true)
        expect(wrapper.text()).toContain('Neuen Cluster anlegen')
        expect(wrapper.text()).toContain('Bestehenden übernehmen')

        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 11,
                    title: 'Notiz',
                    category: 'Notizen',
                    cluster: {
                        id: 3,
                        name: 'Wochenplanung',
                    },
                },
            },
        })

        await component.useSuggestedCluster()

        const savedPayload = vi.mocked(axios.post).mock.calls[1][1] as FormData
        expect(savedPayload.get('cluster_name')).toBe('Wochenplanung')
        expect(savedPayload.get('force_new_cluster')).toBeNull()
    })

    it('asks whether to reuse a similar category before saving', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCreateDialog()
        component.materialForm.title = 'Zellen'
        component.materialForm.category = 'Biolgie'
        vi.mocked(axios.post).mockRejectedValueOnce({
            response: {
                status: 409,
                data: {
                    category_suggestion: {
                        entered: 'Biolgie',
                        existing: 'Biologie',
                    },
                },
            },
        })

        await component.saveMaterial()

        expect(component.categorySuggestionDialog.open).toBe(true)
        expect(wrapper.text()).toContain('Neue Kategorie anlegen')
        expect(wrapper.text()).toContain('Bestehende übernehmen')

        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Zellen',
                    category: 'Biologie',
                },
            },
        })

        await component.useSuggestedCategory()

        const savedPayload = vi.mocked(axios.post).mock.calls[1][1] as FormData
        expect(savedPayload.get('category')).toBe('Biologie')
        expect(savedPayload.get('force_new_category')).toBeNull()
    })

    it('can keep the newly typed category after the warning', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.openCreateDialog()
        component.materialForm.title = 'Zellen'
        component.materialForm.category = 'Biolgie'
        component.categorySuggestionDialog.entered = 'Biolgie'
        component.categorySuggestionDialog.existing = 'Biologie'
        component.categorySuggestionDialog.open = true
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Zellen',
                    category: 'Biolgie',
                },
            },
        })

        await component.keepNewCategory()

        const savedPayload = vi.mocked(axios.post).mock.calls[0][1] as FormData
        expect(savedPayload.get('category')).toBe('Biolgie')
        expect(savedPayload.get('force_new_category')).toBe('1')
    })

    it('reloads the first unfiltered page after creating a material', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        component.search = 'Altes Material'
        component.selectedCategory = 'Biologie'
        component.page = 3
        await wrapper.vm.$nextTick()
        vi.mocked(axios.get).mockClear()

        component.openCreateDialog()
        component.materialForm.title = 'Neues Material'
        component.materialForm.category = 'Mathematik'
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    id: 2,
                    title: 'Neues Material',
                    category: 'Mathematik',
                },
            },
        })

        await component.saveMaterial()
        await flushPromises()

        expect(component.search).toBe('')
        expect(component.selectedCategory).toBe('__all_categories__')
        expect(component.page).toBe(1)
        expect(axios.get).toHaveBeenCalledWith('/api/admin/materials-v2/items', {
            params: {
                search: undefined,
                category: undefined,
                page: 1,
                per_page: 18,
            },
        })
    })

    it('hides ranked local tags from the overview and supports review actions', async () => {
        routeQuery.category = 'Biologie'
        window.localStorage.setItem('materials-v2-display-mode', 'large')

        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': {
                        template: '<div><slot /></div>',
                    },
                    'v-btn': {
                        template: '<button v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
                    },
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': {
                        template: '<div><slot /></div>',
                    },
                    'v-card-title': {
                        template: '<div><slot /></div>',
                    },
                    'v-chip': {
                        template: '<span v-bind="$attrs"><slot /></span>',
                    },
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': {
                        template: '<div><slot /></div>',
                    },
                    'v-dialog': {
                        template: '<div><slot /></div>',
                    },
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': {
                        template: '<div><slot /></div>',
                    },
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        expect(wrapper.text()).not.toContain('Aus dem Inhalt erkannt')
        expect(wrapper.text()).not.toContain('Chlorophyll')
        expect(wrapper.text()).not.toContain('welche')

        const component = wrapper.vm as any
        component.openEditDialog(component.items[0])
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.materials-v2-automatic-tag-review').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-material-dialog-close').attributes('title')).toBe('Schließen')
        const editAttachments = wrapper.find('.materials-v2-edit-attachments')
        expect(editAttachments.exists()).toBe(true)
        expect(editAttachments.text()).toContain('arbeitsblatt.docx')
        expect(editAttachments.find('[title="Anlagen hinzufügen"]').exists()).toBe(true)
        expect(editAttachments.find('[title="Vorschau"]').exists()).toBe(true)
        expect(editAttachments.find('[title="Herunterladen"]').exists()).toBe(true)
        expect(wrapper.find('.materials-v2-automatic-tag-summary').text()).toContain('Chlorophyll')
        expect(wrapper.find('.materials-v2-automatic-tag-names').classes()).toContain(
            'materials-v2-automatic-tag-names',
        )
        expect(wrapper.text()).not.toContain('Dokumente werden nach dem Speichern lokal gelesen.')
        expect(wrapper.find('.materials-v2-automatic-tag-list').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('9.75 Punkte')

        await wrapper.find('.materials-v2-automatic-tag-edit').trigger('click')

        expect(wrapper.find('.materials-v2-automatic-tag-list').exists()).toBe(true)
        expect(wrapper.text()).toContain('9.75 Punkte')
        expect(wrapper.text()).toContain('Tags erkannt')

        const suggestion = component.materialDialog.item.automatic_tag_suggestions[0]
        vi.mocked(axios.post).mockResolvedValueOnce({
            data: {
                data: {
                    ...component.materialDialog.item,
                    user_keywords: ['Biologie', 'Chlorophyll'],
                    generated_keywords: [],
                    automatic_tag_suggestions: [],
                },
            },
        })

        await component.convertAutomaticTag(component.materialDialog.item, suggestion)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/automatic-tags/convert', {
            tag_name: 'Chlorophyll',
        })
        expect(component.materialForm.keywords).toBe('Biologie, Chlorophyll')
        expect(notify).toHaveBeenCalledWith({
            message: 'Tag als eigenes Suchwort übernommen.',
            type: 'success',
            timeout: 3200,
        })

        await wrapper.find('.materials-v2-material-dialog-close').trigger('click')

        expect(component.materialDialog.open).toBe(false)
    })

    it('removes one automatic tag and can request a forced recalculation', async () => {
        const wrapper = shallowMount(MaterialsV2, {
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'v-alert': true,
                    'v-btn': true,
                    'v-btn-toggle': true,
                    'v-card': true,
                    'v-card-actions': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-chip': true,
                    'v-col': true,
                    'v-combobox': true,
                    'v-container': true,
                    'v-dialog': true,
                    'v-divider': true,
                    'v-file-input': true,
                    'v-icon': true,
                    'v-list': true,
                    'v-list-item': true,
                    'v-list-item-title': true,
                    'v-menu': true,
                    'v-pagination': true,
                    'v-row': true,
                    'v-sheet': true,
                    'v-skeleton-loader': true,
                    'v-spacer': true,
                    'v-text-field': true,
                    'v-textarea': true,
                },
            },
        })
        await flushPromises()

        const component = wrapper.vm as any
        const item = component.items[0]
        const suggestion = item.automatic_tag_suggestions[0]
        vi.mocked(axios.delete).mockResolvedValueOnce({
            data: {
                data: {
                    ...item,
                    generated_keywords: [],
                    automatic_tag_suggestions: [],
                },
            },
        })

        await component.removeAutomaticTag(item, suggestion)

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/automatic-tags', {
            data: {
                tag_name: 'Chlorophyll',
            },
        })
        expect(component.items[0].automatic_tag_suggestions).toEqual([])

        vi.mocked(axios.post).mockResolvedValueOnce({ data: { message: 'started' } })
        await component.recalculateAutomaticTags(component.items[0])
        await flushPromises()

        expect(axios.post).toHaveBeenCalledWith('/api/admin/materials-v2/items/1/recalculate-automatic-tags')
        expect(notify).toHaveBeenCalledWith({
            message: 'Automatische Tag-Erkennung gestartet.',
            type: 'info',
            timeout: 3200,
        })
    })
})
