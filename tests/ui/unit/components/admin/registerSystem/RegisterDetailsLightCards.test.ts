import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

describe('Register details light cards', () => {
    it('keeps the page shell dark while using light detail surfaces', () => {
        const pageSource = readFileSync('resources/js/pages/admin/registerSystem/RegisterDetails.vue', 'utf8')
        const overviewSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/Overview.vue', 'utf8')
        const menuSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/MainMenu.vue', 'utf8')
        const datesSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/DatesWithMenu.vue', 'utf8')
        const addDatesSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/AddDates.vue', 'utf8')
        const addPersonSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/AddPerson.vue', 'utf8')
        const bookingsSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/ShowBookings.vue', 'utf8')
        const usersSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterDetails/RegisterUsers.vue', 'utf8')

        expect(pageSource).toContain('background: #0f172a;')

        expect(menuSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.68));')

        expect(overviewSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(overviewSource).toContain('color: #10263a !important;')
        expect(overviewSource).toContain('background: rgba(255, 255, 255, 0.82);')

        expect(datesSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(datesSource).toContain('background: rgba(255, 255, 255, 0.82) !important;')
        expect(datesSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96)) !important;')

        expect(addDatesSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(addDatesSource).toContain('background: rgba(255, 255, 255, 0.82);')

        expect(addPersonSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(addPersonSource).toContain('background: rgba(255, 255, 255, 0.82);')

        expect(bookingsSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(bookingsSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96)) !important;')

        expect(usersSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(usersSource).toContain('color: #10263a !important;')
        expect(usersSource).toContain('background: rgba(255, 255, 255, 0.82);')
    })
})
