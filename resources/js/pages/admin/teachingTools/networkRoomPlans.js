export const networkBuildPath = '/admin/teaching-tools/tests/networks/build-network'
export const networkRoomPlanNumbers = [1, 2, 3, 4, 5]

export function networkRoomPlanPath(planNumber) {
    return `${networkBuildPath}/room-plan/${planNumber}`
}
