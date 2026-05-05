<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    clubProfile: Object,
    clubRoles: { type: Array, default: () => ['owner', 'admin', 'manager', 'member'] },
    posts: { type: Array, default: () => [] },
    viewer: Object,
})

const initials = (name) => (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()
const formatDate = (value) => new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
const page = usePage()
const storageUrl = (path) => path?.startsWith('http') ? path : `${page.props.uploads?.url || '/storage'}/${path}`
const clubRoleLabel = (role) => ({
    owner: 'Owner',
    admin: 'Verein-Admin',
    manager: 'Manager',
    member: 'Mitglied',
}[role] || role)
const logoInput = ref(null)
const coverInput = ref(null)
const imageForm = useForm({
    logo: null,
    cover_image: null,
})

const clubForm = useForm({
    name: props.clubProfile.name || '',
    sport_type: props.clubProfile.sport_type || '',
    country: props.clubProfile.country || 'DE',
    street: props.clubProfile.street || '',
    house_number: props.clubProfile.house_number || '',
    postal_code: props.clubProfile.postal_code || '',
    city: props.clubProfile.city || '',
    state: props.clubProfile.state || '',
})

const uploadImage = (field, event) => {
    const file = event.target.files?.[0] || null

    if (!file) {
        return
    }

    imageForm.logo = field === 'logo' ? file : null
    imageForm.cover_image = field === 'cover_image' ? file : null
    imageForm.post(route('auth.clubs.images.update', props.clubProfile.id), {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            imageForm.reset()
            if (logoInput.value) logoInput.value.value = null
            if (coverInput.value) coverInput.value.value = null
        },
    })
}

const updateMemberRole = (member) => {
    router.put(route('auth.clubs.members.update', [props.clubProfile.id, member.id]), {
        role: member.pivot.role,
    }, {
        preserveScroll: true,
    })
}

