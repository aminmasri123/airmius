<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'

defineProps({
    reason: { type: String, default: null },
    suspended_until: { type: String, default: null },
})

const logout = () => router.post(route('logout'))
</script>

<template>
    <Head title="Konto gesperrt" />

    <main class="flex min-h-screen items-center justify-center bg-bg px-4 py-10 text-primary">
        <section class="w-full max-w-xl rounded-lg border border-border bg-card p-8 text-center shadow-xl">
            <div class="mx-auto h-24 w-24">
                <AuthenticationCardLogo />
            </div>
            <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-danger">Konto vorübergehend gesperrt</p>
            <h1 class="mt-2 text-3xl font-bold text-primary">Airmius schuetzt die Community</h1>
            <p class="mt-4 text-sm leading-6 text-secondary">
                Dein Konto wurde wegen wiederholter oder schwerer Regelverstoesse automatisch eingeschraenkt.
                Ein zuständiges Teammitglied kann den Fall prüfen.
            </p>
            <div class="mt-5 rounded-lg border border-border bg-bg p-4 text-left text-sm text-secondary">
                <p v-if="reason"><span class="font-semibold text-primary">Grund:</span> {{ reason }}</p>
                <p v-if="suspended_until" class="mt-2"><span class="font-semibold text-primary">Gesperrt bis:</span> {{ suspended_until }}</p>
                <p v-if="!reason && !suspended_until">Bitte kontaktiere den Support, wenn du die Sperre für falsch hältst.</p>
            </div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <Link :href="route('legal.reporting')" class="btn">Support kontaktieren</Link>
                <button class="btn-primary" @click="logout">Abmelden</button>
            </div>
        </section>
    </main>
</template>
