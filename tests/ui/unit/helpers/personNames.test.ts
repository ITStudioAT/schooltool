import { expect, it } from 'vitest'
import { sortedPeople } from '@/helpers/personNames'

it('sorts every person list by surname then first name across classes without changing source order', () => {
    const people = [
        { id: 1, last_name: 'Zeller', first_name: 'Anna', schoolclass: '1A' },
        { id: 2, last_name: 'Berger', first_name: 'Zoe', schoolclass: '1A' },
        { id: 3, last_name: 'Berger', first_name: 'Anna', schoolclass: '3B' },
        { id: 4, last_name: 'Änder', first_name: 'Eva', schoolclass: '4A' },
    ]
    expect(sortedPeople(people).map(row => row.id)).toEqual([4, 3, 2, 1])
    expect(people.map(row => row.id)).toEqual([1, 2, 3, 4])
    for (const key of ['identity', 'person']) {
        const rows = people.map(person => ({ id: person.id, [key]: person }))
        expect(sortedPeople(rows).map(row => row.id)).toEqual([4, 3, 2, 1])
    }
})
