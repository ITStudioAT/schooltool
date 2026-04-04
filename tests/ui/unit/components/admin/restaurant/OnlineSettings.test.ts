import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import OnlineSettings from '@/pages/admin/restaurant/components/OnlineSettings.vue'
import { useMenuPlanStore } from '@/stores/admin/restaurant/MenuPlanStore'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

vi.mock('@/stores/admin/restaurant/MenuPlanStore', () => ({
    useMenuPlanStore: vi.fn(),
}))

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: vi.fn(),
}))

function mountOnlineSettings(options: {
    plans?: Array<Record<string, unknown>>
    onlineSettings?: Record<string, unknown>
} = {}) {
    vi.mocked(useMenuPlanStore).mockReturnValue({
        plans: options.plans || [
            { id: 1, start_date: '2026-03-23', end_date: '2026-03-27', is_available: true },
            { id: 2, start_date: '2026-03-30', end_date: '2026-04-03', is_available: true },
        ],
        isLoaded: true,
        load: vi.fn(),
    } as never)

    vi.mocked(useRestaurantStore).mockReturnValue({
        onlineSettings: {
            visibility_start_mode: 'scheduled',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 6,
            visibility_start_time: '14:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'plan_end',
            ...(options.onlineSettings || {}),
        },
        canManageOnlineSettings: true,
        updateOnlineSettings: vi.fn(),
    } as never)

    return mount(OnlineSettings, {
        global: {
            mocks: {
                $pinia: {},
            },
            stubs: {
                'v-col': { template: '<div><slot /></div>' },
            },
        },
    })
}

