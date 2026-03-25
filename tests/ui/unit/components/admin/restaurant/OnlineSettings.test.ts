import { describe, expect, it, vi } from 'vitest'
import OnlineSettings from '@/pages/admin/restaurant/components/OnlineSettings.vue'
import { useRestaurantStore } from '@/stores/admin/restaurant/RestaurantStore'

vi.mock('@/stores/admin/restaurant/RestaurantStore', () => ({
    useRestaurantStore: vi.fn(),
}))

describe('Restaurant online settings component', () => {
    it('lists weekdays from monday to sunday so sunday stays at the end of the previous week', () => {
        const options = (OnlineSettings as any).computed.dayOptions.call({})

        expect(options.map((option: { short: string }) => option.short)).toEqual(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'])
    })

    it('builds a human friendly scheduled preview text', () => {
        const ctx = {
            form: {
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
            },
            dayLabel(day: number) {
                return (OnlineSettings as any).methods.dayLabel.call(this, day)
            },
            weekPhrase(weekOffset: number) {
                return (OnlineSettings as any).methods.weekPhrase.call(this, weekOffset)
            },
        }

        expect((OnlineSettings as any).computed.previewText.call(ctx))
            .toBe('Sonntag 15:00 der Vorvorwoche bis Freitag 17:00 vor der Menüwoche')
    })

    it('applies the example preset for the common school workflow', () => {
        const ctx = {
            form: {
                order_start_mode: 'when_available',
            },
        }

        ;(OnlineSettings as any).methods.applyExamplePreset.call(ctx)

        expect(ctx.form).toEqual({
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
        })
    })

    it('saves online settings through the restaurant store', async () => {
        const updateOnlineSettings = vi.fn().mockResolvedValue({
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
        })

        vi.mocked(useRestaurantStore).mockReturnValue({
            updateOnlineSettings,
        } as never)

        const ctx = {
            canManageOnlineSettings: true,
            isSaving: false,
            form: {
                order_start_mode: 'scheduled',
                order_start_week_offset: 2,
                order_start_day_of_week: 0,
                order_start_time: '15:00',
                order_end_week_offset: 1,
                order_end_day_of_week: 5,
                order_end_time: '17:00',
            },
            syncForm: vi.fn(),
        }

        await (OnlineSettings as any).methods.save.call(ctx)

        expect(updateOnlineSettings).toHaveBeenCalledWith({
            order_start_mode: 'scheduled',
            order_start_week_offset: 2,
            order_start_day_of_week: 0,
            order_start_time: '15:00',
            order_end_week_offset: 1,
            order_end_day_of_week: 5,
            order_end_time: '17:00',
        })
        expect(ctx.syncForm).toHaveBeenCalled()
        expect(ctx.isSaving).toBe(false)
    })
})
