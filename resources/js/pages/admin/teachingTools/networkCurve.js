// Kurvenlabor v4: shape-preserving cubic Hermite interpolation (PCHIP).
// Each segment is a cubic Bezier; points must have strictly increasing X values.
function endSlope(interval, nextInterval, slope, nextSlope) {
    const estimate = ((2 * interval + nextInterval) * slope - interval * nextSlope) / (interval + nextInterval)
    if (Math.sign(estimate) !== Math.sign(slope)) {
        return 0
    }
    if (Math.sign(slope) !== Math.sign(nextSlope) && Math.abs(estimate) > 3 * Math.abs(slope)) {
        return 3 * slope
    }
    return estimate
}

export function curveSegments(points) {
    if (points.length < 2) {
        return []
    }
    const intervals = []
    const slopes = []
    for (let index = 0; index < points.length - 1; index++) {
        const interval = points[index + 1][0] - points[index][0]
        if (interval <= 0) {
            throw new Error('Curve points need strictly increasing X values.')
        }
        intervals.push(interval)
        slopes.push((points[index + 1][1] - points[index][1]) / interval)
    }

    const derivatives = new Array(points.length)
    if (points.length === 2) {
        derivatives.fill(slopes[0])
    } else {
        derivatives[0] = endSlope(intervals[0], intervals[1], slopes[0], slopes[1])
        for (let index = 1; index < points.length - 1; index++) {
            const before = slopes[index - 1]
            const after = slopes[index]
            if (before === 0 || after === 0 || Math.sign(before) !== Math.sign(after)) {
                derivatives[index] = 0
            } else {
                const firstWeight = 2 * intervals[index] + intervals[index - 1]
                const secondWeight = intervals[index] + 2 * intervals[index - 1]
                derivatives[index] = (firstWeight + secondWeight) / (firstWeight / before + secondWeight / after)
            }
        }
        const last = slopes.length - 1
        derivatives[points.length - 1] = endSlope(intervals[last], intervals[last - 1], slopes[last], slopes[last - 1])
    }

    return intervals.map((interval, index) => {
        const [startX, startY] = points[index]
        const [endX, endY] = points[index + 1]
        return {
            start: [startX, startY],
            control1: [startX + interval / 3, startY + derivatives[index] * interval / 3],
            control2: [endX - interval / 3, endY - derivatives[index + 1] * interval / 3],
            end: [endX, endY],
        }
    })
}
