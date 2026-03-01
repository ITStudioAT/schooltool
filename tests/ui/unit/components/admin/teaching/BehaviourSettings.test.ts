import { describe, expect, it } from 'vitest'
import Behaviour from '@/pages/admin/teaching/settings/components/Behaviour.vue'

describe('Behaviour settings edit flow', () => {
    it('returns to edit list level on abort', () => {
        const methods = (Behaviour as any).methods
        const ctx = {
            action: 'teaching_behaviour_new_or_edit',
            edit_index: 2,
            is_editing: false,
        }

        methods.abort.call(ctx)

        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
        expect(ctx.is_editing).toBe(true)
    })

    it('returns to edit list level on save', async () => {
        const methods = (Behaviour as any).methods
        let payload: Record<string, unknown> | null = null

        const ctx: Record<string, unknown> = {
            behaviour_entries: [{ short_name: 'ALT', name: 'Alt' }],
            data: { short_name: 'NEU', name: 'Neu' },
            edit_index: 0,
            action: 'teaching_behaviour_new_or_edit',
            is_editing: false,
            teachingStore: {
                saveSettings: async (input: Record<string, unknown>) => {
                    payload = input
                    return true
                },
            },
        }

        await methods.save.call(ctx)

        expect(payload).toEqual({
            teaching_behaviour: [{ short_name: 'NEU', name: 'Neu' }],
        })
        expect(ctx.action).toBe('')
        expect(ctx.edit_index).toBeNull()
        expect(ctx.is_editing).toBe(true)
    })
})