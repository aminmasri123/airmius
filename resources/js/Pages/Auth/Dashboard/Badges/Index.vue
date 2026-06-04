<script setup>
import { reactive } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { confirmDialog } from '@/services/dialogService'

defineOptions({ layout: AppLayout })

const props = defineProps({
    badges: { type: Array, default: () => [] },
    actorTypes: { type: Array, default: () => ['sportler', 'trainer', 'verein', 'team'] },
    triggers: { type: Array, default: () => ['xp', 'level', 'streak', 'reason'] },
})

const createForm = useForm({
    key: '',
    name: '',
    description: '',
    icon: 'las la-medal',
    actor_type: 'sportler',
    trigger: 'xp',
    threshold: 0,
    reason: '',
    meta: {},
})

const editForms = reactive({})

const formFor = (badge) => {
    editForms[badge.id] ??= useForm({
        key: badge.key,
        name: badge.name,
        description: badge.description || '',
        icon: badge.icon || '',
        actor_type: badge.actor_type || 'sportler',
        trigger: badge.trigger || 'xp',
        threshold: badge.threshold || 0,
        reason: badge.meta?.reason || '',
        meta: badge.meta || {},
    })

    return editForms[badge.id]
}

const normalizeMeta = (form) => ({
    ...form.data(),
    meta: form.trigger === 'reason' ? { reason: form.reason } : {},
})

const firstError = (form) => Object.values(form.errors || {})[0] || null

const createBadge = () => {
    createForm.transform(() => normalizeMeta(createForm)).post(route('admin.badges.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    })
}

const updateBadge = (badge) => {
    const form = formFor(badge)
    form.transform(() => normalizeMeta(form)).put(route('admin.badges.update', badge.id), {
        preserveScroll: true,
    })
}

const deleteBadge = async (badge) => {
    const confirmed = await confirmDialog({
        title: 'Badge löschen',
        message: `Soll der Badge "${badge.name}" wirklich gelöscht werden?`,
        confirmLabel: 'Löschen',
        danger: true,
    })

    if (!confirmed) return

    router.delete(route('admin.badges.destroy', badge.id), { preserveScroll: true })
}
</script>

<template>
    <Head title="Badges verwalten" />

    <div class="space-y-5">
        <section class="surface-card p-5">
            <p class="text-sm font-semibold uppercase tracking-wide text-air-blue">Admin</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">Badges</h1>
            <p class="mt-2 text-sm text-secondary">
                Automatische Auszeichnungen für XP, Level, Streaks und konkrete Aktionen.
            </p>
        </section>

        <form class="surface-card grid gap-3 p-5 lg:grid-cols-[1fr_1fr_150px_150px_120px_auto]" @submit.prevent="createBadge">
            <div v-if="firstError(createForm)" class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error lg:col-span-6" role="alert">
                {{ firstError(createForm) }}
            </div>
            <input v-model="createForm.key" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="key, z.B. player_streak_30">
            <input v-model="createForm.name" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="Name">
            <select v-model="createForm.actor_type" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <option v-for="actor in actorTypes" :key="actor" :value="actor">{{ actor }}</option>
            </select>
            <select v-model="createForm.trigger" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <option v-for="trigger in triggers" :key="trigger" :value="trigger">{{ trigger }}</option>
            </select>
            <input v-model.number="createForm.threshold" type="number" min="0" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
            <button
                type="submit"
                class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50"
                :disabled="createForm.processing"
            >
                Erstellen
            </button>
            <input v-if="createForm.trigger === 'reason'" v-model="createForm.reason" class="lg:col-span-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" placeholder="reason, z.B. knowledge_marked_helpful">
            <textarea v-model="createForm.description" class="lg:col-span-6 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" rows="2" placeholder="Beschreibung"></textarea>
        </form>

        <section class="surface-card divide-y divide-border">
            <article v-for="badge in badges" :key="badge.id" class="grid gap-3 p-4 xl:grid-cols-[1fr_1fr_150px_150px_120px_auto]">
                <input v-model="formFor(badge).key" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <input v-model="formFor(badge).name" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <select v-model="formFor(badge).actor_type" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option v-for="actor in actorTypes" :key="actor" :value="actor">{{ actor }}</option>
                </select>
                <select v-model="formFor(badge).trigger" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                    <option v-for="trigger in triggers" :key="trigger" :value="trigger">{{ trigger }}</option>
                </select>
                <input v-model.number="formFor(badge).threshold" type="number" min="0" class="rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-buttonPrimary px-3 py-2 text-sm text-buttonTextPrimary disabled:opacity-50"
                        :disabled="formFor(badge).processing"
                        @click="updateBadge(badge)"
                    >
                        Speichern
                    </button>
                    <button type="button" class="rounded-lg border border-danger/40 px-3 py-2 text-sm text-danger" @click="deleteBadge(badge)">Löschen</button>
                </div>
                <div v-if="firstError(formFor(badge))" class="rounded-lg border border-error/30 bg-error/10 px-3 py-2 text-sm font-semibold text-error xl:col-span-6" role="alert">
                    {{ firstError(formFor(badge)) }}
                </div>
                <input v-if="formFor(badge).trigger === 'reason'" v-model="formFor(badge).reason" class="xl:col-span-2 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary">
                <textarea v-model="formFor(badge).description" class="xl:col-span-6 rounded-lg border border-border bg-inputBg px-3 py-2 text-sm text-primary" rows="2"></textarea>
            </article>
        </section>
    </div>
</template>