const updateClubProfile = () => {
    clubForm.put(route('auth.clubs.update', props.clubProfile.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AppLayout :title="clubProfile.name">

        <Head :title="clubProfile.name" />

        <div class="mx-auto max-w-5xl space-y-6">
            <section class="overflow-hidden rounded-lg border border-border bg-card">
                <div class="relative h-40 bg-gradient-to-r from-buttonPrimary to-borderHover">
                    <img v-if="clubProfile.cover_image" :src="storageUrl(clubProfile.cover_image)"
                        :alt="clubProfile.name" class="h-full w-full object-cover" />
                    <button v-if="viewer.can_manage" type="button"
                        class="absolute bottom-3 right-3 rounded-lg bg-card/90 px-3 py-2 text-sm font-semibold text-primary shadow hover:bg-card"
                        @click="coverInput?.click()">
                        <i class="las la-camera"></i> Titelbild
                    </button>
                    <input ref="coverInput" type="file" accept="image/*" class="hidden"
                        @change="uploadImage('cover_image', $event)" />
                </div>
                <div class="px-5 pb-5">
                    <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="flex items-end gap-4">
                            <div
                                class="group relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-lg border-4 border-card bg-inputBg text-3xl font-bold text-primary">
                                <img v-if="clubProfile.logo" :src="storageUrl(clubProfile.logo)" :alt="clubProfile.name"
                                    class="h-full w-full object-cover" />
                                <span v-else>{{ initials(clubProfile.name) }}</span>
                                <button v-if="viewer.can_manage" type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100"
                                    @click="logoInput?.click()">
                                    <i class="las la-camera text-xl"></i>
                                </button>
                                <input ref="logoInput" type="file" accept="image/*" class="hidden"
                                    @change="uploadImage('logo', $event)" />
                            </div>
                            <div class="pb-1">
                                <h1 class="text-2xl font-bold text-primary">{{ clubProfile.name }}</h1>
                                <p class="text-sm text-secondary">Verein · {{ viewer.is_member ? 'Mitglied' : 'Profil'
                                    }}</p>
                            </div>
                        </div>

                        <Link :href="route('auth.teams.index')"
                            class="rounded-lg border border-border px-4 py-2 text-sm text-primary hover:bg-inputBg">
                            Teams ansehen
                        </Link>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.users_count }}</div>
                    <div class="text-sm text-secondary">Mitglieder</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.teams_count }}</div>
                    <div class="text-sm text-secondary">Teams</div>
                </div>
                <div class="rounded-lg border border-border bg-card p-4">
                    <div class="text-2xl font-semibold text-primary">{{ clubProfile.posts_count }}</div>
                    <div class="text-sm text-secondary">Beiträge</div>
                </div>
            </section>

            <section v-if="viewer.can_manage" class="rounded-lg border border-border bg-card p-5">
                <h2 class="text-lg font-semibold text-primary">Vereinsdaten</h2>
                <p class="mt-1 text-sm text-secondary">
                    Offizielle Vereine müssen ihre Vereinsnummer hinterlegen. Nicht-offizielle Gruppen können das Feld leer lassen.
                </p>

                <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="updateClubProfile">
                    <div class="md:col-span-2 rounded-lg border border-border bg-bg p-4 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-primary">Pruefstatus:</span>
                            <span class="rounded-full bg-inputBg px-2 py-1 text-xs font-semibold text-secondary">
                                {{ clubProfile.verification_status === 'pending_verification' ? 'Wartet auf Pruefung' : clubProfile.verification_status === 'verified' ? 'Freigegeben' : clubProfile.verification_status === 'rejected' ? 'Abgelehnt' : clubProfile.verification_status }}
                            </span>
                            <span v-if="clubProfile.is_official" class="rounded-full bg-success/10 px-2 py-1 text-xs font-semibold text-success">
                                Offiziell
                            </span>
                        </div>
                        <p v-if="clubProfile.official_club_number" class="mt-2 text-secondary">
                            Vereinsnummer: {{ clubProfile.official_club_number }}
                        </p>
                        <p v-else-if="clubProfile.requested_official_club_number" class="mt-2 text-secondary">
                            Beantragte Vereinsnummer: {{ clubProfile.requested_official_club_number }}
                        </p>
                        <p v-if="clubProfile.verification_notes" class="mt-2 text-secondary">
                            Hinweis: {{ clubProfile.verification_notes }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Vereinsname</label>
                        <input v-model="clubForm.name" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Sportart</label>
                        <input v-model="clubForm.sport_type" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <label v-if="false" class="flex items-start gap-3 rounded-lg border border-border bg-bg p-3 text-sm text-primary md:col-span-2">
                        <input v-model="clubForm.is_official" type="checkbox" class="mt-1 rounded border-border bg-inputBg">
                        <span>
                            <span class="block font-semibold">Offizieller Verein</span>
                            <span class="block text-secondary">Aktivieren, wenn der Verein offiziell registriert oder einem Verband zugeordnet ist.</span>
                        </span>
                    </label>

                    <div v-if="false" class="md:col-span-2">
                        <label class="text-sm font-semibold text-primary">Vereinsnummer</label>
                        <input
                            v-model="clubForm.official_club_number"
                            class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary"
                            :required="clubForm.is_official"
                            placeholder="z. B. Vereinsregister- oder Verbandsnummer"
                        >
                        <p class="mt-1 text-xs text-secondary">
                            Pflichtfeld für offizielle Vereine.
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Land</label>
                        <input v-model="clubForm.country" maxlength="2" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm uppercase text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Stadt</label>
                        <input v-model="clubForm.city" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">PLZ</label>
                        <input v-model="clubForm.postal_code" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Region</label>
                        <input v-model="clubForm.state" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Strasse</label>
                        <input v-model="clubForm.street" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-primary">Hausnummer</label>
                        <input v-model="clubForm.house_number" class="mt-1 w-full rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    </div>

                    <div class="md:col-span-2">
                        <button class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary" :disabled="clubForm.processing">
                            Vereinsdaten speichern
                        </button>
                    </div>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
                <section class="space-y-4">
                    <article v-for="post in posts" :key="post.id" class="rounded-lg border border-border bg-card p-4">
                        <div class="flex items-center gap-3">
                            <img :src="post.user.profile_photo_url" :alt="post.user.name"
                                class="h-10 w-10 rounded-full object-cover">
                            <div>
                                <Link :href="route('auth.users.show', post.user.id)"
                                    class="text-sm font-semibold text-primary hover:underline">
                                    {{ post.user.name }}
                                </Link>
                                <p class="text-xs text-secondary">{{ post.team?.name || clubProfile.name }} · {{
                                    formatDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-primary">{{ post.content }}</p>
                        <div class="mt-3 flex gap-4 text-xs text-secondary">
                            <span>{{ post.likes_count }} Likes</span>
                            <span>{{ post.comments_count }} Kommentare</span>
                        </div>
                    </article>
                    <div v-if="!posts.length"
                        class="rounded-lg border border-border bg-card p-8 text-center text-sm text-secondary">
                        Noch keine sichtbaren Beiträge.
                    </div>
                </section>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Teams</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="team in clubProfile.teams" :key="team.id"
                                :href="route('auth.teams.show', team.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded bg-buttonPrimary text-sm font-semibold text-buttonTextPrimary">
                                    {{ initials(team.name) }}</div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-primary">{{ team.name }}</p>
                                    <p class="text-xs text-secondary">{{ team.users_count }} Mitglieder</p>
                                </div>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Admins</h2>
                        <div class="mt-4 space-y-2">
                            <Link v-for="admin in clubProfile.admins" :key="admin.id"
                                :href="route('auth.users.show', admin.id)"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <img v-if="admin.profile_photo_thumb" :src="admin.profile_photo_thumb" :alt="admin.name"
                                    class="h-8 w-8 rounded-full object-cover" />
                                <div v-else
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                    {{ initials(admin?.name) }}
                                </div>
                                <span class="min-w-0 truncate text-sm font-medium text-primary">{{ admin.name }}</span>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-lg border border-border bg-card p-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-secondary">Mitglieder</h2>
                        <div class="mt-4 space-y-2">
                            <div v-for="member in clubProfile.members" :key="member.id"
                                class="flex items-center gap-3 rounded-lg p-2 hover:bg-inputBg">
                                <div class="min-w-0 flex-1">
                                    <Link :href="route('auth.users.show', member.id)"
                                        class="block truncate text-sm font-medium text-primary hover:underline">
                                        <img v-if="member.profile_photo_thumb" :src="member.profile_photo_thumb"
                                            :alt="member.name" class="h-8 w-8 rounded-full object-cover" />
                                        <div v-else
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-buttonPrimary text-xs font-semibold text-buttonTextPrimary">
                                            {{ initials(member?.name) }}
                                        </div>
                                        {{ member.name }}
                                    </Link>
                                    <p class="text-xs text-secondary">{{ clubRoleLabel(member.pivot.role) }}</p>
                                </div>
                                <select v-if="viewer.can_manage" v-model="member.pivot.role"
                                    class="max-w-28 rounded border border-border bg-inputBg px-2 py-1 text-xs text-primary"
                                    @change="updateMemberRole(member)">
                                    <option v-for="role in clubRoles" :key="role" :value="role">
                                        {{ clubRoleLabel(role) }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
