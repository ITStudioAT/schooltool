import { fireEvent, render, screen } from '@testing-library/vue'
import { describe, expect, it } from 'vitest'
import MaturaBoard from '@/pages/admin/helpers/matura/MaturaBoard.vue'

function state(overrides = {}) {
    return {
        session: { id: 1, status: 'active', waiting_places: 0 },
        actor: { manager: true, owns_station: true, room_id: null },
        rooms: [{ id: 1, name: 'Raum A', student_count: 2, away_count: 0 }],
        students: [{ id: 1, matura_room_id: 1, name: 'Müller Änne', class_name: '8A' }],
        visits: [],
        capacity: 1,
        reserved: 0,
        ...overrides,
    }
}

function renderBoard(data, disabled = false) {
    return render(MaturaBoard, {
        props: { state: data, disabled },
        global: {
            stubs: {
                VDialog: { props: ['modelValue'], template: '<div v-if="modelValue" role="dialog"><slot /></div>' },
                VCard: { template: '<div><slot /></div>' },
            },
        },
    })
}

const requested = { id: 9, student_id: 1, student_name: 'Müller Änne', room_id: 1, room_name: 'Raum A', status: 'requested', requested_at: '2026-10-04T09:00:00+02:00' }

describe('00-Manager station actions', () => {
    it('reserves capacity before allowing departure and blocks a full station', () => {
        renderBoard(state({ visits: [requested], reserved: 1 }))
        expect(screen.getByRole('button', { name: 'Platz freigeben' })).toBeDisabled()
        expect(screen.queryByRole('button', { name: 'Jetzt losschicken' })).not.toBeInTheDocument()
        expect(screen.getByText(/Im Raum warten/)).toBeInTheDocument()
    })

    it('sends the current visit and expected state with the room action', async () => {
        const view = renderBoard(state({ visits: [{ ...requested, status: 'approved' }], reserved: 1 }))
        await fireEvent.click(screen.getByRole('button', { name: 'Jetzt losschicken' }))
        expect(view.emitted().action).toEqual([[{ action: 'depart', visit_id: 9, expected_status: 'approved' }]])
    })

    it('disables actions during connection loss or an outstanding mutation', () => {
        renderBoard(state({ visits: [{ ...requested, status: 'approved' }] }), true)
        expect(screen.getByRole('button', { name: 'Jetzt losschicken' })).toBeDisabled()
    })

    it('requires a reason for corrections and preserves the expected visit state', async () => {
        const view = renderBoard(state({ visits: [requested] }))
        await fireEvent.click(screen.getByRole('button', { name: 'Stornieren' }))
        expect(screen.getByRole('button', { name: 'Bestätigen' })).toBeDisabled()
        await fireEvent.update(screen.getByLabelText('Grund'), 'Versehentlich ausgewählt')
        await fireEvent.click(screen.getByRole('button', { name: 'Bestätigen' }))
        expect(view.emitted().action).toEqual([[{ action: 'cancel', visit_id: 9, expected_status: 'requested', reason: 'Versehentlich ausgewählt' }]])
    })

    it('requires explicit takeover and carries the last observed supervisor', async () => {
        const view = renderBoard(state({ actor: { manager: false, room_id: 1, owns_station: false, name: 'Neue Aufsicht', current_access_id: 7, current_supervisor: { id: 7, name: 'Bisherige Aufsicht' } } }))
        await fireEvent.click(screen.getByRole('button', { name: 'Aufsicht ablösen' }))
        expect(view.emitted().action).toBeUndefined()
        await fireEvent.click(screen.getByRole('button', { name: 'Jetzt übernehmen' }))
        expect(view.emitted().action).toEqual([[{ action: 'claim', previous_access_id: 7 }]])
    })

    it('shows only the current room tasks for room supervisors', () => {
        renderBoard(state({ actor: { manager: false, room_id: 1, owns_station: true }, visits: [{ ...requested, room_id: 2, student_name: 'Andere Person' }] }))
        expect(screen.queryByText('Andere Person')).not.toBeInTheDocument()
        expect(screen.queryByRole('button', { name: 'Toilette betreten' })).not.toBeInTheDocument()
    })
})
