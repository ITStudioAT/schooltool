import { cleanup, fireEvent, render, screen } from '@testing-library/vue'
import { afterEach, expect, it, vi } from 'vitest'
import axios from 'axios'
import MaturaReport from '@/pages/admin/helpers/matura/MaturaReport.vue'

vi.mock('axios', () => ({ default: { get: vi.fn() } }))
afterEach(cleanup)

it('discards an aborted report correction without sending an action', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: {
        summary: { actual_visits: 1, open: 0, cancelled: 0, toilet_seconds: 60 },
        rows: [{ id: 9, student_name: 'Gast Eins', room_name: 'Raum A', status: 'completed', status_label: 'Zurück im Raum' }],
        rooms: [], students: [], events: [],
    } })
    const view = render(MaturaReport, {
        props: { state: { session: { id: 1, status: 'active' }, rooms: [{ id: 1, name: 'Raum A' }], students: [] } },
        global: { stubs: {
            'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
            'v-card': { template: '<div><slot /></div>' },
        } },
    })
    await fireEvent.click(await screen.findByRole('button', { name: 'Korrigieren' }))
    await fireEvent.update(screen.getByLabelText('Grund'), 'Verworfen')
    await fireEvent.click(screen.getByRole('button', { name: 'Abbruch' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(view.emitted().action).toBeUndefined()
    await fireEvent.click(screen.getByRole('button', { name: 'Korrigieren' }))
    expect(screen.getByLabelText('Grund')).toHaveValue('')
    expect(screen.getByRole('button', { name: 'Bestätigen' })).toBeDisabled()
})
