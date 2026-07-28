import {
    config,
    convertAutomaticTag,
    destroy,
    destroyAttachment,
    destroyAutomaticTag,
    index,
    recalculateAutomaticTags,
    retryProcessing,
    show,
    store,
    storeAttachments,
    update,
} from '@/actions/App/Http/Controllers/Admin/MaterialsV2/MaterialV2ItemController'
import {
    destroy as destroyCategory,
    store as storeCategory,
    update as updateCategory,
} from '@/actions/App/Http/Controllers/Admin/MaterialsV2/MaterialV2CategoryController'
import linkPreview from '@/actions/App/Http/Controllers/Admin/MaterialsV2/MaterialV2LinkPreviewController'

export const materialsV2Api = Object.freeze({
    config: () => config.url(),
    items: () => index.url(),
    item: (item) => show.url(item),
    storeItem: () => store.url(),
    updateItem: (item) => update.url(item),
    destroyItem: (item) => destroy.url(item),
    storeAttachments: (item) => storeAttachments.url(item),
    destroyAttachment: (attachment) => destroyAttachment.url(attachment),
    retryProcessing: (item) => retryProcessing.url(item),
    recalculateAutomaticTags: (item) => recalculateAutomaticTags.url(item),
    destroyAutomaticTag: (item) => destroyAutomaticTag.url(item),
    convertAutomaticTag: (item) => convertAutomaticTag.url(item),
    categories: () => storeCategory.url(),
    storeCategory: () => storeCategory.url(),
    updateCategory: () => updateCategory.url(),
    destroyCategory: () => destroyCategory.url(),
    linkPreview: () => linkPreview.url(),
})
