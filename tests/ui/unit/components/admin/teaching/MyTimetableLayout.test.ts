import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterAll, beforeAll, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { compileStyle, parse } from '@vue/compiler-sfc'
import { createVuetify } from 'vuetify'
import { VIcon } from 'vuetify/components/VIcon'
import { VCard, VCardText, VCardTitle } from 'vuetify/components/VCard'
import puppeteer, { type Browser } from 'puppeteer'
import MyTimetable from '@/pages/admin/teaching/overview/components/MyTimetable.vue'
import { useCourseStore } from '@/stores/admin/teaching/CourseStore'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { useSchoolHourStore } from '@/stores/admin/teaching/SchoolHourStore'

let browser: Browser

beforeAll(async () => {
    browser = await puppeteer.launch({
        headless: true,
        pipe: true,
        ...(existsSync(await puppeteer.executablePath()) ? {} : { channel: 'chrome' }),
    })
}, 30_000)

afterAll(async () => {
    await browser?.close()
})

it('fills the available width with equal weekdays and keeps long entries readable on small screens', async () => {
    setActivePinia(createPinia())
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 18, 12))
    useAdminStore().config = { user: { id: 7 } } as any
    useSchoolHourStore().school_hours = [{ hour: 1 }, { hour: 2 }] as any
    const courseStore = useCourseStore()
    courseStore.timetable_view_mode = 'table'
    courseStore.courses = [
        { id: 1, title: 'M', classes: ['1A'], date: '2026-09-15', assigned: false },
        { id: 2, title: 'INF - 5L2 - 1', classes: ['5L2'], date: '2026-09-16', assigned: true },
        { id: 3, title: 'INF - 5L2 - 1', classes: ['5L2'], date: '2026-09-17', assigned: true, hidden: 1 },
        { id: 4, title: 'Informatik mit ausführlicher Kursbezeichnung', classes: ['5L2'], date: '2026-09-18', assigned: true, hidden: 123 },
        { id: 5, title: 'INF', classes: ['5L2, 5L3, 5L4, 5L5, 5L6, 5L7, 5L8, 5L9'], date: '2026-09-18', assigned: false },
    ].map(({ date, assigned, hidden = 0, ...course }) => ({
        ...course,
        user_id: 7,
        course_dates: [{
            id: course.id,
            date,
            hours: [course.id === 5 ? 2 : 1],
            has_curriculum_assignment: assigned,
            shared_curriculum_attachments_count: assigned ? 1 : 0,
            private_curriculum_attachments_count: hidden,
        }],
    })) as any

    const wrapper = mount(MyTimetable, {
        global: {
            plugins: [createVuetify()],
            components: { VIcon, VCard, VCardText, VCardTitle },
            stubs: { ItsGridBox: { template: '<div><slot /></div>' }, VDivider: true, VListItemTitle: true,
                'v-icon': false, 'v-card': false, 'v-card-text': false, 'v-card-title': false },
        },
    })
    const page = await browser.newPage()

    try {
        await wrapper.setData({ range: 'week' })
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/MyTimetable.vue'), 'utf8')
        const { descriptor } = parse(source)
        const styles = descriptor.styles.map((style) => compileStyle({
            source: style.content,
            filename: descriptor.filename,
            id: (MyTimetable as any).__scopeId,
            scoped: style.scoped,
        }).code).join('\n')
        const vuetifyStyles = [
            'node_modules/vuetify/lib/styles/main.css',
            'node_modules/vuetify/lib/components/VIcon/VIcon.css',
            'node_modules/vuetify/lib/components/VCard/VCard.css',
        ].map((path) => readFileSync(resolve(path), 'utf8')).join('\n')

        const themeStyles = document.getElementById('vuetify-theme-stylesheet')?.textContent || ''
        await page.setContent(`<style>${vuetifyStyles}\n${themeStyles}\n${styles}</style><div class="v-theme--light">${wrapper.get('.timetable-table-card').html()}</div>`)

        for (const width of [400, 900, 1440]) {
            await page.setViewport({ width, height: 900 })
            const layout = await page.evaluate(() => {
                const headers = [...document.querySelectorAll('.timetable-day-header-cell')]
                const days = headers.map((header, index) => {
                    const cells = [...document.querySelectorAll(`tbody tr td:nth-child(${index + 2})`)]
                    const cards = cells.flatMap((cell) => [...cell.querySelectorAll('.timetable-grid-item')])
                    return {
                        width: header.getBoundingClientRect().width,
                        overflowing: cards.flatMap((card) => [...card.querySelectorAll('.timetable-grid-course, .timetable-grid-class, [role="img"]')]
                            .filter((element) => element.scrollWidth > element.clientWidth + 1
                                || element.getBoundingClientRect().right > card.getBoundingClientRect().right + 1)
                            .map((element) => element.textContent)),
                        clipped: cards.some((card) => [...card.querySelectorAll('.timetable-grid-course, .timetable-grid-class, [role="img"]')]
                            .some((element) => element.scrollWidth > element.clientWidth + 1
                                || element.getBoundingClientRect().right > card.getBoundingClientRect().right + 1)),
                    }
                })
                const mixed = document.querySelector('[title="1 veröffentlicht, 1 verborgen"]')!
                const table = document.querySelector('.timetable-grid-table')!
                const container = document.querySelector('.timetable-table-wrapper')!
                const card = document.querySelector('.timetable-table-card')!

                return {
                    days,
                    mixedCounts: mixed.textContent?.replace(/\s/g, ''),
                    mixedIconWidths: [...mixed.querySelectorAll('.v-icon')].map((icon) => icon.getBoundingClientRect().width),
                    tableWidth: table.getBoundingClientRect().width,
                    containerWidth: container.clientWidth,
                    scrollWidth: container.scrollWidth,
                    hourWidth: document.querySelector('.timetable-hour-header-cell')!.getBoundingClientRect().width,
                    cardWidth: card.getBoundingClientRect().width,
                    dayCount: headers.length,
                }
            })

            expect(layout.mixedCounts).toBe('11')
            expect(layout.mixedIconWidths).toEqual([14, 14])
            expect(layout.hourWidth).toBe(44)
            expect(layout.dayCount).toBe(5)
            expect(layout.cardWidth).toBe(width)
            for (const [index, day] of layout.days.entries()) {
                expect(day.clipped, `viewport ${width}, day ${index}: ${JSON.stringify(day)}`).toBe(false)
                expect(Math.abs(day.width - layout.days[0].width)).toBeLessThanOrEqual(1)
            }
            expect(layout.scrollWidth).toBeGreaterThanOrEqual(Math.floor(layout.tableWidth))
            if (width === 400) {
                expect(layout.scrollWidth).toBeGreaterThan(layout.containerWidth)
            } else {
                expect(Math.abs(layout.tableWidth - layout.containerWidth)).toBeLessThanOrEqual(1)
            }
        }

        await page.setViewport({ width: 900, height: 900 })
        const loadedCourses = courseStore.courses
        const schoolHourStore = useSchoolHourStore()
        const loadedHours = schoolHourStore.school_hours
        courseStore.courses = []
        schoolHourStore.school_hours = []
        await wrapper.setData({ range: 'next_week' })
        for (const loaded of [false, true]) {
            if (loaded) {
                courseStore.courses = loadedCourses
                schoolHourStore.school_hours = loadedHours
                await wrapper.setData({ range: 'week' })
            }
            await page.setContent(`<style>${vuetifyStyles}\n${themeStyles}\n${styles}</style><div class="v-theme--light">${wrapper.get('.timetable-table-card').html()}</div>`)
            const layout = await page.evaluate(() => {
                const table = document.querySelector('.timetable-grid-table')!
                const headers = [...table.querySelectorAll('thead th')]
                return {
                    dayWidths: headers.slice(1).map((header) => header.getBoundingClientRect().width),
                    unusedWidth: table.getBoundingClientRect().right - headers.at(-1)!.getBoundingClientRect().right,
                }
            })
            expect(layout.dayWidths).toHaveLength(5)
            expect(layout.dayWidths.every((width) => width >= 130)).toBe(true)
            expect(layout.unusedWidth, `loaded: ${loaded}`).toBeLessThanOrEqual(1)
        }

        ;(courseStore.courses[0] as any).course_dates.push({
            id: 101, date: '2026-09-21', hours: [1],
        })
        for (const { now, range, firstDay } of [
            { now: '2026-09-18T21:59:59Z', range: 'week', firstDay: '14.09.' },
            { now: '2026-09-18T22:00:00Z', range: 'next_week', firstDay: '21.09.' },
            { now: '2026-09-20T21:59:59Z', range: 'next_week', firstDay: '21.09.' },
            { now: '2026-09-20T22:00:00Z', range: 'week', firstDay: '21.09.' },
        ]) {
            vi.setSystemTime(new Date(now))
            await wrapper.setData({ nowTs: Date.now(), range: 'today' })
            await wrapper.setData({ range })
            await page.setContent(`<style>${vuetifyStyles}\n${themeStyles}\n${styles}</style><div class="v-theme--light">${wrapper.get('.timetable-table-card').html()}</div>`)
            const layout = await page.evaluate(() => {
                const table = document.querySelector('.timetable-grid-table')!
                const headers = [...table.querySelectorAll('.timetable-day-header-cell')]
                return {
                    firstDay: headers[0].querySelector('.timetable-day-date')!.textContent,
                    widths: headers.map((header) => header.getBoundingClientRect().width),
                    unusedWidth: table.getBoundingClientRect().right - headers.at(-1)!.getBoundingClientRect().right,
                }
            })
            expect(layout.firstDay).toBe(firstDay)
            expect(layout.widths).toHaveLength(5)
            expect(layout.widths.every((width) => width >= 130)).toBe(true)
            expect(layout.unusedWidth).toBeLessThanOrEqual(1)
        }

        if (process.env.TIMETABLE_LAYOUT_HTML) {
            writeFileSync(process.env.TIMETABLE_LAYOUT_HTML, await page.content())
        }

        if (process.env.TIMETABLE_LAYOUT_SCREENSHOT) {
            await page.setViewport({ width: 1440, height: 200 })
            await page.addStyleTag({ url: 'https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css' })
            await page.addStyleTag({ url: 'https://fonts.bunny.net/css?family=roboto:100,300,400,500,700,900' })
            await page.evaluate(() => document.fonts.ready)
            await page.screenshot({ path: process.env.TIMETABLE_LAYOUT_SCREENSHOT, fullPage: true })
        }
    } finally {
        wrapper.unmount()
        vi.useRealTimers()
        await page.close()
    }
}, 30_000)
