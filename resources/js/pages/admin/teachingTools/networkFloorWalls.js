const snapDistance = 24
const outletEndClearance = 20
const roomsWithFoyerDoors = ['office-1', 'office-2', 'server']
export const channelStroke = 14

export function measureNetworkWalls(surface) {
    const plan = surface.parentElement
    const rooms = Array.from(plan.querySelectorAll('.network-floor__room'))
    const bounds = surface.getBoundingClientRect()
    if (rooms.length !== 4 || !bounds.width || !bounds.height) {
        return []
    }

    const relativeBounds = element => {
        const { left, right, top, bottom } = element.getBoundingClientRect()
        return { left: (left - bounds.left) / bounds.width, right: (right - bounds.left) / bounds.width,
            top: (top - bounds.top) / bounds.height, bottom: (bottom - bounds.top) / bounds.height }
    }
    const [officeOne, officeTwo, serverRoom, foyer] = rooms.map(relativeBounds)
    const [firstDoor, secondDoor, serverDoor, entry] = rooms.map(room => relativeBounds(room.querySelector('.network-floor__door')))
    const partitionBand = [officeOne.right, foyer.left]
    const officeBand = [officeOne.bottom, officeTwo.top]
    const serverBand = [officeTwo.bottom, serverRoom.top]
    const foyerEnds = [foyer.top, middle(officeBand), middle(serverBand), foyer.bottom]
    const offices = [officeOne, officeTwo, serverRoom]
    const doors = [firstDoor, secondDoor, serverDoor]

    return [
        createWall('north-office-1', 'horizontal', officeOne.top, officeOne.left, officeOne.right, 'north', 'office-1'),
        createWall('north-foyer', 'horizontal', foyer.top, foyer.left, foyer.right, 'north', 'foyer'),
        createWall('south-server', 'horizontal', serverRoom.bottom, serverRoom.left, serverRoom.right, 'south', 'server'),
        createWall('south-foyer-left', 'horizontal', foyer.bottom, foyer.left, entry.left, 'south', 'foyer'),
        createWall('south-foyer-right', 'horizontal', foyer.bottom, entry.right, foyer.right, 'south', 'foyer'),
        ...roomsWithFoyerDoors.map((room, index) => createWall(`west-${room}`, 'vertical', offices[index].left, offices[index].top,
            offices[index].bottom, 'west', room)),
        createWall('east-foyer', 'vertical', foyer.right, foyer.top, foyer.bottom, 'east', 'foyer'),
        ...roomsWithFoyerDoors.flatMap((room, index) => [
            createWall(`partition-${room}-top`, 'vertical', offices[index].right, offices[index].top,
                doors[index].top, 'east', room, partitionBand),
            createWall(`partition-${room}-bottom`, 'vertical', offices[index].right, doors[index].bottom,
                offices[index].bottom, 'east', room, partitionBand),
            createWall(`partition-${room}-top-foyer`, 'vertical', foyer.left, foyerEnds[index],
                doors[index].top, 'west', 'foyer', partitionBand),
            createWall(`partition-${room}-bottom-foyer`, 'vertical', foyer.left, doors[index].bottom,
                foyerEnds[index + 1], 'west', 'foyer', partitionBand),
        ]),
        createWall('offices-divider-office-1', 'horizontal', officeOne.bottom, officeOne.left, officeOne.right,
            'south', 'office-1', officeBand),
        createWall('offices-divider-office-2', 'horizontal', officeTwo.top, officeTwo.left, officeTwo.right,
            'north', 'office-2', officeBand),
        createWall('server-divider-office-2', 'horizontal', officeTwo.bottom, officeTwo.left, officeTwo.right,
            'south', 'office-2', serverBand),
        createWall('server-divider-server', 'horizontal', serverRoom.top, serverRoom.left, serverRoom.right,
            'north', 'server', serverBand),
    ].filter(wall => wall.end > wall.start)
}

function middle([start, end]) {
    return (start + end) / 2
}

function createWall(id, axis, line, start, end, side, roomId, band = null) {
    return { id, axis, line, start, end, side, roomId, band }
}

function isOnWallSide(wall, point) {
    if (!wall.band) return true

    const across = wall.axis === 'horizontal' ? point.y : point.x
    const center = middle(wall.band)
    return wall.line < center ? across < center : across > center
}

