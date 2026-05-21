<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue'

defineProps({
    reason: { type: String, default: null },
    suspended_until: { type: String, default: null },
})

const supportModalOpen = ref(false)
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
                <button type="button" class="btn" @click="supportModalOpen = true">Support kontaktieren</button>
                <button class="btn-primary" @click="logout">Abmelden</button>
            </div>
        </section>

        <div
            v-if="supportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4 py-8"
            @click.self="supportModalOpen = false"
        >
            <section class="relative w-full max-w-lg rounded-xl border border-border bg-card p-6 text-left shadow-2xl">
                <button
                    type="button"
                    class="absolute right-4 top-4 rounded-lg p-2 text-secondary hover:bg-muted"
                    aria-label="Support-Fenster schliessen"
                    @click="supportModalOpen = false"
                >
                    <i class="las la-times text-xl"></i>
                </button>

                <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">Support</p>
                <h2 class="mt-2 pr-8 text-2xl font-bold text-primary">Sperre prüfen lassen</h2>
                <p class="mt-3 text-sm leading-6 text-secondary">
                    Wenn du glaubst, dass die Sperre falsch ist, kontaktiere den Support mit einer kurzen Erklärung.
                    Bitte nenne deine Konto-E-Mail und beschreibe, was passiert ist.
                </p>

                <div class="mt-5 rounded-lg border border-border bg-bg p-4 text-sm text-secondary">
                    <p v-if="reason"><span class="font-semibold text-primary">Aktueller Grund:</span> {{ reason }}</p>
                    <p v-if="suspended_until" class="mt-2"><span class="font-semibold text-primary">Gesperrt bis:</span> {{ suspended_until }}</p>
                    <p class="mt-2"><span class="font-semibold text-primary">E-Mail:</span> contact@airmius.com</p>
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <a
                        class="btn-primary inline-flex justify-center"
                        href="mailto:contact@airmius.com?subject=Konto-Sperre%20pr%C3%BCfen"
                    >
                        E-Mail schreiben
                    </a>
                    <Link :href="route('legal.reporting')" class="btn inline-flex justify-center">
                        Kontaktseite öffnen
                    </Link>
                </div>
            </section>
        </div>
    </main>
</template>
