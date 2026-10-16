import { entry } from '@/routes/number-systems'

export function numberSystemLoginReturn(query = {}) {
    const code = typeof query.number_systems_code === 'string' ? query.number_systems_code.trim().toUpperCase() : ''
    const team = typeof query.number_systems_team === 'string' ? query.number_systems_team.trim().toUpperCase() : ''
    if (!/^[A-F0-9]{10}$/.test(code)) { return '/student/overview' }
    return entry.url({}, { query: { code, ...(/^[A-F0-9]{8}$/.test(team) ? { team_code: team } : {}) } })
}
