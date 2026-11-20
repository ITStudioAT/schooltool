import { afterEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkPostmarkTest from '@/pages/admin/teaching/overview/components/WorkPostmarkTest.vue'

afterEach(() => vi.unstubAllGlobals())

it('previews the Postmark test for the authorised local account and sends only on the separate confirmation', async () => {
    const draft = { status: 'ready', draft_id: 'a'.repeat(64), sender: 'DI Günther Kron <guenther.kron@cdgym.info>',
        reply_to: 'guenther.kron@cdgym.at', recipient: 'kron@naturwelt.at',
        subject: 'Schooltool · Postmark-Versandtest', html: '<p>Test</p>' }
    const post = vi.fn().mockResolvedValueOnce({ data: draft })
        .mockResolvedValueOnce({ data: { status: 'accepted', message: 'Postmark hat die Testmail angenommen.' } })
        .mockResolvedValueOnce({ data: { ...draft, status: 'accepted', draft_id: null, message: 'Postmark hat die Testmail angenommen.' } })
    vi.stubGlobal('axios', { post })
    const makeWrapper = (email: string) => mount(WorkPostmarkTest, {
        props: { workId: 91 },
        global: { plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { email } } } } })],
            stubs: {
                'v-btn': { template: '<button><slot /></button>' },
                'v-dialog': { props: ['modelValue'], template: '<section v-if="modelValue" role="dialog"><slot /></section>' },
            },
        },
    })
    const otherAccount = makeWrapper('another@example.test')
    const schoolAccount = makeWrapper('guenther.kron@cdgym.at')
    try {
        expect(otherAccount.findAll('button').some(button => button.text() === 'Test')).toBe(false)
        expect(post).not.toHaveBeenCalled()
        await schoolAccount.get('button').trigger('click')
        await flushPromises()
        const dialog = schoolAccount.get('[role="dialog"]')
        expect(dialog.text()).toContain(draft.sender)
        expect(dialog.findAll('dd').some(detail => detail.text() === draft.reply_to)).toBe(true)
        expect(dialog.text()).toContain(draft.recipient)
        expect(dialog.text()).toContain(draft.subject)
        expect(post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_works/91/postmark-test/preview', null, { timeout: 30000 })
        await dialog.findAll('button').find(button => button.text() === 'Testmail über Postmark senden')!.trigger('click')
        await flushPromises()
        expect(post).toHaveBeenLastCalledWith('/api/admin/teaching/course_works/91/postmark-test/send', { draft_id: draft.draft_id }, { timeout: 30000 })
        expect(dialog.text()).toContain('Postmark hat die Testmail angenommen.')
        await dialog.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
        await schoolAccount.get('button').trigger('click')
        await flushPromises()
        const receipt = schoolAccount.get('[role="dialog"]')
        expect(receipt.get('iframe').attributes('srcdoc')).toBe(draft.html)
        expect(receipt.findAll('button').some(button => button.text() === 'Testmail über Postmark senden')).toBe(false)
    } finally { otherAccount.unmount(); schoolAccount.unmount() }
})
