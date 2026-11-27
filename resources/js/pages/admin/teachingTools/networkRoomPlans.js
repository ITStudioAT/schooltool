export const networkBuildPath = '/admin/teaching-tools/tests/networks/build-network'
export const networkDeviceQuestionsPath = '/admin/teaching-tools/tests/networks/device-questions'
export const networkAdditionalQuestionsPath = '/admin/teaching-tools/tests/networks/additional-questions'
export const networkRoomPlanNumbers = [1]

export function networkRoomPlanPath(planNumber) {
    return `${networkBuildPath}/room-plan/${planNumber}`
}
