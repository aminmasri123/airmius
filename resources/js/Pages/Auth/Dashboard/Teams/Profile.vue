<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import ClubWorkspaceNav from '@/Components/Auth/ClubWorkspaceNav.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
    teamProfile: Object,
    posts: { type: Array, default: () => [] },
    viewer: Object,
})

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const { t, te } = useI18n()
const sportLabel = (value) => {
    if (!value) return 'Team'

    const key = `sports.${value}`

    return te(key) ? t(key) : value
}
const requestJoin = () => router.post(route('auth.teams.join-requests.store', props.teamProfile.id), {}, { preserveScroll: true })
const logoInput = ref(null)
const coverInput = ref(null)
const imageForm = useForm({
    logo: null,
    cover_image: null,
})

const uploadImage = (field, event) => {
    const file = event.target.files?.[0] || null

    if (!file) {
        return
    }

    imageForm.logo = field === 'logo' ? file : null
    imageForm.cover_image = field === 'cover_image' ? file : null
    imageForm.post(route('auth.teams.images.update', props.teamProfile.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageForm.reset()
            if (logoInput.value) logoInput.value.value = null
            if (coverInput.value) coverInput.value.value = null
        },
    })
}
</script>

<template>
    <AppLayout :title="teamProfile.name">
        <Head :title="teamProfile.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <ClubWorkspaceNav
                active="structure"
                description="Teamprofil, Zugehörigkeit, Mitglieder und sichtbare Teambeiträge."
            />

            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
                    <img v-if="teamProfile.cover_image" :src="storageUrl(teamProfile.cover_image)" :alt="teamProfile.name" class="h-full w-full object-cover" />
                    <button
                        v-if="viewer.can_manage"
                        type="button"
                        class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                        @click="coverInput?.click()"
                    >
                        <i class="las la-camera"></i> Titelbild
                    </button>
                    <input ref="coverInput" type="file" accept="image/*" class="hidden" @change="uploadImage('cover_image', $event)" />
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-end gap-4">
                            <div class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                                <img v-if="teamProfile.logo" :src="storageUrl(teamProfile.logo)" :alt="teamProfile.name" class="h-full w-full object-cover" />
                                <span v-else>{{ initials(teamProfile.name) }}</span>
                                <button
                                    v-if="viewer.can_manage"
                                    type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                                    @click="logoInput?.click()"
                                >
                                    <i class="las la-camera text-xl"></i>
                                </button>
                                <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="uploadImage('logo', $event)" />
                            </div>
                            <div class="pb-1">
                                <h1 class="text-2xl font-bold text-primary">{{ teamProfile.name }}</h1>
                                <Link :href="route('auth.clubs.show', teamProfile.club.id)" class="text-sm text-secondary hover:text-primary">
                                    {{ teamProfile.club.name }}
                                </Link>
                                <p class="mt-1 text-sm text-secondary">{{ sportLabel(teamProfile.sport_type) }}</p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <Link :href="route('auth.teams.index')" class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                                Teams
                            </Link>
                            <button
                                v-if="!viewer.is_member && !viewer.has_pending_join_request"
                                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                                @click="requestJoin"
                            >
                                Beitritt anfragen
                            </button>
                            <span v-else-if="viewer.has_pending_join_request" class="rounded-lg border border-border px-4 py-2 text-sm text-secondary">
                                Anfrage gesendet
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.users_count }}</div>
                    <div class="text-sm text-secondary">Mitglieder</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.events_count }}</div>
                    <div class="text-sm text-secondary">Events</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ teamProfile.files_count }}</div>
                    <div class="text-sm text-secondary">Dateien</div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3">
                            <img :src="post.user.profile_photo_url" :alt="post.user.name" class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <Link :href="route('auth.users.show', post.user.id)" class="text-sm font-semibold text-primary hover:underline">
                                    {{ post.user.name }}
                                </Link>
                                <p class="text-xs text-secondary">{{ formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} Likes</span>
                            <span>{{ post.comments_count }} Kommentare</span>
                        </div>
                    </article>
                    <div v-if="!posts.length" class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
                        Noch keine sichtbaren Beiträge.
                    </div>
                </section>

                <aside class="rounded-lg border border-border bg-card p-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Mitglieder</h2>
                    <div class="mt-4 space-y-3">
                        <Link v-for="member in teamProfile.members" :key="member.id" :href="route('auth.users.show', member.id)" class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                            <img :src="member.profile_photo_url" :alt="member.name" class="h-9 w-9 rounded-full object-cover">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-primary">{{ member.name }}</p>
                                <p class="text-xs text-secondary">{{ member.pivot?.role || 'Mitglied' }}</p>
                            </div>
                        </Link>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