describe('Restaurant online settings component', () => {
    it('starts in a read-only mode with summary values and an edit button', () => {
        const wrapper = mountOnlineSettings()

        expect(wrapper.get('[data-testid="online-settings-edit-button"]').text()).toContain('Bearbeiten')
        expect(wrapper.get('[data-testid="order-start-summary"]').text()).toContain('Sonntag 15:00 der Vorvorwoche')
        expect(wrapper.get('[data-testid="order-end-summary"]').text()).toContain('Freitag 17:00 vor der Menüwoche')
        expect(wrapper.get('[data-testid="visibility-start-summary"]').text()).toContain('Samstag 14:00 der Vorvorwoche')
        expect(wrapper.get('[data-testid="visibility-end-summary"]').text()).toContain('Bis zum letzten Tag des Menüplans')
        expect(wrapper.find('[data-testid="online-settings-save-button"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="open-start-day-dialog"]').exists()).toBe(false)
        expect(wrapper.find('[data-testid="open-visibility-start-day-dialog"]').exists()).toBe(false)
    })

    it('lists weekdays from monday to sunday so sunday stays at the end of the previous week', () => {
        const options = (OnlineSettings as any).computed.dayOptions.call({})

        expect(options.map((option: { short: string }) => option.short)).toEqual(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'])
    })

    it('builds a human friendly scheduled preview text including visibility', () => {
        const ctx = {
            form: {
                visibility_start_mode: 'scheduled',
                visibility_start_week_offset: 2,
                visibility_start_day_of_week: 6,
                visibility_start_time: '14:00',
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
                visibility_end_mode: 'week_end',
            },
            dayLabel(day: number) {
                return (OnlineSettings as any).methods.dayLabel.call(this, day)
            },
            weekPhrase(weekOffset: number) {
                return (OnlineSettings as any).methods.weekPhrase.call(this, weekOffset)
            },
            visibilityPhrase() {
                return (OnlineSettings as any).methods.visibilityPhrase.call(this)
            },
        }

        expect((OnlineSettings as any).computed.previewText.call(ctx))
            .toBe('Sichtbar ab Samstag 14:00 der Vorvorwoche. Bestellbar ab Sonntag 15:00 der Vorvorwoche bis Freitag 17:00 vor der Men\u00fcwoche. Sichtbar bis zum Ende der Woche.')
    })

    it('builds a preview text when visibility starts once ordering starts', () => {
        const ctx = {
            form: {
                visibility_start_mode: 'when_orderable',
                visibility_start_week_offset: 2,
                visibility_start_day_of_week: 6,
                visibility_start_time: '14:00',
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
                visibility_end_mode: 'plan_end',
            },
            dayLabel(day: number) {
                return (OnlineSettings as any).methods.dayLabel.call(this, day)
            },
            weekPhrase(weekOffset: number) {
                return (OnlineSettings as any).methods.weekPhrase.call(this, weekOffset)
            },
            visibilityPhrase() {
                return (OnlineSettings as any).methods.visibilityPhrase.call(this)
            },
        }

        expect((OnlineSettings as any).computed.previewText.call(ctx))
            .toBe('Sichtbar sobald bestellbar. Bestellbar ab Sonntag 15:00 der Vorvorwoche bis Freitag 17:00 vor der Men\u00fcwoche. Sichtbar bis zum letzten Tag des Men\u00fcplans.')
    })

    it('applies the example preset for the common school workflow', () => {
        const ctx = {
            form: {
                order_start_mode: 'when_available',
            },
        }

        ;(OnlineSettings as any).methods.applyExamplePreset.call(ctx)

        expect(ctx.form).toEqual({
            visibility_start_mode: 'scheduled',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 0,
            visibility_start_time: '15:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'plan_end',
        })
    })

    it('renders marker stacks inside the highlighted preview days', () => {
        const wrapper = mountOnlineSettings()

        const visibleDays = wrapper.findAll('.online-settings__day-chip.has-visible-day')
        const orderableDays = wrapper.findAll('.online-settings__day-chip.has-orderable-day')
        const visibilityChip = wrapper.find('.online-settings__day-chip.has-visibility-start')
        const startChip = wrapper.find('.online-settings__day-chip.has-start')
        const endChip = wrapper.find('.online-settings__day-chip.has-end')

        expect(wrapper.findAll('.online-settings__day-chip')).toHaveLength(21)
        expect(visibleDays).toHaveLength(14)
        expect(orderableDays.length).toBeGreaterThan(1)
        expect(visibilityChip.exists()).toBe(true)
        expect(startChip.exists()).toBe(true)
        expect(endChip.exists()).toBe(true)
        expect(visibilityChip.find('.online-settings__marker-stack').exists()).toBe(false)
        expect(startChip.find('.online-settings__marker-stack').exists()).toBe(true)
        expect(endChip.find('.online-settings__marker-stack').exists()).toBe(true)
        expect(visibilityChip.find('.online-settings__visibility-icon').exists()).toBe(true)
        expect(wrapper.findAll('.online-settings__visibility-icon')).toHaveLength(14)
        expect(wrapper.findAll('.online-settings__orderable-icon')).toHaveLength(orderableDays.length)
        expect(startChip.find('.online-settings__orderable-icon').exists()).toBe(true)
        expect(endChip.find('.online-settings__orderable-icon').exists()).toBe(true)
        expect(visibilityChip.findAll('.online-settings__marker')).toHaveLength(0)
        expect(startChip.findAll('.online-settings__marker')).toHaveLength(1)
        expect(endChip.findAll('.online-settings__marker')).toHaveLength(1)
        expect(visibilityChip.text()).not.toContain('Sichtbar 14:00')
        expect(startChip.text()).toContain('Start 15:00')
        expect(endChip.text()).toContain('Ende 17:00')
    })

    it('keeps only the three-week preview without the extra explainer blocks', () => {
        const wrapper = mountOnlineSettings()

        expect(wrapper.findAll('.online-settings__timeline-week-card')).toHaveLength(3)
        expect(wrapper.text()).not.toContain('So wirkt der Zeitraum f\u00fcr Ihre Men\u00fcwoche')
        expect(wrapper.text()).not.toContain('Legt fest, ab wann der Men\u00fcplan f\u00fcr User angezeigt wird')
        expect(wrapper.text()).not.toContain('Aktuell angezeigt')
        expect(wrapper.text()).not.toContain('Momentan bestellbar')
        expect(wrapper.text()).not.toContain('Ende: Freitag 17:00 vor der Men\u00fcwoche')
    })

    it('groups bestellbar and sichtbar settings into two cards', () => {
        const wrapper = mountOnlineSettings()

        expect(wrapper.findAll('.online-settings__panel')).toHaveLength(2)
        expect(wrapper.get('[data-testid="order-panel"]').text()).toContain('Bestellbar')
        expect(wrapper.get('[data-testid="order-panel"]').text()).toContain('Ab wann bestellbar?')
        expect(wrapper.get('[data-testid="order-panel"]').text()).toContain('Bis wann bestellbar?')
        expect(wrapper.get('[data-testid="visibility-panel"]').text()).toContain('Sichtbar')
        expect(wrapper.get('[data-testid="visibility-panel"]').text()).toContain('Ab wann sichtbar?')
        expect(wrapper.get('[data-testid="visibility-panel"]').text()).toContain('Sichbarkeitsende')
    })

    it('shows an extra card with menu plans inside the displayed preview range', async () => {
        const wrapper = mountOnlineSettings({
            plans: [
                { id: 1, start_date: '2026-03-23', end_date: '2026-03-27', is_available: true },
                { id: 2, start_date: '2026-03-30', end_date: '2026-04-03', is_available: true },
                { id: 3, start_date: '2026-03-11', end_date: '2026-03-13', is_available: true },
            ],
        })
        ;(wrapper.vm as any).previewNow = new Date('2026-03-20T12:00:00')
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="plan-status-card"]').text()).toContain('Men')
        expect(wrapper.get('[data-testid="plan-status-card"]').text()).toContain('09.03.2026 - 29.03.2026')
        expect(wrapper.find('[data-testid="plan-status-1"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="plan-status-3"]').exists()).toBe(true)
        expect(wrapper.find('[data-testid="plan-status-2"]').exists()).toBe(false)
    })

    it('shows the empty plan card with the correct plural label', () => {
        const wrapper = mountOnlineSettings({
            plans: [],
        })

        expect(wrapper.get('[data-testid="plan-status-card"]').text()).toContain('0 Pläne')
    })

    it('labels menu plans as visible orderable upcoming or already past', () => {
        const wrapper = mountOnlineSettings()
        const currentPlan = { id: 2, start_date: '2026-03-30', end_date: '2026-04-03', is_available: true }
        const earlierPlan = { id: 1, start_date: '2026-03-23', end_date: '2026-03-27', is_available: true }

        ;(wrapper.vm as any).previewNow = new Date('2026-03-01T12:00:00')
        expect((wrapper.vm as any).planVisibilityState(earlierPlan).label).toBe('Nicht sichtbar')
        expect((wrapper.vm as any).planOrderState(earlierPlan).label).toBe('Nicht bestellbar')

        ;(wrapper.vm as any).previewNow = new Date('2026-03-26T12:00:00')
        expect((wrapper.vm as any).planVisibilityState(currentPlan).label).toBe('Sichtbar')
        expect((wrapper.vm as any).planOrderState(currentPlan).label).toBe('Bestellbar')

        ;(wrapper.vm as any).previewNow = new Date('2026-03-29T12:00:00')
        expect((wrapper.vm as any).planVisibilityState(earlierPlan).label).toBe('Nicht mehr sichtbar')
        expect((wrapper.vm as any).planOrderState(earlierPlan).label).toBe('Nicht mehr bestellbar')
    })

    it('opens a visibility-start dialog from the sichtbar panel and applies the selected time', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()
        const visibilityPanel = wrapper.get('[data-testid="visibility-panel"]')
        const visibilityModeToggle = visibilityPanel.find('.online-settings__mode-toggle')

        expect(visibilityPanel.get('[data-testid="open-visibility-start-day-dialog"]').text()).toContain('Starttag festlegen')
        expect(visibilityModeToggle.text()).toContain('Sobald verf\u00fcgbar')
        expect(visibilityModeToggle.text()).toContain('Sobald bestellbar')
        expect(visibilityModeToggle.text()).toContain('Fester Tag')
        expect(visibilityModeToggle.text()).toContain('Starttag festlegen')

        ;(wrapper.vm as any).openVisibilitySelectionDialog()
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="visibility-start-day-dialog"]').text()).toContain('Starttag festlegen')

        ;(wrapper.vm as any).visibilityDialogDraft = {
            week_offset: 2,
            day_of_week: 5,
            time: '12:45',
        }
        ;(wrapper.vm as any).applyVisibilitySelectionDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.visibility_start_week_offset).toBe(2)
        expect((wrapper.vm as any).form.visibility_start_day_of_week).toBe(5)
        expect((wrapper.vm as any).form.visibility_start_time).toBe('12:45')
        expect(wrapper.find('[data-testid="visibility-start-day-dialog"]').exists()).toBe(false)
    })

    it('blocks a visibility start that would lie after the scheduled order start', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).openVisibilitySelectionDialog()
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).visibilityDialogDraft = {
            week_offset: 1,
            day_of_week: 1,
            time: '09:00',
        }
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="visibility-start-order-warning"]').text())
            .toContain('Der Starttag der Sichtbarkeit darf nicht nach dem Bestellstart liegen.')
        expect(wrapper.find('[data-testid="apply-visibility-start-day-dialog"]').exists()).toBe(false)

        ;(wrapper.vm as any).applyVisibilitySelectionDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.visibility_start_week_offset).toBe(2)
        expect((wrapper.vm as any).form.visibility_start_day_of_week).toBe(6)
        expect((wrapper.vm as any).form.visibility_start_time).toBe('14:00')
    })

    it('disables impossible weeks and days in the visibility-start dialog', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).openVisibilitySelectionDialog()
        await wrapper.vm.$nextTick()

        const weekButtons = wrapper.findAll('[data-testid="visibility-start-day-dialog"] .online-settings__choice-row').at(0)!
            .findAll('button')
        const dayButtons = wrapper.findAll('[data-testid="visibility-start-day-dialog"] .online-settings__choice-row').at(1)!
            .findAll('button')

        expect((weekButtons[0].element as HTMLButtonElement).disabled).toBe(false)
        expect((weekButtons[1].element as HTMLButtonElement).disabled).toBe(true)
        expect((weekButtons[2].element as HTMLButtonElement).disabled).toBe(true)

        ;(wrapper.vm as any).visibilityDialogDraft.time = '15:30'
        await wrapper.vm.$nextTick()

        expect((dayButtons[5].element as HTMLButtonElement).disabled).toBe(false)
        expect((dayButtons[6].element as HTMLButtonElement).disabled).toBe(true)
    })

    it('opens a start-day dialog from the bestellbar panel and applies the selected start time', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()
        const orderPanel = wrapper.get('[data-testid="order-panel"]')
        const startModeToggle = orderPanel.find('.online-settings__mode-toggle')

        expect(wrapper.find('[data-testid="preview-start-scheduled-controls"]').exists()).toBe(false)
        expect(orderPanel.get('[data-testid="open-start-day-dialog"]').text()).toContain('Starttag festlegen')
        expect(orderPanel.text()).toContain('Ab wann bestellbar?')
        expect(orderPanel.text()).toContain('Bestellstart')
        expect(startModeToggle.text()).toContain('Sobald verf\u00fcgbar')
        expect(startModeToggle.text()).toContain('Fester Tag')
        expect(startModeToggle.text()).toContain('Starttag festlegen')

        ;(wrapper.vm as any).openStartSelectionDialog()
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="start-day-dialog"]').text()).toContain('Starttag festlegen')

        ;(wrapper.vm as any).startDialogDraft = {
            week_offset: 1,
            day_of_week: 5,
            time: '16:30',
        }
        ;(wrapper.vm as any).applyStartSelectionDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.order_start_week_offset).toBe(1)
        expect((wrapper.vm as any).form.order_start_day_of_week).toBe(5)
        expect((wrapper.vm as any).form.order_start_time).toBe('16:30')
        expect(wrapper.find('[data-testid="start-day-dialog"]').exists()).toBe(false)
    })

    it('moves the visibility start with the order start when the new order start is earlier', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).openStartSelectionDialog()
        await wrapper.vm.$nextTick()

        ;(wrapper.vm as any).startDialogDraft = {
            week_offset: 2,
            day_of_week: 5,
            time: '12:00',
        }
        ;(wrapper.vm as any).applyStartSelectionDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.order_start_week_offset).toBe(2)
        expect((wrapper.vm as any).form.order_start_day_of_week).toBe(5)
        expect((wrapper.vm as any).form.order_start_time).toBe('12:00')
        expect((wrapper.vm as any).form.visibility_start_week_offset).toBe(2)
        expect((wrapper.vm as any).form.visibility_start_day_of_week).toBe(5)
        expect((wrapper.vm as any).form.visibility_start_time).toBe('12:00')
    })

    it('normalizes loaded settings so visibility never starts after ordering', async () => {
        const wrapper = mountOnlineSettings()

        ;(wrapper.vm as any).syncForm({
            visibility_start_mode: 'scheduled',
            visibility_start_week_offset: 1,
            visibility_start_day_of_week: 1,
            visibility_start_time: '09:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'plan_end',
        })
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.visibility_start_week_offset).toBe(2)
        expect((wrapper.vm as any).form.visibility_start_day_of_week).toBe(0)
        expect((wrapper.vm as any).form.visibility_start_time).toBe('15:00')
    })

    it('opens an end-day dialog from the bestellbar panel and applies the selected end time', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()
        const orderPanel = wrapper.get('[data-testid="order-panel"]')
        const endBlock = orderPanel.findAll('.online-settings__panel-block')[1]
        const endModeToggle = endBlock.find('.online-settings__mode-toggle')

        expect(orderPanel.get('[data-testid="open-end-day-dialog"]').text()).toContain('Endtag festlegen')
        expect(endBlock.text()).toContain('Bestellende')
        expect(endBlock.text()).toContain('Fixer Tag')
        expect(endModeToggle.text()).toContain('Fixer Tag')
        expect(endModeToggle.text()).toContain('Endtag festlegen')
        expect(orderPanel.text()).not.toContain('Bestellende fest. Woche, Tag und Uhrzeit legen Sie direkt in der Vorschau fest.')

        ;(wrapper.vm as any).openEndSelectionDialog()
        await wrapper.vm.$nextTick()

        expect(wrapper.get('[data-testid="end-day-dialog"]').text()).toContain('Endtag festlegen')

        ;(wrapper.vm as any).endDialogDraft = {
            week_offset: 0,
            day_of_week: 2,
            time: '18:15',
        }
        ;(wrapper.vm as any).applyEndSelectionDialog()
        await wrapper.vm.$nextTick()

        expect((wrapper.vm as any).form.order_end_week_offset).toBe(0)
        expect((wrapper.vm as any).form.order_end_day_of_week).toBe(2)
        expect((wrapper.vm as any).form.order_end_time).toBe('18:15')
        expect(wrapper.find('[data-testid="end-day-dialog"]').exists()).toBe(false)
    })

    it('shows both visibility options in the visibility panel while editing', async () => {
        const wrapper = mountOnlineSettings()
        ;(wrapper.vm as any).beginEdit()
        await wrapper.vm.$nextTick()
        const visibilityPanel = wrapper.get('[data-testid="visibility-panel"]')

        expect(visibilityPanel.text()).toContain('Sobald bestellbar')
        expect(visibilityPanel.text()).toContain('Sichbarkeitsende')
        expect(visibilityPanel.text()).toContain('Bis zum letzten Tag des Men\u00fcplans')
        expect(visibilityPanel.text()).toContain('Bis zum Ende der Woche')
    })

    it('renders combined header labels for automatic ordering and availability visibility', () => {
        const wrapper = mountOnlineSettings({
            onlineSettings: {
                visibility_start_mode: 'when_available',
                order_start_mode: 'when_available',
            },
        })

        expect(wrapper.get('[data-testid="order-panel"]').text()).toContain('Automatisch bis Freitag 17:00 vor der Men\u00fcwoche')
        expect(wrapper.get('[data-testid="visibility-panel"]').text()).toContain('Sobald verf\u00fcgbar bis zum letzten Tag des Men\u00fcplans')
    })

    it('saves online settings through the restaurant store', async () => {
        const updateOnlineSettings = vi.fn().mockResolvedValue({
            visibility_start_mode: 'scheduled',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 6,
            visibility_start_time: '14:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'week_end',
        })

        vi.mocked(useRestaurantStore).mockReturnValue({
            updateOnlineSettings,
        } as never)

        const ctx = {
            canManageOnlineSettings: true,
            isEditingOnlineSettings: true,
            isSaving: false,
            form: {
                visibility_start_mode: 'scheduled',
                visibility_start_week_offset: 2,
                visibility_start_day_of_week: 6,
                visibility_start_time: '14:00',
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
                visibility_end_mode: 'week_end',
            },
            syncForm: vi.fn(),
            closeAllDialogs: vi.fn(),
        }

        await (OnlineSettings as any).methods.save.call(ctx)

        expect(updateOnlineSettings).toHaveBeenCalledWith({
            visibility_start_mode: 'scheduled',
            visibility_start_week_offset: 2,
            visibility_start_day_of_week: 6,
            visibility_start_time: '14:00',
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
            visibility_end_mode: 'week_end',
        })
        expect(ctx.syncForm).toHaveBeenCalled()
        expect(ctx.closeAllDialogs).toHaveBeenCalled()
        expect(ctx.isSaving).toBe(false)
        expect(ctx.isEditingOnlineSettings).toBe(false)
    })
})
