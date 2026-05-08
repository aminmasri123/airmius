<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'

defineProps({
    email: {
        type: String,
        required: true,
    },
    children: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()

const formatDate = (value) => {
    if (!value) return '-'

    return new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value))
}

const revoke = (child) => {
    if (!confirm(`Zustimmung für ${child.name} wirklich widerrufen?`)) return

    router.put(route('guardian-access.children.revoke', child.id), {}, {
        preserveScroll: true,
    })
}

const approve = (child) => {
    if (!confirm(`Ablehnung für ${child.name} zurücknehmen und Zustimmung erteilen?`)) return

    router.put(route('guardian-access.children.approve', child.id), {}, {
        preserveScroll: true,
    })
}

const logout = () => {
    router.post(route('guardian-access.destroy'))
}
</script>

<template>
    <Head title="Elternbereich" />

    <main class="min-h-screen bg-bg text-primary">
        <div class="mx-auto w-full max-w-5xl px-4 py-8">
            <header class="flex flex-col gap-4 rounded-lg border border-border bg-card p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-16 w-16">
                        <AuthenticationCardLogo />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Airmius Elternbereich</p>
                        <h1 class="text-2xl font-semibold text-primary">Zustimmungen verwalten</h1>
                        <p class="mt-1 text-sm text-secondary">{{ email }}</p>
                    </div>
                </div>

                <button class="btn" @click="logout">Schließen</button>
            </header>

            <div v-if="page.props.flash?.success" class="mt-5 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-success">
                {{ page.props.flash.success }}
            </div>

            <section class="mt-5 rounded-lg border border-border bg-card p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Eigenes Elternkonto nutzen</h2>
                        <p class="mt-1 text-sm leading-6 text-secondary">
                            Du kannst optional ein normales Airmius-Konto mit dieser E-Mail erstellen.
                            Danach sind die Kinder dauerhaft mit deinem Elternkonto verknüpft.
                        </p>
                    </div>

                    <Link :href="route('guardian-access.account.create')" class="btn-primary text-center">
                        Elternkonto erstellen
                    </Link>
                </div>
            </section>

            <section class="mt-5 space-y-4">
                <article
                    v-for="child in children"
                    :key="child.id"
                    class="rounded-lg border border-border bg-card p-5"
                >
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary">{{ child.name }}</h2>
                            <p class="mt-1 text-sm text-secondary">
                                {{ child.email }} · {{ child.age }} Jahre · Geburtstag {{ formatDate(child.birth_date) }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span v-if="child.approved_at" class="rounded border border-success/30 bg-success/10 px-2 py-1 text-success">
                                    Zugestimmt am {{ formatDate(child.approved_at) }}
                                </span>
                                <span v-if="child.revoked_at" class="rounded border border-warning/30 bg-warning/10 px-2 py-1 text-warning">
                                    Widerrufen am {{ formatDate(child.revoked_at) }}
                                </span>
                                <span v-if="child.rejected_at" class="rounded border border-error/30 bg-error/10 px-2 py-1 text-error">
                                    Abgelehnt am {{ formatDate(child.rejected_at) }}
                                </span>
                                <span v-if="!child.approved_at && !child.revoked_at && !child.rejected_at" class="rounded border border-border bg-muted px-2 py-1 text-secondary">
                                    Zustimmung offen
                                </span>
                            </div>
                        </div>

                        <button
                            v-if="child.approved_at && !child.revoked_at"
                            type="button"
                            class="rounded-lg bg-error px-4 py-2 text-sm font-semibold text-white"
                            @click="revoke(child)"
                        >
                            Zustimmung widerrufen
                        </button>
                        <button
                            v-else-if="child.rejected_at"
                            type="button"
                            class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary"
                            @click="approve(child)"
                        >
                            Ablehnung zurücknehmen und zustimmen
                        </button>
                    </div>
                </article>

                <div v-if="children.length === 0" class="rounded-lg border border-border bg-card p-8 text-center text-secondary">
                    Zu dieser E-Mail wurden keine Kinder unter 16 Jahren gefunden.
                </div>
            </section>

            <div class="mt-6 text-sm text-secondary">
                <Link :href="route('welcome')" class="underline hover:text-primary">Zur Startseite</Link>
            </div>
        </div>
    </main>
</template>
