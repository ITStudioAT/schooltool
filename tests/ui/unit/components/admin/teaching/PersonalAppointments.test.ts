import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import axios from 'axios'
import PersonalAppointments from '@/pages/admin/teaching/overview/components/PersonalAppointments.vue'
import { nextPersonalAppointmentDate, personalAppointmentDateShortcuts, personalAppointmentDuration, personalAppointmentHeight, personalAppointmentKinds, personalAppointmentLabel, personalAppointmentOccurrences, personalAppointmentTimeline } from '@/helpers/teachingPersonalAppointments'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() } }))
afterEach(() => vi.clearAllMocks())

const single = { id: 1, kind: 'lunch_supervision', title: 'Mittagspause', date: '2026-10-12', starts_at: '12:30', ends_at: '13:20', repeat_until: null }
const series = { ...single, id: 2, date: '2026-10-05', repeat_until: '2026-11-02' }
const methods = (PersonalAppointments as any).methods
function context(overrides = {}) {
    return { ...methods, contextKey: '7:2:3', form: { ...single, id: undefined, weekly: false }, canSave: true, saving: false, loading: false, loadError: '', load: vi.fn(), requestId: 0, schoolyear: { id: 3 }, $emit: vi.fn(), ...overrides }
}

describe('Personal appointment dates and forms', () => {
    it('keeps exception text confined to the saved date, hour and entry across daylight saving', () => {
        const appointment = { ...series, school_hours: [1, 2], time_segments: [{ hour: 1, starts_at: '08:00', ends_at: '08:50' }, { hour: 2, starts_at: '09:00', ends_at: '09:50' }], title_exceptions: { '2026-10-26:1': 'Einzeltext' } }
        const occurrences = personalAppointmentOccurrences([appointment, { ...appointment, id: 3, title_exceptions: {} }], '2026-10-19', '2026-11-02')
        expect(occurrences.filter((entry) => personalAppointmentLabel(entry) === 'Einzeltext').map((entry) => [entry.id, entry.occurrenceDate, entry.school_hours])).toEqual([[2, '2026-10-26', [1]]])
        expect(occurrences.find((entry) => entry.id === 2 && entry.occurrenceDate === '2026-10-26' && entry.occurrenceHour === 2)?.time_segments[0].starts_at).toBe('09:00')
    })

    it('opens a clicked occurrence with text-only saving and keeps series editing available', async () => {
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3' }, global: { stubs: { 'v-checkbox': true } } })
        try {
            const vm = wrapper.vm as any
            vm.open({ ...series, occurrenceDate: '2026-10-26' })
            await wrapper.vm.$nextTick()
            expect(vm.editScope).toBe('occurrence')
            expect(wrapper.findAll('v-text-field').some((field) => field.attributes('label') === 'Datum')).toBe(false)
            vm.occurrenceTitle = 'Einzeltext'
            vi.mocked(axios.put).mockResolvedValue({})
            await vm.save()
            expect(axios.put).toHaveBeenCalledWith(expect.stringContaining('/2/occurrence'), { date: '2026-10-26', hour: null, title: 'Einzeltext', reset: false })
            vm.open({ ...series, occurrenceDate: '2026-10-26' })
            vm.editScope = 'series'
            await wrapper.vm.$nextTick()
            expect(wrapper.findAll('v-text-field').some((field) => field.attributes('label') === 'Datum')).toBe(true)
        } finally { wrapper.unmount() }
    })
    it('retains each day\'s free time before and between outside appointments', () => {
        const placement = (starts_at: string, ends_at: string) => ({ segments: [{ starts_at, ends_at }] })
        const tuesday = personalAppointmentTimeline([placement('17:50', '20:15')], '16:50')
        const friday = personalAppointmentTimeline([placement('17:50', '18:35'), placement('17:05', '17:50'), placement('19:00', '19:50')], '16:50')
        expect(tuesday.map((entry) => entry.gapMinutes)).toEqual([60])
        expect(friday.map((entry) => entry.gapMinutes)).toEqual([15, 0, 25])
        expect(friday.map((entry) => entry.segments[0].starts_at)).toEqual(['17:05', '17:50', '19:00'])
        const measurements = [{ minutes: 50, height: 50 }]
        expect(personalAppointmentHeight(tuesday[0].gapMinutes, measurements)).toBe(60)
        expect(personalAppointmentHeight(friday[0].gapMinutes, measurements)).toBe(15)
        expect(personalAppointmentTimeline([placement('17:05', '18:00'), placement('17:50', '18:35')], '16:50').map((entry) => entry.gapMinutes)).toEqual([15, 0])
    })
    it('scales outside durations against measured school hours independently for each appointment', () => {
        const measurements = [{ minutes: 50, height: 60 }, { minutes: 50, height: 60 }, { minutes: 50, height: 90 }]
        for (const [end, expected] of [['18:25', 30], ['18:50', 60], ['19:40', 120], ['20:00', 144]] as const) {
            const duration = personalAppointmentDuration([{ starts_at: '18:00', ends_at: end }])
            expect(personalAppointmentHeight(duration, measurements)).toBe(expected)
        }
        expect(personalAppointmentHeight(50, [{ minutes: 40, height: 80 }])).toBe(100)
        expect(personalAppointmentHeight(50, [])).toBeNull()
        expect(personalAppointmentDuration([{ starts_at: 'invalid', ends_at: '20:00' }, { starts_at: '20:00', ends_at: '19:00' }])).toBe(0)
        expect(personalAppointmentDuration([{ starts_at: '07:00', ends_at: '07:25' }, { starts_at: '18:00', ends_at: '18:25' }])).toBe(50)
    })
    it('supports single midday supervision and day standby without repetition', () => {
        expect(personalAppointmentKinds.map((kind) => kind.title)).toEqual(['Allgemeine Bereitschaft', 'Betreute Mittagspause', 'Pausenaufsicht', 'Sondereinsatz', 'Sprechstunde', 'Supplierbereitschaft', 'Tagesbetreuung'])
        for (const kind of personalAppointmentKinds) expect(personalAppointmentLabel({ kind: kind.value })).toBe(kind.title)
        expect(personalAppointmentOccurrences([single], '2026-10-12', '2026-10-18')).toEqual([{ ...single, occurrenceDate: '2026-10-12' }])
        expect(personalAppointmentOccurrences([single], '2026-10-13', '2026-10-18')).toEqual([])
        const state = context()
        methods.open.call(state)
        expect(state.form.weekly).toBe(false)
    })

    it('expands only matching weekly weekdays in the displayed interval through the inclusive end date', () => {
        expect(personalAppointmentOccurrences([series], '2026-10-13', '2026-11-02').map((item) => item.occurrenceDate)).toEqual(['2026-10-19', '2026-10-26', '2026-11-02'])
        expect(personalAppointmentOccurrences([series], '2026-11-03', '2026-11-30')).toEqual([])
        expect(personalAppointmentOccurrences([series], '2026-11-03', '2026-10-01')).toEqual([])
    })

    it('preserves local clock times across daylight saving changes and finds the next actual occurrence', () => {
        const occurrences = personalAppointmentOccurrences([series], '2026-10-19', '2026-11-02')
        expect(occurrences.map((item) => item.starts_at)).toEqual(['12:30', '12:30', '12:30'])
        expect(nextPersonalAppointmentDate([series], '2026-10-13')).toBe('2026-10-19')
        expect(nextPersonalAppointmentDate([series], '2026-11-03')).toBeNull()
    })

    it('requires a later ending time and a bounded weekly end date', () => {
        const canSave = (PersonalAppointments as any).computed.canSave
        expect(canSave.call({ form: { ...single, weekly: false } })).toBe(true)
        expect(canSave.call({ form: { ...single, ends_at: '12:30', weekly: false } })).toBe(false)
        expect(canSave.call({ form: { ...single, weekly: true } })).toBe(false)
        expect(canSave.call({ form: { ...series, weekly: true } })).toBe(true)
    })

    it('saves a single appointment and reloads persistent data', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { data: single } })
        const state = context()
        await methods.save.call(state)
        expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('/personal_appointments'), expect.objectContaining({ weekly: false, repeat_until: null, kind: 'lunch_supervision' }))
        expect(state.load).toHaveBeenCalledOnce()
    })

    it('edits the saved series and deletes it only after confirmation', async () => {
        vi.mocked(axios.put).mockResolvedValueOnce({ data: { data: series } })
        vi.mocked(axios.delete).mockResolvedValueOnce({})
        const state = context()
        methods.open.call(state, { ...series, occurrenceDate: '2026-10-26' })
        expect(state.form.date).toBe('2026-10-05')
        state.editScope = 'series'
        await methods.save.call(state)
        expect(axios.put).toHaveBeenCalledWith(expect.stringContaining('/personal_appointments/2'), expect.objectContaining({ date: '2026-10-05', weekly: true, repeat_until: '2026-11-02' }))
        await methods.remove.call(state)
        expect(axios.delete).not.toHaveBeenCalled()
        state.confirmDelete = true
        await methods.remove.call(state)
        expect(axios.delete).toHaveBeenCalledWith(expect.stringContaining('/personal_appointments/2'))
    })

    it('keeps validation errors visible and does not close an unsaved form', async () => {
        vi.mocked(axios.post).mockRejectedValueOnce({ response: { data: { errors: { ends_at: ['Ende muss nach Beginn liegen.'] } } } })
        const state = context({ dialog: true })
        await methods.save.call(state)
        expect(state.dialog).toBe(true)
        expect(state.errors.ends_at).toEqual(['Ende muss nach Beginn liegen.'])
        expect(state.load).not.toHaveBeenCalled()
    })

    it('discards a response from an old teacher or schoolyear after the context changes', async () => {
        let finishOld
        vi.mocked(axios.get).mockImplementationOnce(() => new Promise((resolve) => { finishOld = resolve }))
            .mockResolvedValueOnce({ data: { data: [single] } })
        const state = context({ load: methods.load })
        const pending = methods.load.call(state)
        state.contextKey = '8:2:4'
        await methods.load.call(state)
        finishOld({ data: { data: [series] } })
        await pending
        expect(state.$emit).toHaveBeenLastCalledWith('loaded', [single])
    })

    it('clears a pending loading state when no schoolyear is selected', async () => {
        const state = context({ loading: true, schoolyear: null })
        await methods.load.call(state)
        expect(state.loading).toBe(false)
        expect(axios.get).not.toHaveBeenCalled()
    })

    it('shows the recurrence controls and an explicit series-wide editing hint', async () => {
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3' }, global: { stubs: { 'v-checkbox': true } } })
        try {
            ;(wrapper.vm as any).open(series)
            await wrapper.vm.$nextTick()
            expect(wrapper.text()).toContain('gesamte Terminserie')
            expect(wrapper.findAll('v-text-field').some((field) => field.attributes('label') === 'Wiederholen bis einschließlich')).toBe(true)
        } finally { wrapper.unmount() }
    })

    it.each([false, true])('uses the actual selected school hours with gaps, weekly=%s', async (weekly) => {
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3', schoolHours: [
            { hour: 1, from: '07:45', until: '08:35' }, { hour: 2, from: '08:40', until: '09:30' }, { hour: 3, from: '10:00', until: '10:50' },
        ] }, global: { stubs: { 'v-checkbox': true } } })
        try {
            const vm = wrapper.vm as any
            vm.open()
            await wrapper.setData({ timeMode: 'school_hours', form: { ...single, id: undefined, school_hours: [1, 3], weekly, repeat_until: weekly ? '2026-11-02' : '' } })
            expect(vm.canSave).toBe(true)
            expect(vm.selectedSchoolHourSegments.map((segment) => segment.hour)).toEqual([1, 3])
            expect(wrapper.text()).toContain('1. Std · 07:45–08:35')
            expect(wrapper.text()).toContain('3. Std · 10:00–10:50')
            expect(wrapper.text()).not.toContain('07:45–10:50')
            vi.mocked(axios.post).mockResolvedValueOnce({ data: { data: single } })
            await vm.save()
            expect(axios.post).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ school_hours: [1, 3], starts_at: null, ends_at: null, weekly }))
        } finally { wrapper.unmount() }
    })

    it('restores a saved school hour selection and disables missing or overlapping hours', async () => {
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3', schoolHours: [{ hour: 1, from: '08:00', until: '08:50' }, { hour: 2, from: '08:30', until: '09:20' }] }, global: { stubs: { 'v-checkbox': true } } })
        try {
            const vm = wrapper.vm as any
            vm.open({ ...series, school_hours: [1, 2] })
            await wrapper.vm.$nextTick()
            expect(vm.timeMode).toBe('school_hours')
            expect(vm.canSave).toBe(false)
            await wrapper.setData({ form: { ...series, school_hours: [1], weekly: true } })
            expect(vm.canSave).toBe(true)
            await wrapper.setProps({ schoolHours: [] })
            expect(vm.canSave).toBe(false)
            expect(wrapper.text()).toContain('keine gültigen Schulstunden')
            await wrapper.setData({ timeMode: 'free' })
            expect(vm.canSave).toBe(true)
        } finally { wrapper.unmount() }
    })

    it.each([
        ['2026-09-14', '2027-02-14'], ['2027-02-14', '2027-02-14'],
        ['2027-02-15', '2027-07-09'], ['2027-07-09', '2027-07-09'],
        ['2026-09-13', null], ['2027-07-10', null], ['', null],
    ])('finds the configured semester end for start %s', (start, end) => {
        expect(personalAppointmentDateShortcuts({ from: '2026-09-14', until: '2027-07-09', sem_2_start: '2027-02-15' }, start).semesterEnd).toBe(end)
    })

    it('uses local calendar subtraction across daylight saving and does not invent missing dates', () => {
        expect(personalAppointmentDateShortcuts({ from: '2026-09-14', until: '2027-07-09', sem_2_start: '2027-03-29' }, '2027-03-20').semesterEnd).toBe('2027-03-28')
        expect(personalAppointmentDateShortcuts(null, single.date)).toEqual({ from: null, semesterStart: null, until: null, semesterEnd: null })
        expect(personalAppointmentDateShortcuts({ from: '2026-09-14', until: '2027-07-09' }, single.date).semesterEnd).toBeNull()
        expect(personalAppointmentDateShortcuts({ from: '2026-02-30', sem_2_start: '2027-02-15' }, single.date).from).toBeNull()
        expect(personalAppointmentDateShortcuts({ from: '2026-09-14', until: '2027-07-09', sem_2_start: '2027-08-01' }, single.date).semesterStart).toBeNull()
    })

    it('sets start and repetition dates through the quick buttons and retains the later-end validation', async () => {
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: [] } })
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3', schoolyear: { id: 3, from: '2026-09-14', until: '2027-07-09', sem_2_start: '2027-02-15' } }, global: { stubs: { 'v-checkbox': true } } })
        try {
            const vm = wrapper.vm as any
            vm.open(single)
            await wrapper.vm.$nextTick()
            const button = (label) => wrapper.findAll('v-btn').find((item) => item.text() === label)!
            expect(button('Schulende')).toBeUndefined()
            await button('Schulbeginn').trigger('click')
            expect(vm.form.date).toBe('2026-09-14')
            await wrapper.setData({ form: { ...vm.form, weekly: true } })
            await button('Semesterende').trigger('click')
            expect(vm.form.repeat_until).toBe('2027-02-14')
            await button('Beginn 2. Semester').trigger('click')
            expect(vm.form.date).toBe('2027-02-15')
            expect(vm.canSave).toBe(false)
            await button('Semesterende').trigger('click')
            expect(vm.form.repeat_until).toBe('2027-07-09')
            expect(vm.canSave).toBe(true)
            await wrapper.setData({ form: { ...vm.form, repeat_until: '' } })
            await button('Schulende').trigger('click')
            expect(vm.form.repeat_until).toBe('2027-07-09')
        } finally { wrapper.unmount() }
    })

    it('disables quick buttons when the selected schoolyear has no stored dates', async () => {
        const wrapper = mount(PersonalAppointments, { props: { contextKey: '7:2:3' }, global: { stubs: { 'v-checkbox': true } } })
        try {
            ;(wrapper.vm as any).open(series)
            await wrapper.vm.$nextTick()
            for (const label of ['Schulbeginn', 'Beginn 2. Semester', 'Schulende', 'Semesterende']) {
                const button = wrapper.findAll('v-btn').find((item) => item.text() === label)!
                expect(button.attributes('disabled')).toBeDefined()
                expect(button.attributes('title')).toBeTruthy()
            }
        } finally { wrapper.unmount() }
    })
})
