<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
})

onMounted(() => {
    const theme = localStorage.getItem('theme')
    if (theme) {
        document.documentElement.classList.remove('theme-air', 'theme-dark', 'theme-womanly')
        document.documentElement.classList.add(`theme-${theme}`)
    }
})
// ========================
// STATE
// ========================
const activeTab = ref('sportler')

const tabs = [
    { key: 'sportler', label: '🏃 Sportler' },
    { key: 'trainer', label: '🎯 Trainer' },
    { key: 'vereine', label: '🏢 Vereine' },
]

// Inertia Form für Validation + Loading State
const form = useForm({
    name: '',
    email: '',
    message: ''
})

const formSuccess = ref(false)

// ========================
// METHODS
// ========================

const switchTab = (tab) => {
    activeTab.value = tab
}

const scrollTo = (id) => {
    const el = document.getElementById(id)
    if (!el) return

    const offset = 80
    const elementPosition = el.getBoundingClientRect().top + window.pageYOffset

    window.scrollTo({
        top: elementPosition - offset,
        behavior: 'smooth',
    })
}

const submitForm = () => {
    form.post(route('contact.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            formSuccess.value = true
            setTimeout(() => formSuccess.value = false, 3000)
        },
    })
}


</script>

<template>

    <Head title="Welcome" />
    <div id="app" class="w-full h-full bg-bg text-primary overflow-auto">
        <!-- NAV -->
        <Nav :canLogin="canLogin" />

        <!-- SUB NAV -->
         <Subnav />




        <!-- HERO -->
        <section id="hero" class="pt-24 pb-16 sm:pt-36 sm:pb-24 px-4 min-h-screen sm:h-dvh flex items-center">
            <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-12 lg:gap-32">
                <div class="flex-1 text-center lg:text-left">
                    <div
                        class="anim-fade inline-flex items-center gap-2 bg-white/5 border border-white/10 rounded-full px-4 py-1.5 text-xs font-medium text-air-green mb-6">
                        <span class="pulse-dot bg-air-green"></span> Jetzt in der Beta – Kostenlos starten
                    </div>
                    <h1 id="hero-title"
                        class="anim-fade-d1 font-heading font-900 text-4xl sm:text-5xl lg:text-6xl leading-tight tracking-tight">
                        Das soziale Netzwerk <br>
                        für deinen <span
                            class="bg-gradient-to-r from-air-blue via-air-green to-air-orange bg-clip-text text-transparent">Sport</span>
                    </h1>
                    <p id="hero-subtitle"
                        class="anim-fade-d2 mt-5 text-gray-400 text-lg sm:text-xl max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Für Sportler, Teams und Vereine. Organisation, Kommunikation und Vernetzung – vereint in einer
                        App.
                    </p>
                    <div class="anim-fade-d3 mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                        <button @click="scrollTo('kontakt')"
                            class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full text-center transition">
                            Jetzt starten
                        </button>
                        <button @click="scrollTo('vorteile')"
                            class="border border-white/15 hover:border-white/30 text-white font-semibold px-8 py-3.5 rounded-full text-center transition">
                            Kostenlos registrieren
                        </button>
                    </div>
                    <!-- Badge Zeile: umbrechen erlauben -->
                    <div
                        class="anim-fade-d4 mt-8 flex flex-wrap items-center gap-4 justify-center lg:justify-start text-sm text-gray-500">
                        <span class="flex items-center gap-1.5"><i
                                class="las la-check-circle text-air-green"></i>Kostenlos</span>
                        <span class="flex items-center gap-1.5"><i class="las la-check-circle text-air-green"></i>Keine
                            Kreditkarte</span>
                        <span class="flex items-center gap-1.5"><i
                                class="las la-check-circle text-air-green"></i>DSGVO-konform</span>
                    </div>
                </div>





                <!-- Mockup: mobil ausblenden -->
                <div class="hidden lg:block flex-1 max-w-lg w-full">
                    <div class="mockup-screen float-loop">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-3 h-3 rounded-full bg-red-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-500/80"></span>
                            <span class="w-3 h-3 rounded-full bg-green-500/80"></span>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="card-item item-1 bg-air-blue/10 border border-air-blue/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-blue/20 flex items-center justify-center">
                                    <i class="las la-comment text-air-blue"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Team Chat</div>
                                    <div class="text-sm font-medium">Training morgen um 18:00 👍</div>
                                </div>
                            </div>

                            <div
                                class="card-item item-2 bg-air-green/10 border border-air-green/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-green/20 flex items-center justify-center">
                                    <i class="las la-calendar text-air-green"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Nächstes Spiel</div>
                                    <div class="text-sm font-medium">Sa, 15:30 – FC Muster vs. Sportfreunde</div>
                                </div>
                            </div>

                            <div
                                class="card-item item-3 bg-air-orange/10 border border-air-orange/20 rounded-xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-air-orange/20 flex items-center justify-center">
                                    <i class="las la-chart-bar text-air-orange"></i>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-400">Deine Statistik</div>
                                    <div class="text-sm font-medium">12 Trainings · 89% Anwesenheit</div>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-2 card-item item-4">
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-blue">24</div>
                                    <div class="text-[10px] text-gray-500">Spieler</div>
                                </div>
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-green">18</div>
                                    <div class="text-[10px] text-gray-500">Zusagen</div>
                                </div>
                                <div class="flex-1 bg-white/5 rounded-lg p-2 text-center">
                                    <div class="text-2xl font-heading font-bold text-air-orange">3</div>
                                    <div class="text-[10px] text-gray-500">Absagen</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- PROBLEM → LÖSUNG -->
        <section class="py-16 sm:py-24 px-4 border-t border-white/5 min-h-screen sm:h-dvh flex items-center">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl">Statt <span class="text-red-400">5
                            Tools</span> nur <span class="text-air-green">eine Lösung</span></h2>
                    <p class="text-gray-400 mt-3 max-w-2xl mx-auto">WhatsApp, Excel, OneNote, E-Mail, Telefon⁉️</p>
                    <p class="text-gray-400 mt-2"> Schluss mit dem Chaos❗ AIRMIUS vereint alles.</p>
                </div>
                <div class="grid sm:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-comment-dots text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Chaos bei Kommunikation</h3>
                        <p class="text-sm text-gray-400">Infos gehen in WhatsApp-Gruppen unter. Wichtige Nachrichten
                            werden übersehen.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Zentraler Team-Chat mit Struktur</span>
                        </div>
                    </div>

                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-clock text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Hoher Zeitaufwand</h3>
                        <p class="text-sm text-gray-400">Manuelle Listen, endlose Abfragen, Zettelwirtschaft. Zeit, die
                            im Training fehlt.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Automatisierte Prozesse</span>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl p-6 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-red-500/10 flex items-center justify-center mb-4">
                            <i class="las la-eye-slash text-red-400 text-3xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-lg mb-2">Fehlende Übersicht</h3>
                        <p class="text-sm text-gray-400">Wer kommt? Wann ist Training? Wo stehen wir? Keiner weiß
                            Bescheid.</p>
                        <div class="mt-4 pt-4 border-t border-white/5">
                            <span class="text-air-green text-sm font-semibold">→ Echtzeit-Dashboard für alles</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- VORTEILE -->
        <section id="vorteile" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-8 sm:mb-10 px-2">
                    <span
                        class="text-air-blue text-xs sm:text-sm font-semibold uppercase tracking-wider">Vorteile</span>
                    <h2 class="font-heading font-800 text-2xl sm:text-3xl lg:text-4xl mt-2 leading-tight">
                        Eine App für <span
                            class="bg-gradient-to-r from-air-blue to-air-green bg-clip-text text-transparent">alle im
                            Sport</span>
                    </h2>
                    <p class="text-gray-400 mt-3 text-sm sm:text-base max-w-xl mx-auto">Egal ob Sportler, Trainer oder
                        Verein – AIRMIUS macht
                        deinen Alltag einfacher.</p>
                </div>

                <!-- Tabs: Mobil nur Icons, horizontal scroll -->
                <div class="mb-8 sm:mb-10 flex justify-center px-4">
                    <div class="inline-flex bg-white/5 rounded-full p-1 gap-1 flex-wrap justify-center">
                        <button v-for="tab in tabs" :key="tab.key" @click="switchTab(tab.key)" :class="[
                            'rounded-full px-4 sm:px-5 py-2.5 text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap',
                            activeTab === tab.key ? 'tab-active' : 'text-gray-400 hover:text-white'
                        ]">
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <!-- Sportler: 2 Spalten mobil -->
                <div v-show="activeTab === 'sportler'"
                    class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-grip-lines text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Alles an einem Ort</h4>
                        <p class="text-xs text-gray-400 leading-snug">Training, Spiele, Nachrichten – eine App
                            für alles.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-comment text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Kein WhatsApp-Chaos</h4>
                        <p class="text-xs text-gray-400 leading-snug">Strukturierte Kommunikation statt
                            endloser Gruppenflut.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-mouse-pointer text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Ein-Klick Zu-/Absage
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Teilnahme bestätigen war noch nie so
                            einfach.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-heart text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Echtzeit-Übersicht</h4>
                        <p class="text-xs text-gray-400 leading-snug">Immer wissen, was wann wo stattfindet.
                        </p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-chart-bar text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Persönliche Statistiken
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Dein Fortschritt auf einen Blick –
                            Motivation pur.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-history text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Trainingshistorie</h4>
                        <p class="text-xs text-gray-400 leading-snug">Alle vergangenen Einheiten jederzeit
                            einsehen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-bolt text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Motivation steigern</h4>
                        <p class="text-xs text-gray-400 leading-snug">Sichtbarer Fortschritt = mehr Leistung.
                        </p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-mobile text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Überall verfügbar</h4>
                        <p class="text-xs text-gray-400 leading-snug">Smartphone, Tablet, Desktop – immer
                            dabei.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-car text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Fahrgemeinschaften</h4>
                        <p class="text-xs text-gray-400 leading-snug">Gemeinsam zu Training & Events fahren –
                            Kosten teilen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-shopping-bag text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Einkaufsgemeinschaft
                        </h4>
                        <p class="text-xs text-gray-400 leading-snug">Bestellt euer Equipment gemeinsam zum
                            exklusiven Preis.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-3 sm:p-5 col-span-2 sm:col-span-1">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-blue/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-users text-air-blue text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-xs sm:text-sm mb-1 leading-tight">Sport-Buddy finden</h4>
                        <p class="text-xs text-gray-400 leading-snug">Finde jederzeit Leute zum Laufen,
                            Trainieren oder Spielen.</p>
                    </div>
                </div>

                <!-- Trainer: 1 Spalte mobil -->
                <div v-show="activeTab === 'trainer'"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-clock text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Massive Zeitersparnis</h4>
                        <p class="text-xs text-gray-400">Automatisiere Routineaufgaben und fokussiere dich aufs
                            Training.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-tasks text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Keine manuellen Listen</h4>
                        <p class="text-xs text-gray-400">Schluss mit Excel-Tabellen und handgeschriebenen Listen.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-user-check text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Auto-Anwesenheit</h4>
                        <p class="text-xs text-gray-400">Automatische Erfassung – wer war da, wer nicht.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-users text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Teamverwaltung</h4>
                        <p class="text-xs text-gray-400">Spieler hinzufügen, Gruppen erstellen, Struktur schaffen.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-calendar-plus text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Trainings- & Spielplanung</h4>
                        <p class="text-xs text-gray-400">Termine erstellen in Sekunden – auch wiederkehrend.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-eye text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Echtzeit-Teilnehmer</h4>
                        <p class="text-xs text-gray-400">Sofort sehen, wer zugesagt hat – keine Nachfragen mehr.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-bullhorn text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zentrale Kommunikation</h4>
                        <p class="text-xs text-gray-400">Keine Infoverluste mehr – alle erreichen, sofort.</p>
                    </div>
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-green/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-chart-line text-air-green text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Leistungsanalysen</h4>
                        <p class="text-xs text-gray-400">Datenbasiert bessere Entscheidungen treffen.</p>
                    </div>
                </div>

                <!-- Vereine: 1 Spalte mobil -->
                <div v-show="activeTab === 'vereine'"
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-laptop text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Digitalisierung</h4>
                        <p class="text-xs text-gray-400">Kein Excel, kein Papier – moderner Vereinsbetrieb.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-building text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zentrale Verwaltung</h4>
                        <p class="text-xs text-gray-400">Alle Teams & Mitglieder übersichtlich an einem Ort.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-sitemap text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Klare Strukturen</h4>
                        <p class="text-xs text-gray-400">Hierarchien und Rollen sauber abgebildet.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-share-alt text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Ebenenübergreifend</h4>
                        <p class="text-xs text-gray-400">Vorstand → Trainer → Spieler – Infos fließen reibungslos.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-piggy-bank text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Zeit- & Kostenersparnis</h4>
                        <p class="text-xs text-gray-400">Weniger Aufwand, weniger Kosten, mehr Fokus.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-star text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Professionelle Wirkung</h4>
                        <p class="text-xs text-gray-400">Mehr Attraktivität für neue Mitglieder & Sponsoren.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-wallet text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Beiträge verwalten</h4>
                        <p class="text-xs text-gray-400">Mitgliedsbeiträge einziehen, Mahnungen automatisieren,
                            Überblick behalten.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-handshake text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Sponsoren-Management</h4>
                        <p class="text-xs text-gray-400">Sponsoren pflegen, Pakete verwalten, Sichtbarkeit messen –
                            alles zentral.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-file-invoice text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Belege & Abrechnung</h4>
                        <p class="text-xs text-gray-400">Rechnungen & Quittungen digital ablegen, Ausgaben tracken,
                            Kassenbuch führen.</p>
                    </div>

                    <div class="benefit-card grad-card rounded-xl sm:rounded-2xl p-4 sm:p-5">
                        <div
                            class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-air-orange/15 flex items-center justify-center mb-2 sm:mb-3">
                            <i class="las la-calendar-plus text-air-orange text-lg sm:text-xl"></i>
                        </div>
                        <h4 class="font-heading font-600 text-sm mb-1">Termine für alle</h4>
                        <p class="text-xs text-gray-400">Versammlungen, Events & Spieltage anlegen – mit Zu-/Absagen für
                            den ganzen Verein.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FUNKTIONEN -->
        <section id="funktionen" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 40% at 50% 100%, rgba(0,102,255,.08) 0%, transparent 60%);">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">Funktionen</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Alles, was du brauchst</h2>
                    <p class="text-gray-400 mt-3 max-w-xl mx-auto">Leistungsstarke Features, einfach zu bedienen.</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl p-6 hover:border-air-blue/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4 group-hover:glow-blue transition">
                            <i class="las la-comments text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Team-Chat</h3>
                        <p class="text-sm text-gray-400">Echtzeit-Kommunikation wie WhatsApp – aber strukturiert,
                            übersichtlich und ohne Ablenkung.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-green/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4 group-hover:glow-green transition">
                            <i class="las la-dumbbell text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Trainingsplanung</h3>
                        <p class="text-sm text-gray-400">Erstelle Trainingspläne, wiederkehrende Termine und teile sie
                            mit dem ganzen Team.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-orange/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-orange/15 flex items-center justify-center mb-4 group-hover:glow-orange transition">
                            <i class="las la-calendar text-air-orange text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Terminverwaltung</h3>
                        <p class="text-sm text-gray-400">Spiele, Training, Events – alles im Kalender. Mit Erinnerungen
                            und Zu-/Absagen.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-blue/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4 group-hover:glow-blue transition">
                            <i class="las la-users text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Teammanagement</h3>
                        <p class="text-sm text-gray-400">Spieler verwalten, Rollen zuweisen, Teams strukturieren – alles
                            zentral.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-green/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4 group-hover:glow-green transition">
                            <i class="las la-chart-bar text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Statistiken & Analysen</h3>
                        <p class="text-sm text-gray-400">Leistungsdaten, Anwesenheitsquoten und Fortschritt – visuell
                            aufbereitet.</p>
                    </div>
                    <div class="grad-card rounded-2xl p-6 hover:border-air-orange/30 transition group">
                        <div
                            class="w-12 h-12 rounded-xl bg-air-orange/15 flex items-center justify-center mb-4 group-hover:glow-orange transition">
                            <i class="las la-clipboard text-air-orange text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 mb-2">Anwesenheitssystem</h3>
                        <p class="text-sm text-gray-400">Automatische Erfassung, wer dabei war. Keine Listen, kein
                            Nachfragen.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SPORTARTEN -->
        <section id="sportarten" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto text-center">
                <span class="text-air-orange text-sm font-semibold uppercase tracking-wider">Sportarten</span>
                <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Für jede Sportart gemacht</h2>
                <p class="text-gray-400 mt-3 max-w-xl mx-auto">Teamsport oder Einzelsport – AIRMIUS passt sich an.</p>
                <div class="mt-12 flex flex-wrap justify-center gap-4">
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">⚽</span><span class="font-heading font-600">Fußball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🏀</span><span class="font-heading font-600">Basketball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🏋️</span><span class="font-heading font-600">Fitness</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">🎾</span><span class="font-heading font-600">Tennis</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🏐</span><span class="font-heading font-600">Volleyball</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🏊</span><span class="font-heading font-600">Schwimmen</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-blue/30 transition">
                        <span class="text-3xl">🤸</span><span class="font-heading font-600">Turnen</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-green/30 transition">
                        <span class="text-3xl">🚴</span><span class="font-heading font-600">Radsport</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-air-orange/30 transition">
                        <span class="text-3xl">🥊</span><span class="font-heading font-600">Kampfsport</span>
                    </div>
                    <div
                        class="grad-card rounded-2xl px-6 py-5 flex items-center gap-3 hover:border-white/10 transition">
                        <span class="text-3xl">➕</span>
                        <span class="font-heading font-600 text-gray-400">und viele mehr</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ÜBER UNS -->
        <section id="ueber" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 50% 40% at 20% 50%, rgba(0,200,83,.06) 0%, transparent 50%);">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">Über uns</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Wir digitalisieren den Sport</h2>
                </div>

                <div class="grid md:grid-cols-2 gap-8">
                    <div class="grad-card rounded-2xl p-8">
                        <div class="w-12 h-12 rounded-xl bg-air-blue/15 flex items-center justify-center mb-4">
                            <i class="las la-rocket text-air-blue text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-xl mb-3">Unsere Vision</h3>
                        <p class="text-gray-400 leading-relaxed">
                            Die Digitalisierung im Sport vorantreiben. Wir bauen ein soziales Netzwerk für den Sport –
                            vergleichbar mit LinkedIn für berufliche Chancen und Airmius für sportliche Vernetzung.
                            Technologie soll den Sport besser, fairer und für alle zugänglicher machen.
                        </p>
                    </div>

                    <div class="grad-card rounded-2xl p-8">
                        <div class="w-12 h-12 rounded-xl bg-air-green/15 flex items-center justify-center mb-4">
                            <i class="las la-crosshairs text-air-green text-2xl"></i>
                        </div>
                        <h3 class="font-heading font-700 text-xl mb-3">Unsere Mission</h3>
                        <p class="text-gray-400 leading-relaxed">
                            Sport einfacher organisieren und Menschen verbinden. Wir schaffen eine Plattform, auf der
                            Trainer, Spieler und Vereine sich vernetzen, Chancen entdecken und ihre sportliche Zukunft
                            gestalten können. <br> Alles an einem Ort!
                        </p>
                    </div>
                </div>

                <div class="mt-8 grid sm:grid-cols-3 gap-4">
                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-shield-alt text-air-blue"></i>
                            <span class="font-heading font-600">DSGVO-konform</span>
                        </div>
                        <p class="text-xs text-gray-500">Datenschutz nach höchsten Standards</p>
                    </div>

                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-flag text-air-orange"></i>
                            <span class="font-heading font-600">Made in Germany</span>
                        </div>
                        <p class="text-xs text-gray-500">Entwickelt und gehostet in Deutschland</p>
                    </div>

                    <div class="grad-card rounded-xl p-5 text-center">
                        <div class="flex items-center justify-center gap-2 mb-2">
                            <i class="las la-heart text-air-green"></i>
                            <span class="font-heading font-600">Startup mit Herz</span>
                        </div>
                        <p class="text-xs text-gray-500">Von Sportlern für Sportler gebaut</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- BLOG -->
        <section id="blog" class="py-16 sm:py-24 px-4 border-t border-white/5">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="text-air-green text-sm font-semibold uppercase tracking-wider">Blog</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Neuigkeiten & Tipps</h2>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-blue/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-blue/20 to-air-blue/5 flex items-center justify-center">
                            <i class="las la-brain text-air-blue text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span class="text-xs uppercase tracking-wider text-air-blue font-semibold">Training</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-blue transition">5 Tipps für
                                effektiveres Mannschaftstraining</h3>
                            <p class="text-xs text-gray-500">Wie du mit einfachen Methoden das Beste aus jeder Einheit
                                holst.</p>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-green/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-green/20 to-air-green/5 flex items-center justify-center">
                            <i class="las la-fire text-air-green text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span class="text-xs uppercase tracking-wider text-air-green font-semibold">Motivation</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-green transition">Wie du
                                dein Team langfristig motivierst</h3>
                            <p class="text-xs text-gray-500">Strategien für mehr Engagement und Teamgeist.</p>
                        </div>
                    </div>
                    <div class="grad-card rounded-2xl overflow-hidden group hover:border-air-orange/20 transition">
                        <div
                            class="h-40 bg-gradient-to-br from-air-orange/20 to-air-orange/5 flex items-center justify-center">
                            <i class="las la-laptop-code text-air-orange text-5xl opacity-60"></i>
                        </div>
                        <div class="p-5">
                            <span
                                class="text-xs uppercase tracking-wider text-air-orange font-semibold">Digitalisierung</span>
                            <h3 class="font-heading font-600 mt-2 mb-2 group-hover:text-air-orange transition">Warum
                                dein Verein jetzt digital werden muss</h3>
                            <p class="text-xs text-gray-500">Der Wettbewerbsvorteil durch moderne Vereinsführung.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- KONTAKT -->
        <section id="kontakt" class="py-16 sm:py-24 px-4 border-t border-white/5"
            style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(0,102,255,.08) 0%, transparent 50%);">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-10">
                    <span class="text-air-blue text-sm font-semibold uppercase tracking-wider">Kontakt</span>
                    <h2 class="font-heading font-800 text-3xl sm:text-4xl mt-2">Schreib uns</h2>
                    <p class="text-gray-400 mt-3">Fragen, Feedback oder Partnerschaften? Wir freuen uns auf dich.</p>
                </div>
                <form @submit.prevent="submitForm" class="grad-card rounded-2xl p-6 sm:p-8 space-y-5">
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <label for="cf-name" class="block text-sm font-medium text-gray-300 mb-1.5">Name</label>
                            <input id="cf-name" v-model="form.name" type="text" placeholder="Dein Name"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <div v-if="form.errors.name" class="text-red-400 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label for="cf-email" class="block text-sm font-medium text-gray-300 mb-1.5">E-Mail</label>
                            <input id="cf-email" v-model="form.email" type="email" placeholder="deine@email.de"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition">
                            <div v-if="form.errors.email" class="text-red-400 text-xs mt-1">{{ form.errors.email }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="cf-msg" class="block text-sm font-medium text-gray-300 mb-1.5">Nachricht</label>
                        <textarea id="cf-msg" v-model="form.message" rows="4" placeholder="Was möchtest du uns sagen?"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-air-blue/50 transition resize-none"></textarea>
                        <div v-if="form.errors.message" class="text-red-400 text-xs mt-1">{{ form.errors.message }}
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                        class="w-full bg-air-blue hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-full transition">
                        <span v-if="form.processing">Wird gesendet...</span>
                        <span v-else>Nachricht senden</span>
                    </button>
                    <div v-show="formSuccess" class="text-center text-air-green text-sm font-medium py-2">
                        ✅ Danke! Deine Nachricht wurde gesendet.
                    </div>
                </form>
                <div class="mt-8 flex justify-center gap-5">
                    <a href="#"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-instagram text-gray-400"></i>
                    </a>
                    <a href="#"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-twitter text-gray-400"></i>
                    </a>
                    <a href="#"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-linkedin text-gray-400"></i>
                    </a>
                    <a href="#"
                        class="w-10 h-10 rounded-full bg-white/5 border border-white/10 flex items-center justify-center hover:border-air-blue/50 transition">
                        <i class="lab la-facebook text-gray-400"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA BANNER -->
        <section class="py-16 px-4 border-t border-white/5">
            <div class="max-w-4xl mx-auto text-center grad-card rounded-3xl p-10 sm:p-14"
                style="background: linear-gradient(135deg, rgba(0,102,255,.15), rgba(0,200,83,.1), rgba(255,109,0,.08)); border-color: rgba(0,102,255,.2);">
                <h2 class="font-heading font-800 text-3xl sm:text-4xl">Bereit, dein Team zu digitalisieren?</h2>
                <p class="text-gray-400 mt-3 max-w-lg mx-auto">Starte jetzt kostenlos und erlebe, wie einfach
                    Sportorganisation sein kann.</p>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <button @click="scrollTo('hero')"
                        class="bg-air-blue hover:bg-blue-600 glow-blue text-white font-bold px-8 py-3.5 rounded-full transition">
                        Kostenlos starten
                    </button>
                    <button @click="scrollTo('funktionen')"
                        class="border border-white/15 hover:border-white/30 text-white font-semibold px-8 py-3.5 rounded-full transition">
                        Funktionen entdecken
                    </button>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <Footer/>
    </div>
</template>
<style scoped>
/* 1. Mockup schwebt dauerhaft */
@keyframes floatLoop {

    0%,
    100% {
        transform: translateY(0px);
    }

    50% {
        transform: translateY(-12px);
    }
}

.float-loop {
    animation: floatLoop 6s ease-in-out infinite;
}

/* 2. Items verschwinden und listen sich neu auf - 10s Loop */
@keyframes listLoop {

    /* Start: alle da */
    0% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    /* 60%: alle verschwinden */
    60% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }

    65% {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }

    /* 70%: noch weg */
    70% {
        opacity: 0;
        transform: translateY(10px) scale(0.95);
    }

    /* 100%: wieder da */
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.card-item {
    animation: listLoop 10s ease-in-out infinite;
}

/* Nacheinander einblenden mit delay */
.item-1 {
    animation-delay: 0s;
}

.item-2 {
    animation-delay: 0.15s;
}

.item-3 {
    animation-delay: 0.3s;
}

.item-4 {
    animation-delay: 0.45s;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
