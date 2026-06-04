<script setup>
import FeedComments from '@/Components/Auth/Feed/FeedComments.vue'
import FeedPostBody from '@/Components/Auth/Feed/FeedPostBody.vue'
import FeedPostEditForm from '@/Components/Auth/Feed/FeedPostEditForm.vue'
import FeedPostEngagementBar from '@/Components/Auth/Feed/FeedPostEngagementBar.vue'
import FeedPostHeader from '@/Components/Auth/Feed/FeedPostHeader.vue'
import { ref } from 'vue'

defineProps({
    post: { type: Object, required: true },
    user: { type: Object, default: null },
    editForm: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    visibilities: { type: Array, default: () => [] },
    postTypes: { type: Array, default: () => [] },
    clubs: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    sports: { type: Array, default: () => [] },
    initials: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    visibilityLabel: { type: Function, required: true },
    postTypeLabel: { type: Function, required: true },
    contentOriginLabel: { type: Function, required: true },
    sportLabel: { type: Function, required: true },
    skillsForSport: { type: Function, required: true },
    storageUrl: { type: Function, required: true },
    fileName: { type: Function, required: true },
    isImageMime: { type: Function, required: true },
    isVideoMime: { type: Function, required: true },
})

const emit = defineEmits([
    'delete-comment',
    'delete-post',
    'report-comment',
    'report-post',
    'toggle-helpful',
    'toggle-like',
    'update-post',
])

const commentsRef = ref(null)
</script>

<template>
    <article class="surface-card min-w-0 overflow-hidden">
        <FeedPostHeader
            :post="post"
            :user="user"
            :edit-form="editForm"
            :can-edit="canEdit"
            :can-delete="canDelete"
            :initials="initials"
            :format-date="formatDate"
            :visibility-label="visibilityLabel"
            @report-post="emit('report-post', post)"
            @delete-post="emit('delete-post', post)"
        />

        <FeedPostEditForm
            v-if="editForm.editing"
            :post="post"
            :edit-form="editForm"
            :visibilities="visibilities"
            :post-types="postTypes"
            :clubs="clubs"
            :teams="teams"
            :sports="sports"
            :visibility-label="visibilityLabel"
            :post-type-label="postTypeLabel"
            :skills-for-sport="skillsForSport"
            @update-post="emit('update-post', post)"
        />

        <FeedPostBody
            v-else
            :post="post"
            :user="user"
            :post-type-label="postTypeLabel"
            :content-origin-label="contentOriginLabel"
            :sport-label="sportLabel"
            :storage-url="storageUrl"
            :file-name="fileName"
            :is-image-mime="isImageMime"
            :is-video-mime="isVideoMime"
        />

        <FeedPostEngagementBar
            :post="post"
            @toggle-like="emit('toggle-like', post)"
            @toggle-helpful="emit('toggle-helpful', post)"
            @focus-comments="commentsRef?.focusComment()"
        />

        <FeedComments
            ref="commentsRef"
            :post="post"
            :user="user"
            @delete-comment="emit('delete-comment', $event)"
            @report-comment="emit('report-comment', $event)"
        />
    </article>
</template>
