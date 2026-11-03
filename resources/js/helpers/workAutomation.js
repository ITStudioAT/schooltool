export function workAutomationEnabled(work) {
    return typeof work?.automation_enabled === 'boolean'
        ? work.automation_enabled
        : work?.status?.automation_enabled !== false
}
