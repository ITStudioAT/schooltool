import { readFileSync } from 'node:fs'

import { describe, expect, it } from 'vitest'

const source = readFileSync('resources/js/components/NoteCard.vue', 'utf8')

describe('NoteCard', () => {
    it('renders note content as escaped text', () => {
        expect(source).toContain('{{ note.content }}')
        expect(source).not.toContain('v-html')
    })
})
