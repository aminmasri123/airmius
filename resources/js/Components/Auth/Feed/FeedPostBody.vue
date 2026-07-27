<script setup>
defineProps({
    post: { type: Object, required: true },
    user: { type: Object, default: null },
    postTypeLabel: { type: Function, required: true },
    contentOriginLabel: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    storageUrl: { type: Function, required: true },
    fileName: { type: Function, required: true },
    isImageMime: { type: Function, required: true },
    isVideoMime: { type: Function, required: true },
})
</script>

<template>
    <div class="px-4 pb-4">
        <div class="mb-3 flex flex-wrap gap-2 text-xs">
            <span class="rounded-full bg-inputBg px-3 py-1 font-semibold text-secondary">
                {{ postTypeLabel(post.post_type) }}
            </span>

            <span
                class="rounded-full border px-3 py-1 font-semibold"
                :class="post.content_origin === 'ai'
                    ? 'border-air-blue/40 bg-air-blue/10 text-air-blue'
                    : 'border-border text-secondary'"
            >
                {{ contentOriginLabel(post.content_origin) }}
            </span>

            <span
                v-if="post.user_id === user?.id && post.moderation_status && post.moderation_status !== 'approved'"
                class="rounded-full border border-border bg-inputBg px-3 py-1 font-semibold text-secondary"
            >
                In Prüfung
            </span>

            <span
                v-if="post.sport"
                class="rounded-full bg-inputBg px-3 py-1 text-secondary"
            >
                {{ sportLabel(post.sport) }}
            </span>

            <span
                v-for="skill in post.sport_skills"
                :key="skill.id"
                class="rounded-full border border-border px-3 py-1 text-secondary"
            >
                {{ skill.name }}
            </span>
        </div>

        <p
            v-if="post.content"
            class="whitespace-pre-line break-words text-sm leading-6 text-primary"
        >
            {{ post.content }}
        </p>

        <img
            v-if="post.image"
            :src="storageUrl(post.image)"
            alt=""
            width="1200"
            height="900"
            loading="lazy"
            decoding="async"
            class="mt-4 max-h-[70vh] w-full rounded-xl border border-border object-cover"
        >

        <div v-if="post.attachments?.length" class="mt-4 space-y-3">
            <div
                v-for="attachment in post.attachments"
                :key="attachment.id"
            >
                <video
                    v-if="attachment.file && isVideoMime(attachment.file.type)"
                    :src="storageUrl(attachment.file.path)"
                    :poster="attachment.file.thumbnail_url || (attachment.file.thumbnail_path ? storageUrl(attachment.file.thumbnail_path) : null)"
                    controls
                    preload="metadata"
                    class="max-h-[70vh] w-full rounded-xl border border-border bg-black"
                ></video>

                <img
                    v-else-if="attachment.file && isImageMime(attachment.file.type)"
                    :src="storageUrl(attachment.file.path)"
                    :alt="fileName(attachment.file)"
                    width="1200"
                    height="900"
                    loading="lazy"
                    decoding="async"
                    class="max-h-[70vh] w-full rounded-xl border border-border object-cover"
                >

                <a
                    v-else-if="attachment.file"
                    :href="storageUrl(attachment.file.path)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex items-center gap-2 rounded-lg border border-border bg-inputBg px-3 py-3 text-sm text-primary"
                >
                    <i class="las la-paperclip"></i>

                    <span class="min-w-0 flex-1 truncate">
                        {{ fileName(attachment.file) }}
                    </span>

                    <span class="hidden text-xs text-secondary sm:inline">
                        {{ attachment.file.type }}
                    </span>
                </a>
            </div>
        </div>
    </div>
</template>
