<script setup>
import { onMounted, ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import FeedComments from '@/Components/Auth/Feed/FeedComments.vue'
const props = defineProps({ postId: { type: Number, required: true } })
const post = ref(null)
const busy = ref(false)
const user = usePage().props.auth?.user
const load = async () => {
    try { post.value = (await window.axios.get(`/ads/posts/${props.postId}`)).data.data }
    catch { post.value = null }
}
const like = () => {
    if (busy.value) return
    busy.value = true
    router.post(route('auth.posts.like', props.postId), {}, { preserveScroll: true, onFinish: async () => { await load(); busy.value = false } })
}
onMounted(load)
</script>
<template>
    <article v-if="post" class="surface-card p-4">
        <p class="text-xs text-secondary">Gesponsert</p>
        <p class="mt-2 font-semibold">{{ post.user?.name }}</p>
        <p class="my-3 whitespace-pre-wrap">{{ post.content }}</p>
        <img v-if="post.image" :src="post.image" alt="" class="max-h-96 w-full object-contain" />
        <button :disabled="busy" class="my-3 rounded border border-border p-2" @click="like">{{ post.liked_by_me ? 'Gefällt mir nicht mehr' : 'Gefällt mir' }} · {{ post.likes_count }}</button>
        <FeedComments :post="post" :user="user" />
        <a :href="route('auth.posts.index', { post: post.id })" class="text-primary underline">Originalbeitrag öffnen</a>
    </article>
</template>
