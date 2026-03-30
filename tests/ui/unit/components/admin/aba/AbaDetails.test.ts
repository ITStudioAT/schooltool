import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

describe('AbaDetails', () => {
    it('renders the basic information card and wires the extraction flow', () => {
        const source = readFileSync('resources/js/pages/admin/aba/AbaDetails.vue', 'utf8')

        expect(source).toContain('ABA Details')
        expect(source).toContain('Extraktion starten')
        expect(source).toContain('Dokumentinhalte aus dem Hauptdokument extrahieren')
        expect(source).toContain('Hauptdokument')
        expect(source).toContain('Weitere Dokumente')
        expect(source).toContain('section.key === \'title_page\' && section.title_page')
        expect(source).toContain('title-page-details__hero-label">Titel')
        expect(source).toContain('Verfasser')
        expect(source).toContain('Gefundene Bilder')
        expect(source).toContain('Seitenzahl')
        expect(source).toContain('Weitere Angaben vom Titelblatt')
        expect(source).toContain("section.preview_text && section.key !== 'title_page'")
        expect(source).not.toContain('Extrahierter Dokumentkopf')
        expect(source).toContain('await axios.get(`/api/admin/abas/${this.abaId}`)')
        expect(source).toContain("await axios.get(`/api/admin/abas/${this.abaId}/extraction`)")
        expect(source).toContain("await axios.post(`/api/admin/abas/${this.abaId}/extraction`)")
        expect(source).toContain('this.scheduleExtractionPoll()')
        expect(source).toContain('currentExtraction.status === \'completed\'')
        expect(source).toContain('Nicht zugeordnete Blöcke')
        expect(source).toContain("$router.push('/admin/aba')")
    })
})