export function snapToNetworkWall(walls, bounds, point) {
    let closest = null
    let distance = snapDistance
    for (const wall of walls) {
        if (!isOnWallSide(wall, point)) continue

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
            const key = `${wall.roomId}:${x}:${y}`
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

export function channelTopology(channels, wallsById) {
    const channelsByWall = new Map(channels.filter(({ wallId }) => wallsById.has(wallId)).map(channel => [channel.wallId, channel.id]))
    const neighborsByChannel = new Map([...channelsByWall.values()].map(id => [id, []]))
    const endpointsByLocation = new Map()
    const bridges = []

    function connect(first, second) {
        neighborsByChannel.get(first.id).push(second)
        neighborsByChannel.get(second.id).push(first)
    }

    for (const [wallId, id] of channelsByWall) {
        const wall = wallsById.get(wallId)
        for (const position of [0, 1]) {
            const along = position === 0 ? wall.start : wall.end
            const [x, y] = wall.axis === 'horizontal' ? [along, wall.line] : [wall.line, along]
            const location = `${wall.roomId}:${x}:${y}`
            const endpoint = { id, position }
            const neighbors = endpointsByLocation.get(location) ?? []
            for (const neighbor of neighbors) connect(neighbor, endpoint)
            neighbors.push(endpoint)
            endpointsByLocation.set(location, neighbors)
        }
    }

    function bridgeChannelEnds(firstWallId, firstPosition, secondWallId, secondPosition) {
        const firstId = channelsByWall.get(firstWallId)
        const secondId = channelsByWall.get(secondWallId)
        if (firstId === undefined || secondId === undefined) return

        connect({ id: firstId, position: firstPosition }, { id: secondId, position: secondPosition })
        const firstWall = wallsById.get(firstWallId)
        const secondWall = wallsById.get(secondWallId)
        bridges.push({
            key: `${firstId}:${secondId}`,
            start: { ...outletMountPoint(firstWall, firstPosition), shift: channelCenterShift(firstWall) },
            end: { ...outletMountPoint(secondWall, secondPosition), shift: channelCenterShift(secondWall) },
        })
    }

    for (const room of roomsWithFoyerDoors) {
        const upper = `partition-${room}-top`
        const lower = `partition-${room}-bottom`
        bridgeChannelEnds(upper, 1, `${upper}-foyer`, 1)
        bridgeChannelEnds(lower, 0, `${lower}-foyer`, 0)
        bridgeChannelEnds(`${upper}-foyer`, 1, `${lower}-foyer`, 0)
    }

    const sharedWalls = [...wallsById.values()].filter(wall => wall.band)
    const wallsWithChannels = [...channelsByWall.keys()].map(id => wallsById.get(id))
    for (const first of wallsWithChannels) {
        for (const second of wallsWithChannels) {
            if (first.axis !== second.axis || first.line !== second.line || first.end >= second.start) continue
            const passesThroughWall = sharedWalls.some(({ axis, band, start, end }) =>
                first.axis !== axis && first.end === band[0] && second.start === band[1]
                && first.line >= start && first.line <= end)
            if (passesThroughWall) bridgeChannelEnds(first.id, 1, second.id, 0)
        }
    }

    return { neighborsByChannel, bridges }
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
        if (!wall || !isOnWallSide(wall, point)) continue

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

export function firstWallIntersection(walls, start, end) {
    let firstHit = null
    for (const wall of walls) {
        const [across, along] = wall.axis === 'horizontal' ? ['y', 'x'] : ['x', 'y']
        const band = wall.band ?? [wall.line, wall.line]
        const [acrossEntry, acrossExit] = segmentRange(start[across], end[across], ...band)
        const [alongEntry, alongExit] = segmentRange(start[along], end[along], wall.start, wall.end)
        const entry = Math.max(0, acrossEntry, alongEntry)
        const exit = Math.min(1, acrossExit, alongExit)
        if (entry <= exit && exit > 0 && entry < 1 && (firstHit === null || entry < firstHit)) firstHit = entry
    }
    return firstHit
}

function segmentRange(start, end, min, max) {
    if (start === end) return start >= min && start <= max ? [0, 1] : [1, 0]

    const entry = (min - start) / (end - start)
    const exit = (max - start) / (end - start)
    return [Math.min(entry, exit), Math.max(entry, exit)]
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
