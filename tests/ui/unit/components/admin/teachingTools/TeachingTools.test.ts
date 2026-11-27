import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { DOMWrapper, flushPromises, mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import { createMemoryHistory, createRouter, RouterLink, RouterView } from 'vue-router'
import { routes } from '../../../../../../resources/routes/admin.js'

vi.mock('@/pages/admin/teachingTools/MultipleChoiceWorkspace.vue', () => ({
    default: { template: '<div data-test="mc-workspace">Vorhandener MC-Workflow</div>' },
}))
vi.mock('@/pages/admin/teachingTools/NumberSystemsGame.vue', () => ({
    default: { template: '<div data-test="number-systems">Signal Null · Neues Abenteuer</div>' },
}))

const wrappers = []
const sharingState = { available: true, published: false, access_mode: 'authenticated', invitation: null, courses: [{ id: 21, title: '2A' }], groups: [] }
beforeEach(() => {
    vi.stubGlobal('axios', { get: vi.fn().mockResolvedValue({ data: sharingState }), post: vi.fn(), delete: vi.fn() })
})
const navigationButton = defineComponent({
    props: ['to'],
    setup(props, { slots, attrs }) {
        return () => props.to
            ? h(RouterLink, { ...attrs, to: props.to }, slots.default)
            : h('button', { ...attrs, type: attrs.type ?? 'button' }, slots.default?.())
    },
})
const navigationCard = defineComponent({
    props: ['to'],
    setup(props, { slots }) {
        return () => props.to ? h(RouterLink, { to: props.to }, slots.default) : h('div', slots.default?.())
    },
})

afterEach(() => {
    wrappers.splice(0).forEach(wrapper => wrapper.unmount())
    window.localStorage.clear()
    vi.unstubAllGlobals()
})

async function renderTools(path, deliveryMode = 'mailpit', userId?: number) {
    const router = createRouter({ history: createMemoryHistory(), routes: routes.filter(route => route.path.startsWith('/admin/teaching-tools')) })
    await router.push(path)
    await router.isReady()
    const wrapper = mount(RouterView, {
        attachTo: document.body,
        global: { plugins: [router, createTestingPinia({ createSpy: vi.fn, initialState: {
            AdminAdminStore: { config: { teaching_email_delivery: { mode: deliveryMode }, user: { id: userId, school_id: 21 } } },
        } })], stubs: { AdminPageHeader: true, 'v-btn': navigationButton, 'v-card': navigationCard,
            'v-overlay': { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
            'v-sheet': { template: '<div><slot /></div>' }, 'v-container': { template: '<div><slot /></div>' },
            'v-dialog': { props: ['modelValue'], emits: ['update:modelValue'], template: `
                <slot name="activator" :props="{ onClick: () => $emit('update:modelValue', true) }" />
                <div v-if="modelValue" v-bind="$attrs" role="dialog"><slot /></div>
            ` } } },
    })
    wrappers.push(wrapper)
    await flushPromises()
    return { wrapper, router }
}

async function measureRoomPlan(wrapper) {
    const layer = wrapper.get('[aria-label="Geräte im Grundriss"]').element
    Object.defineProperty(layer, 'offsetWidth', { value: 800 })
    layer.getBoundingClientRect = () => new DOMRect(0, 0, 800, 450)
    const rooms = wrapper.findAll('.network-floor__room')
    const layout = [
        [[0, 0, 399, 148], [369, 59, 30, 30]],
        [[0, 150, 399, 149], [369, 209, 30, 30]],
        [[0, 301, 399, 149], [369, 361, 30, 30]],
        [[401, 0, 399, 450], [585, 420, 30, 30]],
    ] as const
    rooms.forEach((room, index) => {
        const [bounds, door] = layout[index]
        room.element.getBoundingClientRect = () => new DOMRect(...bounds)
        room.get('.network-floor__door').element.getBoundingClientRect = () => new DOMRect(...door)
    })
    window.dispatchEvent(new Event('resize'))
    await flushPromises()
}

describe('Teaching tools navigation', () => {
    it('restores the saved room plan and zoom for the same user after a new mount', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        const palette = wrapper.get('[aria-label="Geräteauswahl"]')
        await palette.findAll('button[aria-pressed]').find(button => button.text() === 'Router')!.trigger('click')
        await wrapper.get('[aria-label="Geräte im Grundriss"]').trigger('keydown.enter')
        await wrapper.get('[aria-label="Grundriss vergrößern"]').trigger('click')
        const marker = '[aria-label="Router im Grundriss"]'
        const position = wrapper.get(marker).attributes('style')

        const { wrapper: restored } = await renderTools(path, 'mailpit', 1)
        expect(restored.get(marker).attributes('style')).toBe(position)
        expect(restored.get('[aria-label="Zoomstufe"]').text()).toBe('105 %')

        const { wrapper: otherUser } = await renderTools(path, 'mailpit', 2)
        expect(otherUser.find(marker).exists()).toBe(false)
        expect(otherUser.get('[aria-label="Zoomstufe"]').text()).toBe('100 %')
    })

    it('previews devices before placing them and snaps cable ducts and outlets into position', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const { wrapper } = await renderTools(path)
        await measureRoomPlan(wrapper)
        const layer = wrapper.get('[aria-label="Geräte im Grundriss"]')
        const palette = wrapper.get('[aria-label="Geräteauswahl"]')
        const deviceButton = label => palette.findAll('button').find(button => button.text() === label)!
        const preview = '.network-floor__placement-preview'
        const move = (clientX, clientY) => layer.trigger('pointermove', { pointerType: 'mouse', clientX, clientY })
        const hitTest = vi.spyOn(document, 'elementFromPoint').mockReturnValue(layer.element)
        try {
            await deviceButton('Kabelkanal').trigger('click')
            await move(140, 94)
            expect(layer.find(preview).exists()).toBe(false)
            await move(140, 13)
            expect(layer.get(preview).attributes('data-wall')).toBe('north-office-1')
            expect(layer.find('[data-placement-id]').exists()).toBe(false)
            await layer.trigger('click', { clientX: 140, clientY: 13 })
            expect(layer.find(preview).exists()).toBe(false)
            const channel = layer.get('[data-placement-id][data-wall="north-office-1"]')

            await deviceButton('Einfache Netzwerkdose').trigger('click')
            await move(140, 13)
            expect(layer.get(preview).attributes('data-channel-id')).toBe(channel.attributes('data-placement-id'))
            await layer.trigger('pointerleave')
            expect(layer.find(preview).exists()).toBe(false)

            await deviceButton('Switch').trigger('click')
            await move(600, 180)
            expect(layer.get(preview).text()).toBe('Switch')
            expect(layer.findAll('[data-placement-id]')).toHaveLength(1)
            await layer.trigger('click', { clientX: 600, clientY: 180 })
            expect(layer.find(preview).exists()).toBe(false)
            expect(layer.findAll('[data-placement-id]')).toHaveLength(2)

            const routerButton = deviceButton('Router')
            routerButton.element.setPointerCapture = vi.fn()
            const pointer = { pointerId: 1, isPrimary: true, pointerType: 'mouse', button: 0 }
            await routerButton.trigger('pointerdown', { ...pointer, clientX: 50, clientY: 510 })
            await routerButton.trigger('pointermove', { ...pointer, clientX: 600, clientY: 240 })
            expect(layer.get(preview).text()).toBe('Router')
            await routerButton.trigger('pointerup', { ...pointer, clientX: 600, clientY: 240 })
            expect(layer.find(preview).exists()).toBe(false)
            expect(layer.findAll('[data-placement-id]')).toHaveLength(3)
        } finally {
            hitTest.mockRestore()
        }
    })

    it('closes photo previews and hides device hover hints while a device is selected', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            placements: [{ id: 1, deviceId: 'router', x: .25, y: .8 }],
            connections: [],
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        const palette = wrapper.get('[aria-label="Geräteauswahl"]')
        const deviceButton = label => palette.findAll('button').find(button => button.text() === label)!
        const routerItem = new DOMWrapper(deviceButton('Router').element.closest('.network-device-photo__item')!)
        const routerPhoto = '[aria-label="Foto: Router"]'
        const router = wrapper.get('[data-placement-id="1"]')
        const hover = () => routerItem.trigger('pointerenter', { pointerType: 'mouse', buttons: 0 })
        await hover()
        expect(routerItem.find(routerPhoto).exists()).toBe(true)
        expect(router.attributes('title')).toBe('Router')

        await deviceButton('Switch').trigger('click')
        expect(routerItem.find(routerPhoto).exists()).toBe(false)
        expect(router.attributes('title')).toBeUndefined()
        await hover()
        expect(routerItem.find(routerPhoto).exists()).toBe(false)

        await deviceButton('Switch').trigger('click')
        await hover()
        expect(routerItem.find(routerPhoto).exists()).toBe(true)
        expect(router.attributes('title')).toBe('Router')
    })

    it('clears the saved room plan only after confirming the reset', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const draftKey = 'schooltool:network-room-plan:v1:1:21:1'
        const savedDraft = JSON.stringify({
            floorLayoutVersion: 2,
            zoom: 120,
            placements: [
                { id: 1, deviceId: 'workstation', x: .2, y: .2 },
                { id: 2, deviceId: 'cable-duct', wallId: 'south-server', x: .2, y: 1 },
                { id: 3, deviceId: 'network-outlet', channelId: 2, channelPosition: .5 },
                { id: 4, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['patch-panel', 'switch'] },
            ],
            connections: [{ id: 1, start: { placementId: 1, index: 0 }, end: { placementId: 3, index: 0 } }],
        })
        window.localStorage.setItem(draftKey, savedDraft)
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        const resetButton = wrapper.get('header').findAll('button').find(button => button.text() === 'Raumplan zurücksetzen')!
        const dialogSelector = '[role="dialog"][aria-labelledby="ground-floor-reset-title"]'
        const planDevices = '[aria-label="Geräte im Grundriss"] [data-placement-id]'

        await resetButton.trigger('click')
        expect(window.localStorage.getItem(draftKey)).toBe(savedDraft)
        await wrapper.get(dialogSelector).findAll('button').find(button => button.text() === 'Abbrechen')!.trigger('click')
        expect(wrapper.find(dialogSelector).exists()).toBe(false)
        expect(wrapper.findAll(planDevices)).toHaveLength(4)
        expect(wrapper.get('[aria-label="Zoomstufe"]').text()).toBe('120 %')

        await resetButton.trigger('click')
        await wrapper.get(dialogSelector).findAll('button').find(button => button.text() === 'Raumplan zurücksetzen')!.trigger('click')
        expect(wrapper.find(dialogSelector).exists()).toBe(false)
        expect(wrapper.findAll(planDevices)).toHaveLength(0)
        expect(wrapper.get('[aria-label="Zoomstufe"]').text()).toBe('100 %')
        expect(JSON.parse(window.localStorage.getItem(draftKey)!)).toMatchObject({ placements: [], connections: [], zoom: 100 })

        const { wrapper: restored } = await renderTools(path, 'mailpit', 1)
        expect(restored.findAll(planDevices)).toHaveLength(0)
        expect(restored.get('[aria-label="Zoomstufe"]').text()).toBe('100 %')
    })

    it('marks completed work cards and removes the checks when the plan no longer meets a step', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const patchPort = (slot, port) => ({ slot, port })
        const clientIds = [1, 2, 3, 4, 5, 14]
        const outlets = [[30, 0], [30, 1], [31, 0], [32, 0], [32, 1], [33, 0]]
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            floorLayoutVersion: 2,
            placements: [
                ...[[.1, .18], [.24, .18], [.38, .18], [.1, .52], [.26, .52]].map(([x, y], index) =>
                    ({ id: index + 1, deviceId: 'workstation', x, y })),
                ...[[.65, .2], [.82, .2], [.65, .5], [.82, .5]].map(([x, y], index) =>
                    ({ id: index + 10, deviceId: 'standing-table', x, y })),
                { id: 14, deviceId: 'access-point', x: .7, y: .82 },
                ...['west-office-1', 'west-office-2', 'west-server', 'south-server', 'partition-server-bottom',
                    'partition-server-bottom-foyer'].map((wallId, index) =>
                    ({ id: index + 20, deviceId: 'cable-duct', wallId, x: .5, y: .5 })),
                { id: 30, deviceId: 'double-network-outlet', channelId: 20, channelPosition: .25 },
                { id: 31, deviceId: 'network-outlet', channelId: 20, channelPosition: .75 },
                { id: 32, deviceId: 'double-network-outlet', channelId: 21, channelPosition: .5 },
                { id: 33, deviceId: 'network-outlet', channelId: 25, channelPosition: .5 },
                { id: 40, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['patch-panel', 'switch', 'router', 'server'],
                    rackPatchConnections: [
                        ...clientIds.map((_, port) => ({ start: patchPort(0, port), end: patchPort(1, port) })),
                        { start: patchPort(1, 6), end: patchPort(2, 0) },
                    ] },
                { id: 41, deviceId: 'bundle-outlet', channelId: 23, channelPosition: .5, rackId: 40 },
            ],
            connections: clientIds.map((placementId, port) => ({
                id: port + 1, rackPort: port, start: { placementId, index: 0 },
                end: { placementId: outlets[port][0], index: outlets[port][1] },
            })),
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        await measureRoomPlan(wrapper)
        const card = wrapper.get('#ground-floor-task-card')
        const steps = wrapper.get('[aria-label="Arbeitsschritte"]').findAll('button')
        const completionText = 'Arbeitsschritt erfüllt'
        expect(steps[1].attributes('color')).toBe('success')
        expect(steps[1].attributes('aria-pressed')).toBe('false')
        expect(steps[1].attributes('aria-label')).toContain('erfüllt')
        expect(steps[6].attributes('color')).toBe('primary')
        for (const step of [1, 2, 3, 4, 5, 6]) {
            await steps[step - 1].trigger('click')
            expect(card.text(), `Schritt ${step}`).toContain(completionText)
        }
        await steps[6].trigger('click')
        expect(card.text()).not.toContain(completionText)

        const actions = new DOMWrapper(document.body)
        await wrapper.get('[data-placement-id="40"]').trigger('click')
        await actions.get('#network-floor-marker-actions button').trigger('click')
        const dialog = wrapper.get('[role="dialog"]')
        await dialog.findAll('button').find(button => button.text() === 'Patchkabel ein-/ausstecken')!.trigger('click')
        await dialog.get('[aria-label="Platz 2, Switch, Buchse 8"]').trigger('click')
        await dialog.get('[aria-label="Platz 4, Datenserver, Buchse 1"]').trigger('click')
        expect(card.text()).not.toContain(completionText)
        await dialog.findAll('button').find(button => button.text() === 'Speichern')!.trigger('click')
        expect(card.text()).toContain(completionText)
        expect(steps[6].attributes('color')).toBe('success')

        await wrapper.get('[data-placement-id="40"]').trigger('click')
        await actions.get('#network-floor-marker-actions button').trigger('click')
        const reopenedDialog = wrapper.get('[role="dialog"]')
        await reopenedDialog.get('[aria-label="Patchkabel zwischen Platz 2, Switch, Buchse 8 und Platz 4, Datenserver, Buchse 1 entfernen"]')
            .trigger('click')
        await reopenedDialog.findAll('button').find(button => button.text() === 'Speichern')!.trigger('click')
        expect(wrapper.get('[aria-label="Netzwerkstatus"]').text()).toMatch(
            /^LAN\s*aktivWLAN\s*aktivInternet\s*aktivDatenserver\s*nicht erreichbar$/,
        )
        expect(card.text()).not.toContain(completionText)
        expect(steps[6].attributes('color')).toBe('primary')

        await wrapper.get('[data-placement-id="33"]').trigger('click')
        await actions.get('#network-floor-marker-actions [aria-label="Einfache Netzwerkdose entfernen"]').trigger('click')
        expect(steps[2].attributes('color')).toBe('success')
        expect(steps[3].attributes('color')).toBe('primary')

        await wrapper.get('[data-placement-id="1"]').trigger('click')
        await actions.get('#network-floor-marker-actions [aria-label="Arbeitsplatz entfernen"]').trigger('click')
        expect(card.text()).not.toContain(completionText)
        expect(steps[6].attributes('color')).toBe('primary')
        await steps[0].trigger('click')
        expect(card.text()).not.toContain(completionText)
        await steps[6].trigger('click')
        expect(card.text()).not.toContain(completionText)
    })

    it('confirms rack component removal before disconnecting its patch cables', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            placements: [{ id: 1, deviceId: 'server-rack', x: .25, y: .8 }],
            connections: [],
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        await wrapper.get('[data-placement-id="1"]').trigger('click')
        await new DOMWrapper(document.body).get('#network-floor-marker-actions button').trigger('click')
        const dialog = wrapper.get('[role="dialog"]')
        const button = label => dialog.findAll('button').find(candidate => candidate.text() === label)
        const patchButton = () => button('Patchkabel ein-/ausstecken')

        expect(patchButton()).toBeUndefined()
        await button('Switch')!.trigger('click')
        await dialog.get('[aria-label="Platz 3, frei"]').trigger('click')
        expect(patchButton()).toBeUndefined()
        await button('Router')!.trigger('click')
        await dialog.get('[aria-label="Platz 5, frei"]').trigger('click')
        await patchButton()!.trigger('click')
        expect(dialog.get('[aria-label="Buchsen auf Platz 3"]').exists()).toBe(true)

        const switchPort = '[aria-label="Platz 3, Switch, Buchse 1"]'
        const routerPort = '[aria-label="Platz 5, Router, Buchse 1"]'
        const cable = '[aria-label="Patchkabel zwischen Platz 3, Switch, Buchse 1 und Platz 5, Router, Buchse 1 entfernen"]'
        await dialog.get(switchPort).trigger('click')
        await dialog.get(routerPort).trigger('click')
        const removeRouter = '[aria-label="Router aus Platz 5 entfernen"]'
        const confirmation = () => wrapper.get('[role="dialog"][aria-labelledby="network-rack-remove-title"]')

        await dialog.get(removeRouter).trigger('click')
        expect(confirmation().text()).toContain('Router aus Platz 5 entfernen?')
        expect(dialog.find(cable).exists()).toBe(true)
        await confirmation().findAll('button').find(candidate => candidate.text() === 'Abbrechen')!.trigger('click')
        expect(dialog.find(removeRouter).exists()).toBe(true)
        expect(dialog.find(cable).exists()).toBe(true)

        await dialog.get(removeRouter).trigger('click')
        await confirmation().findAll('button').find(candidate => candidate.text() === 'Entfernen')!.trigger('click')
        expect(dialog.find(cable).exists()).toBe(false)
        expect(patchButton()).toBeUndefined()
        expect(dialog.find('[aria-label="Buchsen auf Platz 3"]').exists()).toBe(false)
        await button('Router')!.trigger('click')
        await dialog.get('[aria-label="Platz 5, frei"]').trigger('click')
        expect(patchButton()!.attributes('aria-pressed')).toBe('false')
        await patchButton()!.trigger('click')
        expect(dialog.find(routerPort).exists()).toBe(true)
        expect(dialog.find(cable).exists()).toBe(false)
    })

    it('updates the internet cloud immediately when the switch-to-router cable is added or removed', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            placements: [
                { id: 1, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['switch'] },
                { id: 2, deviceId: 'router', x: .4, y: .8 },
            ],
            connections: [],
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        const internet = wrapper.get('[role="status"][aria-label="Netzwerkstatus"]')
        async function openRack() {
            await wrapper.get('[data-placement-id="1"]').trigger('click')
            await new DOMWrapper(document.body).get('#network-floor-marker-actions button').trigger('click')
            return wrapper.get('[role="dialog"]')
        }
        const clickButton = (scope, label) => scope.findAll('button').find(button => button.text() === label)!.trigger('click')

        expect(internet.text()).toMatch(/Internet\s*inaktiv/)
        const dialog = await openRack()
        await clickButton(dialog, 'Router')
        await dialog.get('button[aria-label="Platz 2, frei"]').trigger('click')
        expect(internet.text()).toMatch(/Internet\s*inaktiv/)
        await clickButton(dialog, 'Patchkabel ein-/ausstecken')
        await dialog.get('[aria-label="Platz 1, Switch, Buchse 1"]').trigger('click')
        await dialog.get('[aria-label="Platz 2, Router, Buchse 1"]').trigger('click')
        expect(internet.text()).toMatch(/Internet\s*aktiv/)
        const { wrapper: restored } = await renderTools(path, 'mailpit', 1)
        expect(restored.get('[role="status"][aria-label="Netzwerkstatus"]').text()).toMatch(/Internet\s*aktiv/)
        await clickButton(dialog, 'Speichern')

        const uplink = '[aria-label="Patchkabel zwischen Platz 1, Switch, Buchse 1 und Platz 2, Router, Buchse 1 entfernen"]'
        const edited = await openRack()
        await edited.get(uplink).trigger('click')
        expect(internet.text()).toMatch(/Internet\s*inaktiv/)
        await clickButton(edited, 'Abbrechen')
        expect(internet.text()).toMatch(/Internet\s*aktiv/)
        const { wrapper: restoredAfterCancel } = await renderTools(path, 'mailpit', 1)
        expect(restoredAfterCancel.get('[role="status"][aria-label="Netzwerkstatus"]').text()).toMatch(/Internet\s*aktiv/)

        const reopened = await openRack()
        await reopened.get(uplink).trigger('click')
        await clickButton(reopened, 'Speichern')
        expect(internet.text()).toMatch(/Internet\s*inaktiv/)
    })

    it('reports LAN, WLAN, internet and data-server reachability along the wired paths', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const endpoint = (slot, port) => ({ slot, port })
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            floorLayoutVersion: 2,
            placements: [
                { id: 1, deviceId: 'cable-duct', wallId: 'north-office-1', x: .25, y: 0 },
                { id: 2, deviceId: 'double-network-outlet', channelId: 1, channelPosition: .3 },
                { id: 3, deviceId: 'double-network-outlet', channelId: 1, channelPosition: .7 },
                { id: 4, deviceId: 'workstation', x: .1, y: .2 },
                { id: 5, deviceId: 'standing-table', x: .2, y: .2 },
                { id: 6, deviceId: 'workstation', x: .3, y: .2 },
                { id: 7, deviceId: 'access-point', x: .4, y: .2 },
                { id: 8, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['patch-panel', 'switch', 'router', 'switch', 'server'],
                    rackPatchConnections: [
                        { start: endpoint(0, 0), end: endpoint(3, 0) },
                        { start: endpoint(0, 1), end: endpoint(1, 0) },
                        { start: endpoint(0, 2), end: endpoint(1, 1) },
                        { start: endpoint(0, 3), end: endpoint(1, 2) },
                    ] },
                { id: 9, deviceId: 'cable-duct', wallId: 'south-server', x: .25, y: 1 },
                { id: 10, deviceId: 'bundle-outlet', channelId: 9, channelPosition: .5, rackId: 8 },
                { id: 11, deviceId: 'standing-table', x: .8, y: .2 },
                ...[
                    'east-foyer',
                    'partition-office-1-top-foyer',
                    'partition-office-1-bottom-foyer',
                    'partition-office-2-top-foyer',
                    'partition-office-2-bottom-foyer',
                    'partition-server-top-foyer',
                    'partition-server-bottom-foyer',
                    'partition-server-bottom',
                ].map((wallId, index) => ({ id: 12 + index, deviceId: 'cable-duct', wallId, x: .5, y: .5 })),
            ],
            connections: [
                { id: 1, start: { placementId: 4, index: 0 }, end: { placementId: 2, index: 0 } },
                { id: 2, start: { placementId: 2, index: 1 }, end: { placementId: 5, index: 0 } },
                { id: 3, start: { placementId: 3, index: 0 }, end: { placementId: 6, index: 0 } },
                { id: 4, start: { placementId: 7, index: 0 }, end: { placementId: 3, index: 1 } },
            ],
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        await measureRoomPlan(wrapper)
        const mark = id => wrapper.get(`[data-placement-id="${id}"]`)
        const actions = new DOMWrapper(document.body)
        const clickButton = (scope, label) => scope.findAll('button').find(button => button.text() === label)!.trigger('click')
        const onlineIds = () => wrapper.findAll('.network-floor__client-connections')
            .map(indicator => indicator.element.parentElement.dataset.placementId)
        const badgeCounts = () => [4, 5, 6, 7, 11].map(id => mark(id).findAll('.network-floor__connection-badge').length)
        const connectionStatus = () => wrapper.get('.network-floor__connection-status').text()
        const activeChannels = () => wrapper.findAll('[data-wall]').filter(marker =>
            marker.attributes('aria-label')?.includes('Verbindung aktiv')).map(marker => marker.attributes('data-placement-id'))
        const workingComponents = dialog => dialog.findAll('button[aria-description="Funktionsfähig"]')
            .map(button => button.attributes('aria-label'))
        const litRackSlots = () => mark(8).findAll('.network-rack-icon > [data-module].is-lit')
            .map(slot => slot.attributes('data-module'))
        const assignedDevices = (dialog) => dialog.get('[aria-labelledby="network-rack-panel-devices"]').findAll('dl > div')
            .map(entry => [entry.get('dt').text(), entry.get('dd').text()])
        async function openRack() {
            await mark(8).trigger('click')
            await clickButton(actions.get('#network-floor-marker-actions'), 'Serverschrank konfigurieren')
            return wrapper.get('[role="dialog"]')
        }

        expect(onlineIds()).toEqual([])
        expect(activeChannels()).toEqual([])
        expect(connectionStatus()).toMatch(/^LAN\s*inaktivWLAN\s*inaktivInternet\s*inaktivDatenserver\s*nicht erreichbar$/)
        expect(mark(7).attributes('aria-label')).not.toContain('mit Internet verbunden')
        expect(mark(4).text()).toBe('Arbeitsplatz 1')
        expect(mark(6).text()).toBe('Arbeitsplatz 2')
        expect(mark(5).text()).toBe('Stehtisch 1')
        const dialog = await openRack()
        expect(dialog.text()).toContain('Eingehende Kabel: 0')
        expect(workingComponents(dialog)).toEqual([])
        expect(dialog.findAll('.rack-module-graphic__status.is-lit')).toHaveLength(0)
        expect(litRackSlots()).toEqual([])
        await clickButton(dialog, 'Patchkabel ein-/ausstecken')
        await dialog.get('[aria-label="Platz 2, Switch, Buchse 4"]').trigger('click')
        await dialog.get('[aria-label="Platz 3, Router, Buchse 1"]').trigger('click')
        expect(connectionStatus()).toMatch(/^LAN\s*inaktivWLAN\s*inaktivInternet\s*aktivDatenserver\s*nicht erreichbar$/)
        expect(workingComponents(dialog)).toEqual(['Platz 2, Switch', 'Platz 3, Router'])
        expect(dialog.findAll('.rack-module-graphic__status.is-lit')).toHaveLength(2)
        expect(litRackSlots()).toEqual(['switch', 'router'])
        expect(activeChannels()).toEqual([])
        await clickButton(dialog, 'Speichern')

        async function placeMissingDuct() {
            await clickButton(wrapper.get('[aria-label="Geräteauswahl"]'), 'Kabelkanal')
            const layer = wrapper.get('[aria-label="Geräte im Grundriss"]')
            const hit = vi.spyOn(document, 'elementFromPoint').mockReturnValue(layer.element)
            try {
                await layer.trigger('click', { clientX: 390, clientY: 28 })
            } finally {
                hit.mockRestore()
            }
            expect(wrapper.find('[data-wall="partition-office-1-top"]').exists()).toBe(true)
        }
        await placeMissingDuct()
        const connectedChannels = ['1', '9', '13', '14', '15', '16', '17', '18', '19', '20']
        expect(activeChannels()).toEqual(connectedChannels)
        expect(onlineIds()).toEqual(['4', '5', '6', '7', '11'])
        expect(badgeCounts()).toEqual([1, 2, 2, 1, 2])
        expect(mark(4).attributes('aria-label')).toContain('per LAN verbunden')
        expect(mark(5).attributes('aria-label')).toContain('mit Internet verbunden')
        expect(mark(7).attributes('aria-label')).toContain('mit Internet verbunden')
        expect(mark(11).attributes('aria-label')).toContain('per WLAN mit Internet verbunden')
        expect(connectionStatus()).toMatch(/^LAN\s*aktivWLAN\s*aktivInternet\s*aktivDatenserver\s*nicht erreichbar$/)

        const connectedDialog = await openRack()
        expect(workingComponents(connectedDialog)).toEqual([
            'Platz 1, Patchpanel, 4 Anschlüsse belegt', 'Platz 2, Switch', 'Platz 3, Router', 'Platz 4, Switch',
        ])
        expect(assignedDevices(connectedDialog)).toEqual([
            ['Buchse 1', 'Arbeitsplatz 1'],
            ['Buchse 2', 'Stehtisch 1'],
            ['Buchse 3', 'Arbeitsplatz 2'],
            ['Buchse 4', 'Access Point 1'],
        ])
        await clickButton(connectedDialog, 'Patchkabel ein-/ausstecken')
        expect(connectedDialog.get('[data-rack-slot="0"] [data-rack-patch-port="2"]').attributes('title')).toBe('Arbeitsplatz 2')
        await connectedDialog.get('[aria-label="Platz 2, Switch, Buchse 5"]').trigger('click')
        await connectedDialog.get('[aria-label="Platz 5, Datenserver, Buchse 1"]').trigger('click')
        expect(connectionStatus()).toMatch(/^LAN\s*aktivWLAN\s*aktivInternet\s*aktivDatenserver\s*erreichbar$/)
        expect(workingComponents(connectedDialog)).toContain('Platz 5, Datenserver')
        expect(connectedDialog.get('[data-rack-slot="4"] .rack-module-graphic__status').classes()).toContain('is-lit')
        expect(litRackSlots()).toEqual(['patch-panel', 'switch', 'router', 'switch', 'server'])
        await clickButton(connectedDialog, 'Speichern')

        const withoutUplinkDialog = await openRack()
        await clickButton(withoutUplinkDialog, 'Patchkabel ein-/ausstecken')
        await withoutUplinkDialog.get('[aria-label="Patchkabel zwischen Platz 2, Switch, Buchse 4 und Platz 3, Router, Buchse 1 entfernen"]')
            .trigger('click')
        expect(connectionStatus()).toMatch(/^LAN\s*aktivWLAN\s*aktivInternet\s*inaktivDatenserver\s*erreichbar$/)
        expect(workingComponents(withoutUplinkDialog)).toEqual([
            'Platz 1, Patchpanel, 4 Anschlüsse belegt', 'Platz 2, Switch', 'Platz 4, Switch', 'Platz 5, Datenserver',
        ])
        expect(withoutUplinkDialog.get('[data-rack-slot="2"] .rack-module-graphic__status').classes()).not.toContain('is-lit')
        expect(litRackSlots()).toEqual(['patch-panel', 'switch', 'switch', 'server'])
        expect(activeChannels()).toEqual(connectedChannels)
        expect(badgeCounts()).toEqual([1, 1, 1, 1, 1])
        expect(mark(5).attributes('aria-label')).toContain('per LAN verbunden')
        expect(mark(11).attributes('aria-label')).toContain('per WLAN verbunden')
        await clickButton(withoutUplinkDialog, 'Abbrechen')
        await mark(4).trigger('click')
        await actions.get('[aria-label="Arbeitsplatz entfernen"]').trigger('click')
        expect(onlineIds()).toEqual(['5', '6', '7', '11'])
        expect(mark(6).text()).toBe('Arbeitsplatz 2')

        const edited = await openRack()
        await edited.get('[aria-label="Patchkabel zwischen Platz 1, Patchpanel, Buchse 4 und Platz 2, Switch, Buchse 3 entfernen"]')
            .trigger('click')
        expect(onlineIds()).toEqual(['5', '6'])
        expect(connectionStatus()).toMatch(/^LAN\s*aktivWLAN\s*inaktivInternet\s*aktivDatenserver\s*erreichbar$/)
        expect(mark(7).attributes('aria-label')).not.toContain('mit Internet verbunden')
        await clickButton(edited, 'Speichern')
        expect(onlineIds()).toEqual(['5', '6'])

        await wrapper.get('[data-wall="partition-office-1-top"]').trigger('click')
        await actions.get('[aria-label="Kabelkanal entfernen"]').trigger('click')
        expect(activeChannels()).toEqual([])
        expect(connectionStatus()).toMatch(/^LAN\s*inaktivWLAN\s*inaktivInternet\s*aktivDatenserver\s*nicht erreichbar$/)
        await placeMissingDuct()
        expect(onlineIds()).toEqual(['5', '6'])

        await mark(10).trigger('click')
        await clickButton(actions.get('#network-floor-marker-actions'), 'Verbindung lösen')
        expect(onlineIds()).toEqual([])
        expect(activeChannels()).toEqual([])
        expect(connectionStatus()).toMatch(/^LAN\s*inaktivWLAN\s*inaktivInternet\s*aktivDatenserver\s*nicht erreichbar$/)
    })

    it.each([
        { description: 'connects the aligned west-wall ducts', middleDuct: true, activeChannels: ['1', '4', '7'] },
        { description: 'leaves the west-wall route offline with a missing duct', middleDuct: false, activeChannels: [] },
    ])('$description through the office and server room walls', async ({ middleDuct, activeChannels }) => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        const cord = (slot, port, targetSlot, targetPort) => ({
            start: { slot, port }, end: { slot: targetSlot, port: targetPort },
        })
        const placements = [
            { id: 1, deviceId: 'cable-duct', wallId: 'west-office-1', x: 0, y: .1 },
            { id: 2, deviceId: 'network-outlet', channelId: 1, channelPosition: .3 },
            { id: 3, deviceId: 'workstation', x: .25, y: .2 },
            { id: 4, deviceId: 'cable-duct', wallId: 'west-office-2', x: 0, y: .4 },
            { id: 5, deviceId: 'network-outlet', channelId: 4, channelPosition: .3 },
            { id: 6, deviceId: 'workstation', x: .25, y: .5 },
            { id: 7, deviceId: 'cable-duct', wallId: 'west-server', x: 0, y: .8 },
            { id: 8, deviceId: 'bundle-outlet', channelId: 7, channelPosition: .3, rackId: 9 },
            { id: 9, deviceId: 'server-rack', x: .2, y: .8, rackModules: ['patch-panel', 'switch', 'router'],
                rackPatchConnections: [cord(0, 0, 1, 0), cord(0, 1, 1, 1), cord(0, 2, 1, 2), cord(1, 3, 2, 0)] },
            { id: 10, deviceId: 'cable-duct', wallId: 'north-foyer', x: .8, y: 0 },
            { id: 11, deviceId: 'network-outlet', channelId: 10, channelPosition: .5 },
            { id: 12, deviceId: 'access-point', x: .8, y: .2 },
        ]
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            floorLayoutVersion: 2,
            placements: placements.filter(({ id }) => middleDuct || id !== 4),
            connections: [[2, 3], [5, 6], [11, 12]].map(([outlet, device], port) => ({
                id: port + 1,
                start: { placementId: outlet, index: 0 },
                end: { placementId: device, index: 0 },
                rackPort: port,
            })),
        }))

        const { wrapper } = await renderTools(path, 'mailpit', 1)
        await measureRoomPlan(wrapper)

        const status = wrapper.get('.network-floor__connection-status').text()
        expect(status).toMatch(middleDuct ? /^LAN\s*aktivWLAN\s*inaktiv/ : /^LAN\s*inaktivWLAN\s*inaktiv/)
        const onlineChannels = wrapper.findAll('[data-wall]').filter(marker =>
            marker.attributes('aria-label')?.includes('Verbindung aktiv'))
        expect(onlineChannels.map(marker => marker.attributes('data-placement-id'))).toEqual(activeChannels)
        expect(wrapper.findAll('.network-floor__channel-bridge')).toHaveLength(middleDuct ? 2 : 0)
    })

    it.each([0, 2])('shows %i incoming cables only while the bundle is linked to the rack', async cableCount => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            placements: [
                { id: 1, deviceId: 'cable-duct', wallId: 'south-server', x: .2, y: .95 },
                { id: 2, deviceId: 'bundle-outlet', channelId: 1, channelPosition: .5 },
                { id: 3, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['patch-panel'] },
                { id: 4, deviceId: 'server-rack', x: .35, y: .8, rackModules: ['patch-panel'] },
                { id: 5, deviceId: 'cable-duct', wallId: 'north-office-1', x: .25, y: .05 },
                { id: 6, deviceId: 'double-network-outlet', channelId: 1, channelPosition: .7 },
                { id: 7, deviceId: 'workstation', x: .2, y: .8 },
                { id: 8, deviceId: 'workstation', x: .3, y: .8 },
                { id: 9, deviceId: 'workstation', x: .4, y: .2 },
                { id: 10, deviceId: 'network-outlet', channelId: 5, channelPosition: .7 },
            ],
            connections: [
                { id: 1, start: { placementId: 7, index: 0 }, end: { placementId: 6, index: 0 } },
                { id: 2, start: { placementId: 6, index: 1 }, end: { placementId: 8, index: 0 } },
            ].slice(0, cableCount),
        }))
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        await measureRoomPlan(wrapper)
        const actions = new DOMWrapper(document.body)
        const marker = id => wrapper.get(`[data-placement-id="${id}"]`)
        const dialog = () => wrapper.get('[role="dialog"]')
        const clickNamedButton = (scope, name) => scope.findAll('button').find(button => button.text() === name)!.trigger('click')
        async function openRack(id) {
            await marker(id).trigger('click')
            await clickNamedButton(actions.get('#network-floor-marker-actions'), 'Serverschrank konfigurieren')
        }

        await clickNamedButton(wrapper.get('[aria-label="Geräteauswahl"]'), 'Kabelstrang')
        await marker(2).trigger('click')
        await marker(3).trigger('click')

        await openRack(3)
        expect(dialog().text()).toContain(`Eingehende Kabel: ${cableCount}`)
        const connectedPorts = () => dialog().findAll('.rack-module-graphic__ports > .is-connected')
        expect(connectedPorts()).toHaveLength(cableCount)
        if (cableCount > 0) {
            await dialog().get('button[aria-label="Platz 1, Patchpanel, 2 Anschlüsse belegt"]').trigger('click')
            await dialog().get('button[aria-label="Platz 3, frei"]').trigger('click')
            const movedPanel = dialog().get('button[aria-label="Platz 3, Patchpanel, 2 Anschlüsse belegt"]')
            expect(movedPanel.findAll('.rack-module-graphic__ports > .is-connected')).toHaveLength(cableCount)
            await dialog().get('button[aria-label="Patchpanel aus Platz 3 entfernen"]').trigger('click')
            await clickNamedButton(wrapper.get('[role="dialog"][aria-labelledby="network-rack-remove-title"]'), 'Entfernen')
            expect(connectedPorts()).toHaveLength(0)
        }
        await clickNamedButton(dialog(), 'Abbrechen')
        await openRack(4)
        expect(dialog().text()).not.toContain('Eingehende Kabel')
        expect(connectedPorts()).toHaveLength(0)
        await clickNamedButton(dialog(), 'Abbrechen')

        await marker(2).trigger('click')
        await clickNamedButton(actions.get('#network-floor-marker-actions'), 'Verbindung lösen')
        await openRack(3)
        expect(dialog().text()).not.toContain('Eingehende Kabel')
    })

    it('keeps rack cables on moved components and lets an occupied socket disconnect its cable', async () => {
        const path = '/admin/teaching-tools/tests/networks/build-network/room-plan/1'
        window.localStorage.setItem('schooltool:network-room-plan:v1:1:21:1', JSON.stringify({
            placements: [{ id: 1, deviceId: 'server-rack', x: .25, y: .8, rackModules: ['patch-panel', 'switch', 'server'] }],
            connections: [],
        }))
        async function openRack(wrapper) {
            await wrapper.get('[data-placement-id="1"]').trigger('click')
            await new DOMWrapper(document.body).get('#network-floor-marker-actions button').trigger('click')
            return wrapper.get('[role="dialog"]')
        }
        const clickButton = (scope, label) => scope.findAll('button').find(button => button.text() === label)!.trigger('click')
        const { wrapper } = await renderTools(path, 'mailpit', 1)
        const dialog = await openRack(wrapper)
        await clickButton(dialog, 'Patchkabel ein-/ausstecken')

        const overlay = dialog.get('.network-rack-config__patch-lines').element
        const firstSocket = dialog.get('[aria-label="Platz 1, Patchpanel, Buchse 1"]')
        const secondSocket = dialog.get('[aria-label="Platz 2, Switch, Buchse 2"]')
        Object.defineProperties(overlay, { clientWidth: { value: 400 }, clientHeight: { value: 600 } })
        overlay.getBoundingClientRect = () => new DOMRect(10, 20, 200, 300)
        firstSocket.element.getBoundingClientRect = () => new DOMRect(70, 98, 22, 22)
        secondSocket.element.getBoundingClientRect = () => new DOMRect(96, 174, 22, 22)

        await firstSocket.trigger('click')
        await dialog.get('[aria-label="Platz 1, Patchpanel, Buchse 2"]').trigger('click')
        expect(dialog.get('[role="status"]').text()).toContain('anderen Komponente')
        await secondSocket.trigger('click')
        const segments = dialog.findAll<HTMLElement>('.network-rack-config__patch-line > span')
        const socketPoint = ({ element }: DOMWrapper<HTMLElement>) => {
            const { left, top, width, height } = element.style
            return [parseFloat(left) + parseFloat(width) / 2, parseFloat(top) + parseFloat(height)]
        }
        expect(socketPoint(segments[0])).toEqual([142, 158])
        expect(socketPoint(segments.at(-1)!)).toEqual([194, 310])
        expect(dialog.get('[aria-label="Platz 1, Patchpanel, Buchse 1, verbunden mit Platz 2, Switch, Buchse 2"]').exists()).toBe(true)
        await dialog.get('[aria-label="Platz 2, Switch, Buchse 2, verbunden mit Platz 1, Patchpanel, Buchse 1"]').trigger('click')
        expect(dialog.get('[role="status"]').text()).toContain('bereits')
        await dialog.get('button[aria-label="Platz 1, Patchpanel"]').trigger('click')
        await dialog.get('button[aria-label="Platz 3, Datenserver"]').trigger('click')
        await clickButton(dialog, 'Speichern')

        const { wrapper: restored } = await renderTools(path, 'mailpit', 1)
        const restoredDialog = await openRack(restored)
        const cable = 'Patchkabel zwischen Platz 3, Patchpanel, Buchse 1 und Platz 2, Switch, Buchse 2 entfernen'
        expect(restoredDialog.find(`[aria-label="${cable}"]`).exists()).toBe(true)
        await clickButton(restoredDialog, 'Patchkabel ein-/ausstecken')
        const switchPort = restoredDialog.get('[aria-label="Platz 2, Switch, Buchse 2, verbunden mit Platz 3, Patchpanel, Buchse 1"]')
        await switchPort.trigger('click')
        await clickButton(restoredDialog.get('[aria-label="Buchsen auf Platz 2"]'), 'Patchkabel entfernen')
        await flushPromises()

        expect(restoredDialog.get('[aria-label="Platz 2, Switch, Buchse 2"]').element).toBe(document.activeElement)
        expect(restoredDialog.find('[aria-label="Platz 3, Patchpanel, Buchse 1"]').exists()).toBe(true)
        expect(restoredDialog.find(`[aria-label="${cable}"]`).exists()).toBe(false)
        await clickButton(restoredDialog, 'Speichern')
        const reopened = await openRack(restored)
        expect(reopened.find(`[aria-label="${cable}"]`).exists()).toBe(false)
    })

    it('keeps the local-only simulator out of Office navigation and explains direct links without contacting Mailpit', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/simulator', 'office_outlook')
        expect(wrapper.find('a[href="/admin/teaching-tools/simulator"]').exists()).toBe(false)
        expect(wrapper.text()).toContain('nur in der lokalen Testumgebung verfügbar')
        expect(axios.get).not.toHaveBeenCalled()
    })
    it('keeps Multiple Choice as the default and opens the existing workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools')

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/multiple-choice')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/admin/teaching-tools/multiple-choice"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').text()).toBe('Spiele')
    })

    it('lists Tests immediately after Multiple Choice and shows the Netzwerke card', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools', 'office_outlook')
        const testsPath = '/admin/teaching-tools/tests'
        const navigation = wrapper.get('[aria-label="Unterricht Tools"]')

        expect(navigation.findAll('a').map(link => link.text())).toEqual([
            'Multiple Choice', 'Tests', 'Spiele', 'Erklärungen',
        ])
        await navigation.get(`a[href="${testsPath}"]`).trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe(testsPath)
        expect(navigation.get(`a[href="${testsPath}"]`).attributes('aria-pressed')).toBe('true')
        expect(wrapper.get('h1').text()).toBe('Tests')
        expect(wrapper.findAll('li').map(entry => entry.get('h2').text())).toEqual(['Netzwerke'])
        expect(wrapper.get('li').text()).toContain('In Vorbereitung')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)

        const direct = await renderTools(testsPath, 'office_outlook')
        expect(direct.wrapper.get('h1').text()).toBe('Tests')
        expect(direct.wrapper.get('li h2').text()).toBe('Netzwerke')
        expect(axios.get).not.toHaveBeenCalled()
    })

    it('opens the device questions separately and saves the edited model answers', async () => {
        const overview = '/admin/teaching-tools/tests/networks'
        const page = `${overview}/device-questions`
        const endpoint = `/api${page}`
        const serverData = {
            school_id: 21, revision: 0,
            answers: {
                patch_panel: 'Feste Netzwerkkabel enden hier.', switch: 'Verbindet Geräte im LAN.',
                router: 'Verbindet Netzwerke.', data_server: 'Stellt Dateien bereit.', access_point: 'Stellt WLAN bereit.',
            },
        }
        const loadAnswers = vi.fn().mockResolvedValue({ data: serverData })
        const saveAnswers = vi.fn().mockImplementation((url, payload) => Promise.resolve({ data: { ...payload, revision: 1 } }))
        vi.stubGlobal('axios', { get: loadAnswers, put: saveAnswers })
        const { wrapper, router } = await renderTools(overview)

        await wrapper.get(`li a[href="${page}"]`).trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe(page)
        expect(wrapper.get('h1').text()).toBe('Fragen zu Geräten')
        expect(wrapper.find('#ground-floor-task-title').exists()).toBe(false)
        expect(loadAnswers).toHaveBeenCalledWith(endpoint)
        const fields = wrapper.findAll<HTMLTextAreaElement>('textarea')
        expect(fields.map(field => wrapper.get(`label[for="${field.element.id}"]`).text())).toEqual([
            'Was ist die Aufgabe des Patchpanels?',
            'Was ist die Aufgabe des Switchs?',
            'Was ist die Aufgabe des Routers?',
            'Was ist die Aufgabe des Datenservers?',
            'Was ist die Aufgabe des WLAN-Access-Points?',
        ])
        expect(fields.map(field => field.element.value)).toEqual(Object.values(serverData.answers))
        await fields[2].setValue('Der Router leitet Pakete zwischen IP-Netzen weiter.')
        await wrapper.get('form').trigger('submit')
        await flushPromises()
        expect(saveAnswers).toHaveBeenCalledWith(endpoint, {
            ...serverData, answers: { ...serverData.answers, router: 'Der Router leitet Pakete zwischen IP-Netzen weiter.' },
        })
        expect(wrapper.get('[role="status"]').text()).toBe('Musterantworten gespeichert.')
    })

    it('creates, edits and deletes a question from Weitere Fragen', async () => {
        const overview = '/admin/teaching-tools/tests/networks'
        const page = overview + '/additional-questions'
        const endpoint = '/api' + page
        const initial = { id: 15, question: 'Was ist ein LAN?', answer: 'Ein lokales Netzwerk.' }
        const revised = { ...initial, question: 'Was ist ein WLAN?', answer: 'Ein drahtloses lokales Netzwerk.' }
        const request = {
            get: vi.fn().mockResolvedValue({ data: [] }),
            post: vi.fn().mockResolvedValue({ data: initial }),
            put: vi.fn().mockResolvedValue({ data: revised }),
            delete: vi.fn().mockResolvedValue({}),
        }
        vi.stubGlobal('axios', request)
        const { wrapper, router } = await renderTools(overview)
        const action = (text: string) => wrapper.findAll('button').find(button => button.text() === text)!

        await wrapper.get('li a[href="' + page + '"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe(page)
        expect(wrapper.get('h1').text()).toBe('Weitere Fragen')
        expect(request.get).toHaveBeenCalledWith(endpoint)

        await action('Frage hinzufügen').trigger('click')
        await wrapper.get('#additional-question').setValue(initial.question)
        await wrapper.get('#additional-answer').setValue(initial.answer)
        await wrapper.get('form').trigger('submit')
        await flushPromises()
        expect(request.post).toHaveBeenCalledWith(endpoint, { question: initial.question, answer: initial.answer })
        expect(wrapper.text()).toContain(initial.question)

        await action('Bearbeiten').trigger('click')
        await wrapper.get('#additional-question').setValue(revised.question)
        await wrapper.get('#additional-answer').setValue(revised.answer)
        await wrapper.get('form').trigger('submit')
        await flushPromises()
        expect(request.put).toHaveBeenCalledWith(endpoint + '/15', { question: revised.question, answer: revised.answer })
        expect(wrapper.text()).toContain(revised.answer)

        await action('Löschen').trigger('click')
        expect(wrapper.text()).toContain('Diese Frage löschen?')
        await action('Löschen').trigger('click')
        await flushPromises()
        expect(request.delete).toHaveBeenCalledWith(endpoint + '/15')
        expect(wrapper.text()).toContain('Noch keine Fragen vorhanden.')
    })

    it('shows the network test areas and opens the only room plan', async () => {
        const testsPath = '/admin/teaching-tools/tests'
        const networksPath = `${testsPath}/networks`
        const buildPath = `${networksPath}/build-network`
        const { wrapper, router } = await renderTools(testsPath)

        await wrapper.get(`li a[href="${networksPath}"]`).trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe(networksPath)
        expect(wrapper.get('h1').text()).toBe('Netzwerke')
        const sections = wrapper.findAll('li')
        expect(sections.map(section => section.get('h2').text())).toEqual([
            'Netzwerk bauen', 'Fragen zu Geräten', 'Weitere Fragen',
        ])
        await sections[0].get(`a[href="${buildPath}"]`).trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe(buildPath)
        expect(wrapper.get('h1').text()).toBe('Netzwerk bauen')
        const plans = wrapper.findAll('li a')
        expect(plans.map(plan => plan.get('h2').text())).toEqual(['Raumplan 1'])
        expect(wrapper.get(`a[href="${testsPath}"]`).attributes('aria-pressed')).toBe('true')

        const firstPlanPath = `${buildPath}/room-plan/1`
        await wrapper.get(`li a[href="${firstPlanPath}"]`).trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe(firstPlanPath)
        const planHeader = wrapper.get('header')
        expect(planHeader.get('h1').text()).toBe('Raumplan 1: Erdgeschoss')
        const instructions = wrapper.get<HTMLDetailsElement>('details[aria-labelledby="ground-floor-task-title"]')
        const stepButtons = instructions.get('[aria-label="Arbeitsschritte"]').findAll('button')
        const taskCard = instructions.get('#ground-floor-task-card')
        expect(stepButtons.map(button => button.text())).toEqual(['1', '2', '3', '4', '5', '6', '7'])
        expect(stepButtons[0].attributes('aria-pressed')).toBe('true')
        expect(taskCard.text()).toContain('fünf Arbeitsplätze')
        expect(taskCard.text()).toContain('Foyer vier Stehtische')
        await stepButtons[6].trigger('click')
        expect(stepButtons[6].attributes('aria-pressed')).toBe('true')
        expect(stepButtons[0].attributes('aria-pressed')).toBe('false')
        expect(taskCard.text()).toContain('der Zugriff auf den Datenserver funktionieren')
        expect(taskCard.text()).toContain('alle fünf Arbeitsplätze per LAN und alle vier Laptops an den Stehtischen per WLAN')
        expect(taskCard.text()).not.toContain('Foyer vier Stehtische')
        expect(instructions.element.open).toBe(true)
        await instructions.get('summary').trigger('click')
        expect(instructions.element.open).toBe(false)
        await instructions.get('summary').trigger('click')
        expect(instructions.element.open).toBe(true)
        expect(wrapper.find('textarea').exists()).toBe(false)
        expect(wrapper.get('[aria-label="Grundriss"]').findAll('.network-floor__room').map(room => room.text())).toEqual([
            'Büro 1', 'Büro 2', 'Serverraum', 'Foyer',
        ])
        expect(planHeader.get('[role="status"][aria-label="Netzwerkstatus"]').text()).toMatch(/LAN.*WLAN.*Internet/)
        const palette = wrapper.get('[aria-label="Geräteauswahl"]')
        expect(palette.findAll('section').map(group => ({
            title: group.get('h3').text(),
            devices: group.findAll('button[aria-pressed]').map(button => button.text()),
        }))).toEqual([
            { title: 'Arbeitsplätze und Möbel', devices: ['5 × Arbeitsplatz', '4 × Stehtisch'] },
            { title: 'Netzwerk und Server', devices: ['Router', 'Switch', 'Access Point', 'Datenserver', 'Serverschrank'] },
            { title: 'Verkabelung', devices: ['Kabelkanal', 'Einfache Netzwerkdose', 'Doppelte Netzwerkdose',
                'Kabelstrang-Ausgang', 'Kabelstrang', 'Patchkabel'] },
        ])
        expect(palette.find('button[aria-label^="Foto anzeigen:"]').exists()).toBe(false)
        const zoom = planHeader.get('[aria-label="Grundriss-Zoom"]')
        expect(zoom.get('output').text()).toBe('100 %')
        await zoom.get('[aria-label="Grundriss vergrößern"]').trigger('click')
        expect(zoom.get('output').text()).toBe('105 %')
        await zoom.get('[aria-label="Grundriss vergrößern"]').trigger('click')
        expect(zoom.get('output').text()).toBe('110 %')
        await zoom.get('[aria-label="Grundriss verkleinern"]').trigger('click')
        expect(zoom.get('output').text()).toBe('105 %')
        expect(zoom.findAll('button').map(button => button.text())).toEqual(['−', '+'])

        await wrapper.get(`section a[href="${buildPath}"]`).trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerk bauen')
        await wrapper.get(`section a[href="${networksPath}"]`).trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerke')
        await wrapper.get(`section a[href="${testsPath}"]`).trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Tests')
        expect(axios.get).not.toHaveBeenCalled()
    })

    it.each([
        ['/admin/teaching-tools/tests/networks', 'Netzwerke', 3],
        ['/admin/teaching-tools/tests/networks/build-network', 'Netzwerk bauen', 1],
    ])('renders %s directly', async (path, title, cardCount) => {
        const { wrapper } = await renderTools(path)
        expect(wrapper.get('h1').text()).toBe(title)
        expect(wrapper.findAll('li')).toHaveLength(cardCount)
        expect(wrapper.find('[aria-label="Grundriss"]').exists()).toBe(false)
        expect(axios.get).not.toHaveBeenCalled()
    })

    it('offers Zahlensysteme and opens the playable adventure workspace', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/games')

        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)
        expect(wrapper.find('a[href="/admin/teaching-tools/games"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.findAll('[aria-label="Verfügbare Spiele"] a')).toHaveLength(2)
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').text()).toContain('Zahlensysteme')
        await wrapper.find('[aria-label="Zahlensysteme öffnen"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/games/number-systems')
        expect(wrapper.get('[data-test="number-systems"]').text()).toContain('Neues Abenteuer')
        const back = wrapper.findAll('a').find(link => link.text() === 'Zur Spieleübersicht')
        await back.trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Spiele')
        expect(wrapper.find('[aria-label="Zahlensysteme öffnen"]').exists()).toBe(true)
    })

    it('opens Netzwerke from the second tile and directly, then returns to the games list', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/games')

        await wrapper.get('[aria-label="Netzwerke öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/games/networks')
        expect(wrapper.get('h1').text()).toBe('Netzwerke')
        expect(wrapper.findAll('svg [role="button"]')).toHaveLength(88)
        expect(wrapper.find('iframe').exists()).toBe(false)
        expect(axios.get).not.toHaveBeenCalled()
        await wrapper.get('.teaching-games__back').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Spiele')

        const direct = await renderTools('/admin/teaching-tools/games/networks')
        expect(direct.wrapper.get('h1').text()).toBe('Netzwerke')
    })

    it('navigates from categories through number systems to management and starts the student view only on request', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools')

        await wrapper.get('a[href="/admin/teaching-tools/explanations"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations')
        expect(wrapper.get('a[href="/admin/teaching-tools/explanations"]').attributes('aria-pressed')).toBe('true')
        expect(wrapper.get('h1').text()).toBe('Erklärungen')
        expect(wrapper.find('[aria-label="Dezimal auf Dual öffnen"]').exists()).toBe(false)
        expect(wrapper.get('[aria-label="Verfügbare Kategorien"]').findAll('a')).toHaveLength(3)
        await wrapper.get('[aria-label="Zahlensysteme öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/number-systems')
        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        expect(wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').text()).toContain('Dezimal auf Dual')
        expect(wrapper.find('[data-test="mc-workspace"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Verfügbare Spiele"]').exists()).toBe(false)

        await wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(wrapper.get('h1').text()).toBe('Dezimal auf Dual')
        expect(wrapper.find('.binary-explanation').exists()).toBe(false)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(false)
        expect(window.axios.post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await flushPromises()
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] input').element.value).toBe('13'))
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Veröffentlichen')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Freigabe widerrufen')
        await wrapper.findAll('button').find(button => button.text() === 'Vorschau beenden').trigger('click')
        await flushPromises()
        expect(wrapper.find('.binary-explanation').exists()).toBe(false)
        await wrapper.get('.explanations__back').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        await wrapper.get('.explanations__back').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Erklärungen')
        const direct = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(direct.wrapper.get('h1').text()).toBe('Dezimal auf Dual')
        expect(direct.wrapper.find('.binary-explanation').exists()).toBe(false)
        expect(direct.wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations/number-systems')
    })

    it('restores the student preview from the URL and preserves unrelated parameters and history', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/network-media?other=kept#section')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await flushPromises()
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] h1').text()).toBe('Übertragungsmedien, LAN und WLAN'))
        expect(router.currentRoute.value.query.preview).toBe('1')
        expect(router.currentRoute.value.query.other).toBe('kept')
        expect(router.currentRoute.value.hash).toBe('#section')

        const restored = await renderTools(router.currentRoute.value.fullPath)
        await vi.waitFor(() => expect(restored.wrapper.get('[role="dialog"] h1').text()).toBe('Übertragungsmedien, LAN und WLAN'))

        await wrapper.findAll('button').find(button => button.text() === 'Vorschau beenden').trigger('click')
        await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(router.currentRoute.value.query.preview).toBeUndefined()
        expect(router.currentRoute.value.query.other).toBe('kept')
        router.back()
        await vi.waitFor(() => expect(router.currentRoute.value.query.preview).toBe('1'))
        await vi.waitFor(() => expect(wrapper.find('[role="dialog"]').exists()).toBe(true))
    })

    it('opens the number systems category with binary and hexadecimal explanations', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        expect(wrapper.get('h1').text()).toBe('Zahlensysteme')
        expect(wrapper.get('[aria-label="Verfügbare Erklärungen"]').findAll('a')).toHaveLength(6)
        expect(wrapper.get('[aria-label="Dezimal auf Dual öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(wrapper.get('[aria-label="Dual auf Dezimal öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/binary-to-decimal')
        expect(wrapper.get('[aria-label="Hexadezimal auf Dezimal öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/hexadecimal-to-decimal')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('opens the artificial intelligence category and its interactive student preview without publishing', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations')

        expect(wrapper.get('[aria-label="Künstliche Intelligenz öffnen"]').text()).toContain('1 Erklärung')
        await wrapper.get('[aria-label="Künstliche Intelligenz öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/artificial-intelligence')
        await wrapper.get('[aria-label="Wie ein neuronales Netz lernt öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Wie ein neuronales Netz lernt')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations/artificial-intelligence')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/neural-network-learning')

        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] iframe').attributes('title')).toBe('Wie ein neuronales Netz lernt'))
        expect(wrapper.get('[role="dialog"] iframe').attributes('sandbox')).toBe('allow-scripts')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('keeps the student preview and other URL state when the learning step changes', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/neural-network-learning?preview=1&other=kept#section')
        await vi.waitFor(() => expect(wrapper.find('[role="dialog"] iframe').exists()).toBe(true))
        const frame = wrapper.get('[role="dialog"] iframe')
        window.dispatchEvent(new MessageEvent('message', { source: frame.element.contentWindow, data: { type: 'schooltool-neural-network-step', step: 12 } }))
        await vi.waitFor(() => expect(router.currentRoute.value.query.lernschritt).toBe('12'))
        expect(router.currentRoute.value.query.preview).toBe('1')
        expect(router.currentRoute.value.query.other).toBe('kept')
        expect(router.currentRoute.value.hash).toBe('#section')
        expect(wrapper.find('[role="dialog"] iframe').exists()).toBe(true)
    })

    it('shows the confirmed availability and an unknown status when the status refresh fails', async () => {
        const url = 'https://schooltool.example/explanations/active'
        window.axios.get.mockImplementation(path => {
            if (path.endsWith('/binary-to-hexadecimal')) return Promise.reject(new Error('Unavailable'))
            if (path.endsWith('/decimal-to-binary')) return Promise.resolve({ data: { ...sharingState, published: true, invitation: { url } } })
            if (path.endsWith('/hexadecimal-to-binary')) return Promise.resolve({ data: { ...sharingState, available: false, published: true, invitation: { url } } })
            return Promise.resolve({ data: sharingState })
        })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        expect(wrapper.find('[aria-label="Dezimal auf Dual: veröffentlicht"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Dezimal auf Hexadezimal: nicht verfügbar"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Dual auf Hexadezimal: Status unbekannt"]').exists()).toBe(true)
        expect(wrapper.find('[aria-label="Hexadezimal auf Dual: nicht verfügbar"]').exists()).toBe(true)

        window.axios.get.mockRejectedValueOnce(new Error('Unavailable'))
        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()
        expect(wrapper.find('[aria-label="Dezimal auf Dual: veröffentlicht"]').exists()).toBe(false)
        expect(wrapper.find('[aria-label="Dezimal auf Dual: Status unbekannt"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Der aktuelle Link konnte nicht geladen werden')
    })

    it('opens the network learning area, student preview and sharing setup', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations')
        expect(wrapper.get('[aria-label="Netzwerke öffnen"]').text()).toContain('7 Erklärungen')
        expect(wrapper.get('[aria-label="Verfügbare Kategorien"]').find('.explanation-tile__preview').exists()).toBe(false)
        await wrapper.get('[aria-label="Netzwerke öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerke')
        expect(wrapper.get('[aria-label="Verfügbare Erklärungen"]').findAll('a')).toHaveLength(7)
        expect(wrapper.get('[aria-label="Übertragungsmedien, LAN und WLAN öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-media')
        expect(wrapper.get('[aria-label="Datenkommunikation und Internet öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-communication')
        expect(wrapper.get('[aria-label="IP-Adressen, Netze und Routing öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-addressing')
        expect(wrapper.get('[aria-label="Netzwerkprotokolle und Dienste öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-protocols')
        expect(wrapper.get('[aria-label="Netzwerksicherheit und Datenschutz öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-security')
        expect(wrapper.get('[aria-label="Netzwerke planen, betreiben und Störungen erkennen öffnen"]').attributes('href')).toBe('/admin/teaching-tools/explanations/network-operations')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations')
        await wrapper.get('[aria-label="Netzwerkgrundlagen und Geräte öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Netzwerkgrundlagen und Geräte')
        expect(wrapper.get('.explanations__back').attributes('href')).toBe('/admin/teaching-tools/explanations/networks')
        expect(wrapper.text()).not.toContain('Mit eigener Zahl')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-fundamentals')
        expect(window.axios.post).not.toHaveBeenCalled()
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] h1').text()).toContain('Netzwerkgrundlagen'))
        await wrapper.findAll('button').find(button => button.text() === 'Vorschau beenden').trigger('click')
        window.axios.post.mockResolvedValueOnce({ data: { ...sharingState, published: true, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/network', qr_data_url: 'data:image/svg+xml;base64,test' } } })
        await wrapper.get('input[value="public"]').setValue(true)
        await wrapper.findAll('button').find(button => button.text() === 'Veröffentlichen').trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-fundamentals/publication', { access_mode: 'public' })
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe('https://schooltool.example/explanations/network')
    })

    it('opens network protocols and loads the interactive student preview without publishing', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="Netzwerkprotokolle und Dienste öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/network-protocols')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-protocols')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] .protocols-lesson h1').text()).toBe('Netzwerkprotokolle und Dienste'))
        expect(wrapper.get('[role="dialog"]').text()).toContain('Client und Server')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('opens network security and loads its student preview without publishing', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="Netzwerksicherheit und Datenschutz öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/network-security')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-security')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] .security-lesson h1').text()).toBe('Netzwerksicherheit und Datenschutz'))
        expect(wrapper.get('[role="dialog"]').text()).toContain('Vertraulichkeit')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('opens network operations and loads its student preview without publishing', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="Netzwerke planen, betreiben und Störungen erkennen öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/network-operations')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-operations')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] .operations-lesson h1').text()).toBe('Netzwerke planen, betreiben und Störungen erkennen'))
        expect(wrapper.get('[role="dialog"]').text()).toContain('Nutzung und Dienste')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('opens IP addressing from the network overview and renders its packet journey in the student preview', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="IP-Adressen, Netze und Routing öffnen"]').trigger('click')
        await flushPromises()
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/network-addressing')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-addressing')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] .addressing-lesson h1').text()).toBe('IP-Adressen, Netze und Routing'))
        expect(wrapper.get('[role="dialog"]').text()).toContain('Was passiert wann?')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('opens the transmission media explanation from the network overview', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="Übertragungsmedien, LAN und WLAN öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Übertragungsmedien, LAN und WLAN')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-media')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] h1').text()).toBe('Übertragungsmedien, LAN und WLAN'))
    })

    it('opens the data communication explanation from the network overview', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/networks')
        await wrapper.get('[aria-label="Datenkommunikation und Internet öffnen"]').trigger('click')
        await flushPromises()
        expect(wrapper.get('h1').text()).toBe('Datenkommunikation und Internet')
        expect(window.axios.get).toHaveBeenCalledWith('/api/admin/teaching-explanations/network-communication')
        await wrapper.findAll('button').find(button => button.text() === 'Schüleransicht testen').trigger('click')
        await vi.waitFor(() => expect(wrapper.get('[role="dialog"] h1').text()).toBe('Datenkommunikation und Internet'))
    })

    it.each([
        ['decimal-to-binary', 'Dezimal auf Dual'],
        ['decimal-to-hexadecimal', 'Dezimal auf Hexadezimal'],
        ['binary-to-hexadecimal', 'Dual auf Hexadezimal'],
        ['hexadecimal-to-binary', 'Hexadezimal auf Dual'],
        ['binary-to-decimal', 'Dual auf Dezimal'],
        ['hexadecimal-to-decimal', 'Hexadezimal auf Dezimal'],
    ])('copies the current student link for %s directly from its card', async (key, title) => {
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        window.axios.get.mockImplementation(url => Promise.resolve({ data: { ...sharingState, published: true,
            invitation: { url: `https://schooltool.example/explanations/${url.split('/').at(-1)}` } } }))
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/number-systems')
        const currentUrl = `https://schooltool.example/explanations/${key}-current`
        window.axios.get.mockResolvedValueOnce({ data: { ...sharingState, published: true,
            invitation: { url: currentUrl } } })

        await wrapper.get(`button[aria-label="${title}: Link kopieren"]`).trigger('click')
        await flushPromises()

        expect(writeText).toHaveBeenCalledExactlyOnceWith(currentUrl)
        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/number-systems')
        expect(wrapper.get(`button[aria-label="${title}: Link kopieren"]`).text()).toContain('Link kopiert')
        expect(window.axios.post).not.toHaveBeenCalled()
        expect(window.axios.delete).not.toHaveBeenCalled()
    })

    it('opens sharing setup for an unpublished card without publishing it', async () => {
        const { wrapper, router } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link einrichten"]').trigger('click')
        await flushPromises()

        expect(router.currentRoute.value.path).toBe('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('offers the student link for manual copying when clipboard access fails', async () => {
        vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('Denied')) } })
        const url = 'https://schooltool.example/explanations/manual'
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true,
            invitation: { url, local_only: true } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()

        const card = wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').element.closest('li')!
        expect(card.querySelector('input')?.value).toBe(url)
        expect(card.textContent).toContain('Textfeld markieren und kopieren')
        expect(card.textContent).toContain('nur auf diesem Rechner erreichbar')
        expect(card.querySelector('[aria-label="Dezimal auf Dual: veröffentlicht"]')).not.toBeNull()
        expect(card.querySelector('button')?.textContent).not.toContain('Link kopiert')
    })

    it.each(['revoked', 'unavailable'])('does not copy a stale link when sharing is %s', async condition => {
        const writeText = vi.fn()
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true,
            invitation: { url: 'https://schooltool.example/explanations/stale' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/number-systems')
        if (condition === 'revoked') window.axios.get.mockResolvedValueOnce({ data: sharingState })
        else window.axios.get.mockRejectedValueOnce(new Error('Offline'))

        await wrapper.get('button[aria-label="Dezimal auf Dual: Link kopieren"]').trigger('click')
        await flushPromises()

        expect(writeText).not.toHaveBeenCalled()
        expect(wrapper.get('[role="alert"]').text()).toContain(condition === 'revoked' ? 'nicht freigegeben' : 'nicht geladen')
        expect(wrapper.find(`[aria-label="Dezimal auf Dual: ${condition === 'revoked' ? 'nicht verfügbar' : 'Status unbekannt'}"]`).exists()).toBe(true)
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('distinguishes unsaved access selection and replaces link and QR only after a successful save', async () => {
        const previous = { ...sharingState, published: true, invitation: { url: 'https://schooltool.example/explanations/old', qr_data_url: 'data:image/svg+xml;base64,old' } }
        window.axios.get.mockResolvedValue({ data: previous })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-hexadecimal')
        expect(wrapper.get('h1').text()).toBe('Dezimal auf Hexadezimal')
        await wrapper.get('input[value="public"]').setValue(true)
        expect(window.axios.post).not.toHaveBeenCalled()
        expect(wrapper.get('.explanation-unsaved').text()).toContain('bisherige Link und QR-Code ungültig')
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe(previous.invitation.url)
        const save = wrapper.findAll('button').find(button => button.text() === 'Zugangsänderung speichern')!
        window.axios.post.mockRejectedValueOnce({ response: { data: { message: 'Speichern fehlgeschlagen' } } })
        await save.trigger('click')
        await flushPromises()
        expect(wrapper.get('[role="alert"]').text()).toContain('Speichern fehlgeschlagen')
        expect(wrapper.find('.explanation-success').exists()).toBe(false)
        expect(wrapper.get('.explanation-detail__link input').element.value).toBe(previous.invitation.url)
        window.axios.post.mockResolvedValueOnce({ data: { ...previous, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/new', qr_data_url: 'data:image/svg+xml;base64,new' } } })
        await save.trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenLastCalledWith('/api/admin/teaching-explanations/decimal-to-hexadecimal/publication', { access_mode: 'public' })
        expect(wrapper.find('.explanation-unsaved').exists()).toBe(false)
        expect(wrapper.get('.explanation-success').text()).toContain('Zugang gespeichert')
        expect(wrapper.get('.explanation-detail__link input').element.value).toContain('/new')
        expect(wrapper.get('.explanation-detail__qr').attributes('src')).toBe('data:image/svg+xml;base64,new')
    })

    it('publishes only on explicit action with the chosen access and can revoke the share', async () => {
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')
        expect(window.axios.post).not.toHaveBeenCalled()
        window.axios.post.mockResolvedValue({ data: { ...sharingState, published: true, access_mode: 'public', invitation: { url: 'https://schooltool.example/explanations/token', qr_data_url: 'data:image/svg+xml;base64,test' } } })
        await wrapper.get('input[value="public"]').setValue(true)
        await wrapper.findAll('button').find(button => button.text() === 'Veröffentlichen').trigger('click')
        await flushPromises()
        expect(window.axios.post).toHaveBeenCalledWith('/api/admin/teaching-explanations/decimal-to-binary/publication', { access_mode: 'public' })
        expect(wrapper.get('img[alt="QR-Code zur freigegebenen Erklärung"]').attributes('src')).toBe('data:image/svg+xml;base64,test')
        window.axios.delete.mockResolvedValue({ data: sharingState })
        await wrapper.findAll('button').find(button => button.text() === 'Freigabe widerrufen').trigger('click')
        await flushPromises()
        expect(wrapper.text()).toContain('Nicht veröffentlicht')
        expect(wrapper.find('.explanation-detail__link').exists()).toBe(false)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(false)
    })

    it.each(['public', 'authenticated'])('shows the existing QR immediately with %s access without creating a publication', async accessMode => {
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true, access_mode: accessMode,
            invitation: { url: 'https://schooltool.example/explanations/existing', qr_data_url: 'data:image/svg+xml;base64,existing' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')

        expect(wrapper.get('img[alt="QR-Code zur freigegebenen Erklärung"]').attributes('src')).toBe('data:image/svg+xml;base64,existing')
        expect(wrapper.get('input[name="explanation-access"]:checked').element.value).toBe(accessMode)
        expect(window.axios.post).not.toHaveBeenCalled()
    })

    it('offers only link copying and the visible QR for sharing without an email entry or recipient picker', async () => {
        window.axios.get.mockResolvedValue({ data: { ...sharingState, published: true, invitation: { url: 'https://schooltool.example/explanations/token', qr_data_url: 'data:image/svg+xml;base64,existing' } } })
        const { wrapper } = await renderTools('/admin/teaching-tools/explanations/decimal-to-binary')

        expect(wrapper.findAll('button').some(button => button.text() === 'Link kopieren')).toBe(true)
        expect(wrapper.find('img[alt="QR-Code zur freigegebenen Erklärung"]').exists()).toBe(true)
        expect(wrapper.text()).not.toContain('E-Mail')
        expect(wrapper.find('select').exists()).toBe(false)
        expect(window.axios.post).not.toHaveBeenCalled()
    })
})
