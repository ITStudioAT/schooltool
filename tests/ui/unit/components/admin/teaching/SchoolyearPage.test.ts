import { describe, expect, it, vi } from 'vitest'
import Schoolyear from '@/pages/admin/teaching/schoolyear/Schoolyear.vue'

describe('Teaching schoolyear page', () => {
    it('builds schoolyear metadata string from available dates', () => {
        const methods = (Schoolyear as any).methods
        const ctx = {
            formatDate: methods.formatDate,
        }

        const meta = methods.buildSchoolyearMeta.call(ctx, {
            from: '2025-09-08',
            sem_2_start: '2026-02-16',
            until: '2026-07-10',
        })

        expect(meta).toContain('Start')
        expect(meta).toContain('Sem 2')
        expect(meta).toContain('Ende')
    })

    it('sorts schoolyears by name descending', () => {
        const ctx = {
            schoolyears: [{ name: '2024/25' }, { name: '2026/27' }, { name: '2025/26' }],
        }

        const sorted = (Schoolyear as any).computed.sortedSchoolyears.call(ctx)

        expect(sorted.map((item: { name: string }) => item.name)).toEqual(['2026/27', '2025/26', '2024/25'])
    })

    it('marks selected schoolyear as active when ids match', () => {
        const activeCtx = {
            selectedSchoolyear: { id: 10 },
            activeSchoolyear: { id: 10 },
        }
        const inactiveCtx = {
            selectedSchoolyear: { id: 11 },
            activeSchoolyear: { id: 10 },
        }

        expect((Schoolyear as any).computed.isSelectedSchoolyearActive.call(activeCtx)).toBe(true)
        expect((Schoolyear as any).computed.isSelectedSchoolyearActive.call(inactiveCtx)).toBe(false)
    })

    it('selects a schoolyear for preview without activating it', () => {
        const methods = (Schoolyear as any).methods
        const setActiveSchoolyear = vi.fn()
        const loadConfig = vi.fn()

        const ctx: Record<string, unknown> = {
            is_loading: false,
            selected_schoolyear_id: 10,
            schoolyearStore: { setActiveSchoolyear },
            adminStore: { loadConfig },
        }

        methods.selectSchoolyear.call(ctx, { id: 11 })

        expect(ctx.selected_schoolyear_id).toBe(11)
        expect(setActiveSchoolyear).not.toHaveBeenCalled()
        expect(loadConfig).not.toHaveBeenCalled()
    })

    it('activates only the selected preview year', async () => {
        const methods = (Schoolyear as any).methods
        const setActiveSchoolyear = vi.fn().mockResolvedValue(true)
        const loadConfig = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, unknown> = {
            is_loading: false,
            pending_schoolyear_id: null,
            selectedSchoolyear: { id: 11 },
            config: {
                selected_schoolyear: { id: 10 },
            },
            schoolyearStore: {
                setActiveSchoolyear,
            },
            adminStore: {
                loadConfig,
            },
            isActiveSchoolyear: methods.isActiveSchoolyear,
        }

        await methods.activateSelectedSchoolyear.call(ctx)

        expect(setActiveSchoolyear).toHaveBeenCalledWith(11)
        expect(loadConfig).toHaveBeenCalledTimes(1)
        expect(ctx.pending_schoolyear_id).toBeNull()
        expect(ctx.is_loading).toBe(false)
    })

    it('does not activate when selected year is already active', async () => {
        const methods = (Schoolyear as any).methods
        const setActiveSchoolyear = vi.fn().mockResolvedValue(true)
        const loadConfig = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, unknown> = {
            is_loading: false,
            pending_schoolyear_id: null,
            selectedSchoolyear: { id: 10 },
            config: {
                selected_schoolyear: { id: 10 },
            },
            schoolyearStore: {
                setActiveSchoolyear,
            },
            adminStore: {
                loadConfig,
            },
            isActiveSchoolyear: methods.isActiveSchoolyear,
        }

        await methods.activateSelectedSchoolyear.call(ctx)

        expect(setActiveSchoolyear).not.toHaveBeenCalled()
        expect(loadConfig).not.toHaveBeenCalled()
    })
})
