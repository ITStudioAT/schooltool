import { useForm } from 'laravel-precognition-vue'
import { materialsV2Api } from './api'

const initialData = {
    title: '',
    category: '',
    cluster_name: null,
    description: null,
    reminder_date: null,
    reminder_time: null,
    link_url: null,
    user_keywords: [],
    attachments: [],
}

export function useMaterialV2Precognition({ dialog, source }) {
    const form = useForm(
        () => (dialog.mode === 'edit' ? 'put' : 'post'),
        () => (
            dialog.mode === 'edit' && dialog.item
                ? materialsV2Api.updateItem(dialog.item)
                : materialsV2Api.storeItem()
        ),
        initialData,
    ).setValidationTimeout(350)

    function sync() {
        form.setData({
            title: String(source.title || '').trim(),
            category: String(source.category || '').trim() || null,
            cluster_name: String(source.clusterName || '').trim() || null,
            description: String(source.description || '').trim() || null,
            reminder_date: String(source.reminderDate || '').trim() || null,
            reminder_time: String(source.reminderTime || '').trim() || null,
            link_url: String(source.linkUrl || '').trim() || null,
            user_keywords: parseKeywords(source.keywords),
            attachments: normalizeFiles(source.attachments),
        })
    }

    function validate(field) {
        sync()
        form.validate(field)
    }

    function errorMessages(field, fallback = []) {
        if (Array.isArray(fallback) && fallback.length > 0) {
            return fallback
        }

        const message = form.errors[field]

        return message ? [message] : []
    }

    function reset() {
        form.reset()
    }

    return {
        form,
        validate,
        errorMessages,
        reset,
    }
}

function normalizeFiles(files) {
    if (Array.isArray(files)) {
        return files
    }

    if (typeof FileList !== 'undefined' && files instanceof FileList) {
        return Array.from(files)
    }

    return files ? [files] : []
}

function parseKeywords(value) {
    return String(value || '')
        .split(/[,;\n]+/u)
        .map((keyword) => keyword.trim())
        .filter(Boolean)
}
