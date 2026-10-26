<template>
    <div class="network-device-photo" :class="{ 'network-device-photo--thumbnail': decorative, 'network-device-photo--multiple': selectedPhotos.length > 1 }" :aria-hidden="decorative ? 'true' : undefined">
        <details v-if="credits" class="network-device-photo__credits">
            <summary>Bildnachweise</summary>
            <ul>
                <li v-for="photo in creditPhotos" :key="photo.file">
                    <a :href="photo.source" target="_blank" rel="noopener noreferrer">{{ photo.alt }}: {{ photo.author }}</a>
                    · <a :href="photo.licenseUrl || photo.source" target="_blank" rel="noopener noreferrer">{{ photo.license }}</a><template v-if="!photo.original"> · verkleinert</template>
                </li>
            </ul>
        </details>
        <template v-else>
            <figure v-for="photo in selectedPhotos" :key="photo.file">
                <img :src="`/images/network-devices/${photo.file}`" :alt="decorative ? '' : photo.alt" loading="lazy" decoding="async" />
                <figcaption v-if="!decorative">
                    <a :href="`/images/network-devices/${photo.file}`" target="_blank" rel="noopener noreferrer">Foto vergrößern</a><br />
                    Foto: <a :href="photo.source" target="_blank" rel="noopener noreferrer">{{ photo.author }}</a>
                    · <a :href="photo.licenseUrl || photo.source" target="_blank" rel="noopener noreferrer">{{ photo.license }}</a><template v-if="!photo.original"> · verkleinert</template>
                </figcaption>
            </figure>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { networkDevicePhotos } from './networkDevicePhotos'

const props = defineProps({
    name: { type: String, default: '' },
    names: { type: Array, default: () => [] },
    decorative: { type: Boolean, default: false },
    credits: { type: Boolean, default: false },
})

const selectedPhotos = computed(() => networkDevicePhotos[props.name] || [])
const creditPhotos = computed(() => {
    const unique = new Map()
    props.names.flatMap(name => networkDevicePhotos[name] || []).forEach(photo => unique.set(photo.file, photo))
    return [...unique.values()]
})
</script>

<style scoped>
.network-device-photo { display: grid; gap: 6px; min-width: 0; }
.network-device-photo--multiple { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.network-device-photo figure { min-width: 0; margin: 0; }
.network-device-photo img { display: block; width: 100%; height: 100px; object-fit: contain; border-radius: 6px; background: #fff; }
.network-device-photo figcaption { margin-top: 4px; color: #526780; font-size: .65rem; line-height: 1.3; overflow-wrap: anywhere; }
.network-device-photo a { color: #285a92; }
.network-device-photo--thumbnail { gap: 2px; }
.network-device-photo--thumbnail img { height: 42px; border-radius: 4px; }
.network-device-photo__credits { margin-top: 8px; color: #526780; font-size: .75rem; }
.network-device-photo__credits summary { cursor: pointer; color: #285a92; }
.network-device-photo__credits ul { margin: 8px 0 0; padding-left: 18px; line-height: 1.5; }
</style>
