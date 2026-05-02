<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const maintenance = computed(() => props.settings.maintenance || {})

const form = useForm({
    maintenance_enabled: Boolean(maintenance.value.enabled),
    maintenance_title: maintenance.value.title || 'Airmius ist gerade im Wartemodus',
    maintenance_message: maintenance.value.message || 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.',
})

const save = () => {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Systemeinstellungen" />

    <div class="space-y-5">
        <section class="surface-card overflow-hidden">
            <div class="border-b border-border p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Admin</p>
                <h1 class="mt-1 text-2xl font-semibold text-primary">Systemeinstellungen</h1>
                <p class="mt-2 max-w-2xl text-sm text-secondary">
                    Steuere zentrale Plattformfunktionen, die sofort für alle Benutzer wirken.
                </p>
            </div>

            <div class="grid gap-0 lg:grid-cols-[1fr_22rem]">
                <form class="space-y-5 p-5" @submit.prevent="save">
                    <div class="rounded-lg border border-border bg-bg p-4">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Wartemodus</h2>
                                <p class="mt-1 text-sm text-secondary">
                                    Wenn aktiv, sehen alle nicht berechtigten Benutzer nur die Warteseite.
                                </p>
                            </div>

                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <span class="text-sm font-semibold text-secondary">
                                    {{ form.maintenance_enabled ? 'Aktiv' : 'Inaktiv' }}
                                </span>
                                <input v-model="form.maintenance_enabled" type="checkbox" class="peer sr-only">
                                <span
                                    class="relative h-7 w-12 rounded-full transition"
                                    :class="form.maintenance_enabled ? 'bg-buttonPrimary' : 'bg-muted'"
                                >
                                    <span
                                        class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="{ 'translate-x-5': form.maintenance_enabled }"
                                    ></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <div>
                            <label for="maintenance_title" class="text-sm font-semibold text-primary">Titel</label>
                            <input
                                id="maintenance_title"
                                v-model="form.maintenance_title"
                                type="text"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                maxlength="120"
                                required
                            >
                            <p v-if="form.errors.maintenance_title" class="mt-1 text-sm text-error">
                                {{ form.errors.maintenance_title }}
                            </p>
                        </div>

                        <div>
                            <label for="maintenance_message" class="text-sm font-semibold text-primary">Nachricht</label>
                            <textarea
                                id="maintenance_message"
                                v-model="form.maintenance_message"
                                rows="4"
                                class="mt-1 block w-full rounded-lg border-border bg-inputBg text-primary"
                                maxlength="500"
                                required
                            />
                            <p v-if="form.errors.maintenance_message" class="mt-1 text-sm text-error">
                                {{ form.errors.maintenance_message }}
                            </p>
                        </div>
                    </div>

                    <div v-if="page.props.flash?.success" class="rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
                        {{ page.props.flash.success }}
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            class="btn-primary"
                            :class="{ 'opacity-60': form.processing }"
                            :disabled="form.processing"
                        >
                            Speichern
                        </button>
                    </div>
                </form>

                <aside class="border-t border-border bg-bg p-5 lg:border-l lg:border-t-0">
                    <div class="rounded-lg border border-border bg-card p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Vorschau</p>
                        <div class="mt-4 rounded-lg border border-border bg-bg p-5">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                                <i class="las la-tools text-2xl"></i>
                            </div>
                            <h3 class="mt-4 text-lg font-semibold text-primary">
                                {{ form.maintenance_title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-secondary">
                                {{ form.maintenance_message }}
                            </p>
                            <div class="mt-5 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full w-2/3 rounded-full bg-buttonPrimary"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 rounded-lg border border-border bg-card p-4 text-sm text-secondary">
                        Benutzer mit der Berechtigung <span class="font-semibold text-primary">system.manage</span>
                        können Airmius weiterhin normal verwenden.
                    </div>
                </aside>
            </div>
        </section>
    </div>
</template>
