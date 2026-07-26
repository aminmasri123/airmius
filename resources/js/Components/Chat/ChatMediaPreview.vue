<script setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const tx = (key, params = {}) => t(key, params)

defineProps({
    activeMediaAttachment: { type: Object, default: null },
    galleryAttachments: { type: Array, default: () => [] },
    closeMediaPreview: { type: Function, required: true },
    showPreviousMedia: { type: Function, required: true },
    showNextMedia: { type: Function, required: true },
    fileUrl: { type: Function, required: true },
    fileDownloadUrl: { type: Function, required: true },
    attachmentLabel: { type: Function, required: true },
    trackDownload: { type: Function, required: true },
})
</script>

<template>
    <div
        v-if="activeMediaAttachment"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/90 p-3"
        @click.self="closeMediaPreview"
    >
        <button
            type="button"
            class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
            :title="tx('chat.ui.close')"
            :aria-label="tx('chat.ui.close')"
            @click="closeMediaPreview"
        >
            <i class="las la-times text-2xl"></i>
        </button>
        <button
            v-if="galleryAttachments.length > 1"
            type="button"
            class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
            :title="tx('chat.ui.previous_image')"
            :aria-label="tx('chat.ui.previous_image')"
            @click="showPreviousMedia"
        >
            <i class="las la-angle-left text-2xl"></i>
        </button>
        <img
            :src="fileUrl(activeMediaAttachment.file)"
            :alt="attachmentLabel(activeMediaAttachment)"
            width="1200"
            height="900"
            loading="eager"
            decoding="async"
            class="max-h-[82dvh] max-w-full rounded-lg object-contain"
        />
        <button
            v-if="galleryAttachments.length > 1"
            type="button"
            class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
            :title="tx('chat.ui.next_image')"
            :aria-label="tx('chat.ui.next_image')"
            @click="showNextMedia"
        >
            <i class="las la-angle-right text-2xl"></i>
        </button>
        <a
            :href="fileDownloadUrl(activeMediaAttachment.file)"
            class="absolute bottom-4 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-black"
            @click="trackDownload(activeMediaAttachment.file)"
        >
            {{ tx('chat.ui.download') }}
        </a>
    </div>
</template>
