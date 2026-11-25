const snapDistance = 24
const outletEndClearance = 20
export const channelStroke = 14

export function measureNetworkWalls(surface) {
    const plan = surface.parentElement
    const rooms = Array.from(plan.querySelectorAll('.network-floor__room'))
    const bounds = surface.getBoundingClientRect()
    if (rooms.length !== 4 || !bounds.width || !bounds.height) {
        return []
    }

    const [officeOne, officeTwo, serverRoom, foyer] = rooms.map(room => room.getBoundingClientRect())
    const doorBounds = rooms.map(room => room.querySelector('.network-floor__door').getBoundingClientRect())
    const partition = ((officeOne.right + foyer.left) / 2 - bounds.left) / bounds.width
    const horizontalBand = (upper, lower) => [upper.bottom, lower.top].map(y => (y - bounds.top) / bounds.height)
    const partitionBand = [officeOne.right, foyer.left].map(x => (x - bounds.left) / bounds.width)
    const officeBand = horizontalBand(officeOne, officeTwo)
    const serverBand = horizontalBand(officeTwo, serverRoom)
    const middle = ([start, end]) => (start + end) / 2
    const roomEnds = [0, middle(officeBand), middle(serverBand), 1]
    const roomIds = ['office-1', 'office-2', 'server']
    const openings = doorBounds.slice(0, 3).map(door => [(door.top - bounds.top) / bounds.height,
        (door.bottom - bounds.top) / bounds.height])
    const entry = doorBounds[3]
    const [entryStart, entryEnd] = [entry.left, entry.right].map(x => (x - bounds.left) / bounds.width)

    return [
        createWall('north-office-1', 'horizontal', 0, 0, partition, 'north'),
        createWall('north-foyer', 'horizontal', 0, partition, 1, 'north'),
        createWall('south-server', 'horizontal', 1, 0, partition, 'south'),
        createWall('south-foyer-left', 'horizontal', 1, partition, entryStart, 'south'),
        createWall('south-foyer-right', 'horizontal', 1, entryEnd, 1, 'south'),
        ...roomIds.map((room, index) => createWall(`west-${room}`, 'vertical', 0, roomEnds[index],
            roomEnds[index + 1], 'west')),
        createWall('east-foyer', 'vertical', 1, 0, 1, 'east'),
        ...roomIds.flatMap((room, index) => [
            createWall(`partition-${room}-top`, 'vertical', partition, roomEnds[index], openings[index][0],
                'partition', partitionBand),
            createWall(`partition-${room}-bottom`, 'vertical', partition, openings[index][1], roomEnds[index + 1],
                'partition', partitionBand),
        ]),
        createWall('offices-divider', 'horizontal', middle(officeBand), 0, partition, 'divider', officeBand),
        createWall('server-divider', 'horizontal', middle(serverBand), 0, partition, 'divider', serverBand),
    ].filter(wall => wall.end > wall.start)
}

function createWall(id, axis, line, start, end, side, band = null) {
    return { id, axis, line, start, end, side, band }
}

export function snapToNetworkWall(walls, bounds, point) {
    let closest = null
    let distance = snapDistance
    for (const wall of walls) {
        const along = pointAlongWall(wall, point)
        const candidate = wall.axis === 'horizontal' ? { x: along, y: wall.line } : { x: wall.line, y: along }
        const offset = Math.hypot((candidate.x - point.x) * bounds.width, (candidate.y - point.y) * bounds.height)
        if (offset <= distance) {
            closest = { ...candidate, wallId: wall.id }
            distance = offset
        }
    }
    return closest
}

export function pointAlongWall(wall, point) {
    const along = wall.axis === 'horizontal' ? point.x : point.y
    return Math.max(wall.start, Math.min(wall.end, along))
}

export function channelCenterShift(wall) {
    return { x: Number(wall.side === 'west') - Number(wall.side === 'east'),
        y: Number(wall.side === 'north') - Number(wall.side === 'south') }
}

export function channelCorners(walls, previewWallId = null) {
    const ends = new Map()
    for (const wall of walls) {
        const horizontal = wall.axis === 'horizontal'
        const shift = channelCenterShift(wall)
        const directions = horizontal ? ['right', 'left'] : ['down', 'up']

        for (const [index, along] of [wall.start, wall.end].entries()) {
            const [x, y] = horizontal ? [along, wall.line] : [wall.line, along]
            const key = `${x}:${y}`
            if (!ends.has(key)) ends.set(key, { key, x, y, shift: { x: 0, y: 0 }, arms: [], preview: false })
            const end = ends.get(key)
            if (horizontal) end.shift.y = shift.y
            else end.shift.x = shift.x
            end.arms.push(directions[index])
            end.preview ||= wall.id === previewWallId
        }
    }

    return [...ends.values()].filter(end => (!previewWallId || end.preview)
        && (end.arms.includes('left') || end.arms.includes('right')) && (end.arms.includes('up') || end.arms.includes('down')))
}

export function outletMountRange(wall, bounds) {
    const span = wall.axis === 'horizontal' ? bounds.width : bounds.height
    const margin = outletEndClearance / span
    const start = wall.start + margin
    const end = wall.end - margin
    return end > start ? { start, end } : null
}

export function outletMountPoint(wall, ratio) {
    const along = wall.start + ratio * (wall.end - wall.start)
    return wall.axis === 'horizontal' ? { x: along, y: wall.line } : { x: wall.line, y: along }
}

export function snapToChannel(channels, wallsById, bounds, point) {
    let closest = null
    let distance = snapDistance
    for (const channel of channels) {
        const wall = wallsById.get(channel.wallId)
        if (!wall) continue

        const range = outletMountRange(wall, bounds)
        const horizontal = wall.axis === 'horizontal'
        const along = horizontal ? point.x : point.y
        if (!range || along < range.start || along > range.end) continue

        const shift = channelCenterShift(wall)
        const offset = horizontal ? Math.abs((point.y - wall.line) * bounds.height - shift.y * channelStroke / 2)
            : Math.abs((point.x - wall.line) * bounds.width - shift.x * channelStroke / 2)
        if (offset < distance) {
            closest = { channelId: channel.id, channelPosition: (along - wall.start) / (wall.end - wall.start) }
            distance = offset
        }
    }

    return closest
}

export function furnitureFits(walls, bounds, point, size) {
    const halfWidth = size.width / (2 * bounds.width)
    const halfHeight = size.height / (2 * bounds.height)
    const left = point.x - halfWidth
    const right = point.x + halfWidth
    const top = point.y - halfHeight
    const bottom = point.y + halfHeight
    if (left < 0 || right > 1 || top < 0 || bottom > 1) {
        return false
    }

    return walls.every(wall => {
        if (!wall.band) {
            return true
        }

        const horizontal = wall.axis === 'horizontal'
        const [start, end] = wall.band
        const crossesBand = horizontal ? top < end && bottom > start : left < end && right > start
        if (!crossesBand) {
            return true
        }

        return horizontal
            ? right <= wall.start || left >= wall.end
            : bottom <= wall.start || top >= wall.end
    })
}
