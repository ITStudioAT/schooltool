export function gradingAdjustmentState(groups, parts) {
    const contexts = new Map()
    const add = (parent, child) => {
        if (!contexts.has(parent)) contexts.set(parent, [])
        contexts.get(parent).push(child)
    }
    for (const group of groups) add(group.parent_group_id ?? 'root', { key: `group:${group.id}`, name: group.name })
    for (const part of parts) {
        const parent = groups.find((group) => group.part_ids.includes(part.id))?.id ?? 'root'
        add(parent, { key: `part:${part.id}`, id: part.id, name: part.name, adjustment: part.points_assessment_mode === 'sign_adjust' })
    }
    const state = {}
    for (const [context, siblings] of contexts) {
        const adjustments = siblings.filter((child) => child.adjustment)
        for (const part of adjustments) {
            const target = siblings.length === 2 && adjustments.length === 1 ? siblings.find((child) => child !== part) : null
            const message = target ? '' : adjustments.length > 1
                ? 'Zwei Anpassungs-Benotungsteile können einander nicht als Ziel verwenden. Auf dieser Ebene braucht die Anpassung genau einen anderen Baustein ohne den Zweck „Bestehende Note anpassen“.'
                : '„Bestehende Note anpassen“ braucht auf derselben Ebene genau einen weiteren Baustein als Ziel: insgesamt genau zwei direkte Bausteine. Eine Untergruppe zählt als ein Baustein.'
            state[part.id] = { message, target: target?.name, context, siblingCount: siblings.length, adjustmentCount: adjustments.length,
                signature: JSON.stringify([context, siblings.map((child) => child.key).sort(), adjustments.map((child) => child.id).sort((a, b) => a - b)]) }
        }
    }
    return state
}

export function gradingAdjustmentChangeError(beforeGroups, beforeParts, afterGroups, afterParts) {
    const before = gradingAdjustmentState(beforeGroups, beforeParts)
    const after = gradingAdjustmentState(afterGroups, afterParts)
    return Object.entries(after).find(([id, issue]) => {
        if (!issue.message) return false
        const previous = before[id]
        if (previous?.signature === issue.signature) return false
        const improves = previous?.message && previous.context === issue.context
            && issue.siblingCount <= previous.siblingCount && issue.adjustmentCount <= previous.adjustmentCount
            && (issue.adjustmentCount < previous.adjustmentCount || Math.abs(issue.siblingCount - 2) < Math.abs(previous.siblingCount - 2))
        return !improves
    })?.[1].message ?? ''
}
