<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'

defineProps({
    title: {
        type: String,
        default: 'Airmius ist gerade im Wartemodus',
    },
    message: {
        type: String,
        default: 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.',
    },
    canLogin: {
        type: Boolean,
        default: true,
    },
})

const page = usePage()
</script>

<template>
    <Head :title="title" />

    <main class="min-h-screen bg-bg text-primary">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-5 py-10">
            <section class="grid w-full overflow-hidden rounded-lg border border-border bg-card shadow-xl md:grid-cols-[1fr_24rem]">
                <div class="p-6 sm:p-10">
                    <div class="h-24 w-24">
                        <AuthenticationCardLogo />
                    </div>

                    <div class="mt-10 max-w-2xl">
                        <p class="text-sm font-semibold uppercase tracking-wide text-secondary">Wartemodus</p>
                        <h1 class="mt-3 text-3xl font-semibold text-primary sm:text-5xl">
                            {{ title }}
                        </h1>
                        <p class="mt-5 text-base leading-7 text-secondary sm:text-lg">
                            {{ message }}
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="btn-primary"
                        >
                            Für Admins anmelden
                        </Link>

                        <Link
                            v-else-if="page.props.auth?.user"
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="btn"
                        >
                            Abmelden
                        </Link>
                    </div>
                </div>

                <aside class="border-t border-border bg-bg p-6 md:border-l md:border-t-0">
                    <div class="flex h-full flex-col justify-between gap-8">
                        <div class="space-y-4">
                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-buttonPrimary text-buttonTextPrimary">
                                        <i class="las la-wrench text-xl"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-primary">Plattform wird aktualisiert</p>
                                        <p class="text-sm text-secondary">Bitte später erneut versuchen.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-border bg-card p-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-primary">
                                        <i class="las la-shield-alt text-xl"></i>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-primary">Daten bleiben geschützt</p>
                                        <p class="text-sm text-secondary">Der Zugriff ist vorübergehend eingeschränkt.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-card p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">Status</p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full w-3/4 rounded-full bg-buttonPrimary"></div>
                            </div>
                            <p class="mt-3 text-sm text-secondary">
                                Airmius kommt gleich wieder zurück.
                            </p>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </main>
</template>
