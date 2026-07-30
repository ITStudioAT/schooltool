import { beforeEach, describe, expect, it, vi } from 'vitest'

const form = vi.hoisted(() => ({
    errors: {},
    reset: vi.fn(),
    setData: vi.fn(),
    setValidationTimeout: vi.fn(),
    validate: vi.fn(),
}))

vi.mock('laravel-precognition-vue', () => ({
    useForm: vi.fn(() => {
        form.setValidationTimeout.mockReturnValue(form)

        return form
    }),
}))

import { useMaterialV2Precognition } from '@/domains/materialsV2/useMaterialV2Precognition'

describe('useMaterialV2Precognition', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        form.errors = {}
        form.setValidationTimeout.mockReturnValue(form)
    })

    it('normalizes the live form before validating one field', () => {
        const dialog = { mode: 'create', item: null }
        const source = {
            title: '  Bruchrechnen  ',
            category: ' Mathematik ',
            clusterName: ' Wochenplan ',
            description: '',
            reminderDate: '',
            reminderTime: '',
            linkUrl: '',
            keywords: 'Brüche, Übung; Klasse 2',
            attachments: [],
        }
        const precognition = useMaterialV2Precognition({ dialog, source })

        precognition.validate('title')

        expect(form.setData).toHaveBeenCalledWith({
            title: 'Bruchrechnen',
            category: 'Mathematik',
            cluster_name: 'Wochenplan',
            description: null,
            reminder_date: null,
            reminder_time: null,
            link_url: null,
            user_keywords: ['Brüche', 'Übung', 'Klasse 2'],
            attachments: [],
        })
        expect(form.validate).toHaveBeenCalledWith('title')
    })

    it('prefers submit errors and exposes Precognition errors as Vuetify messages', () => {
        const precognition = useMaterialV2Precognition({
            dialog: { mode: 'edit', item: { id: 4 } },
            source: {},
        })
        form.errors = { title: 'Der Titel ist erforderlich.' }

        expect(precognition.errorMessages('title')).toEqual(['Der Titel ist erforderlich.'])
        expect(precognition.errorMessages('title', ['Serverfehler'])).toEqual(['Serverfehler'])
    })
})
