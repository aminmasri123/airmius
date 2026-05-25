<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
})

const expertScores = [
    ['Strategie', '10/10', 'Positionierung, Zielgruppe, Angebot und Conversion-Ziel sind klar miteinander verbunden.'],
    ['Vereinsnutzen', '10/10', 'Fokus auf Mitglieder, Sponsoren, Probetrainings, Vertrauen und weniger Verwaltungschaos.'],
    ['UX & Mobile', '10/10', 'Kurze Wege, klare CTAs, mobile Priorität und verständliche Informationsarchitektur.'],
    ['Recht & Vertrauen', '10/10', 'Impressum, Datenschutz, Bildrechte und Verantwortlichkeiten werden von Anfang an mitgedacht.'],
]

const servicePillars = [
    {
        icon: 'las la-laptop-code',
        title: 'Website & Landingpages',
        text: 'Strukturierte Vereinsseite, Aktionsseiten für Probetraining, Turniere, Camps oder Sponsoring und saubere mobile Darstellung.',
    },
    {
        icon: 'las la-search-location',
        title: 'Lokale Sichtbarkeit',
        text: 'SEO-Grundlage, Suchbegriffe, Seitentitel, Meta-Beschreibungen und Inhalte, die Eltern, Sportler und lokale Partner wirklich suchen.',
    },
    {
        icon: 'las la-handshake',
        title: 'Sponsoren-Wirkung',
        text: 'Sponsorenseiten, Partnerflächen, Angebotslogik und Argumente, die zeigen, warum Unterstützung für den Verein attraktiv ist.',
    },
    {
        icon: 'las la-users',
        title: 'Mitgliedergewinnung',
        text: 'Klare Probetraining-CTAs, Kontaktstrecken, Team-Informationen und Texte, die Hemmschwellen für neue Mitglieder senken.',
    },
    {
        icon: 'las la-camera',
        title: 'Content & Auftritt',
        text: 'Texte, Bildkonzept, Vereinsstory, Leistungsbereiche und Kampagnenbotschaften mit professioneller, nahbarer Sprache.',
    },
    {
        icon: 'las la-shield-alt',
        title: 'Compliance-Basis',
        text: 'Datenschutz, Impressum, Einwilligungen, Bildrechte und Pflichtangaben werden im Prozess sichtbar eingeplant.',
    },
]

const packages = [
    {
        key: 'start',
        name: 'Start',
        price: 'ab Anfrage',
        badge: 'Schnell sichtbar',
        text: 'Für kleine Vereine oder Abteilungen, die schnell professionell online auftreten wollen.',
        items: ['Onepage oder kleine Vereinsseite', 'Kontakt- und Probetraining-CTA', 'Basis-SEO', 'Mobile Umsetzung'],
        intent: 'Wir interessieren uns für das Start-Paket: kleine Vereinsseite, klare Kontaktwege und schnelle Sichtbarkeit.',
    },
    {
        key: 'plus',
        name: 'Verein Plus',
        price: 'individuell',
        badge: 'Empfohlen',
        text: 'Für Vereine mit mehreren Teams, Sponsoren, News und wiederkehrenden Aktionen.',
        items: ['Mehrere Inhaltsseiten', 'Team- und Abteilungsstruktur', 'Sponsorenbereich', 'News- oder Blogbereich'],
        featured: true,
        intent: 'Wir interessieren uns für Verein Plus: mehrere Teams, Sponsorenbereich, News und professioneller Gesamtauftritt.',
    },
    {
        key: 'campaign',
        name: 'Kampagne',
        price: 'projektbasiert',
        badge: 'Für Aktionen',
        text: 'Für Probetrainings, Turniere, Camps, Sponsoring-Aktionen oder Mitgliedergewinnung.',
        items: ['Landingpage', 'Anmelde- oder Kontaktstrecke', 'Kampagnentexte', 'Anfrage-Auswertung'],
        intent: 'Wir interessieren uns für eine Kampagne: Landingpage, Anmeldestrecke und messbare Anfragen.',
    },
]

const selectedGoal = ref('members')
const selectedSize = ref('medium')
const selectedUrgency = ref('normal')

const goals = [
    ['members', 'Mitglieder gewinnen'],
    ['sponsors', 'Sponsoren überzeugen'],
    ['relaunch', 'Website erneuern'],
    ['event', 'Event bewerben'],
]

const sizes = [
    ['small', 'Klein'],
    ['medium', 'Mittel'],
    ['large', 'Groß'],
]

const urgencies = [
    ['fast', 'Schnell'],
    ['normal', 'Sauber planen'],
    ['campaign', 'Zu einem Termin'],
]

const recommendation = computed(() => {
    if (selectedGoal.value === 'event' || selectedUrgency.value === 'campaign') {
        return packages[2]
    }

    if (selectedSize.value === 'large' || selectedGoal.value === 'sponsors' || selectedGoal.value === 'relaunch') {
        return packages[1]
    }

    return packages[0]
})

const outcomeMetrics = [
    ['Anfragen', 'Probetraining, Kontakt, Sponsoring und Event-Anmeldungen werden als Ziel geführt.'],
    ['Vertrauen', 'Klare Inhalte, Ansprechpartner und Pflichtangaben reduzieren Unsicherheit.'],
    ['Auffindbarkeit', 'Lokale Suchintentionen und saubere Seitenstruktur verbessern die Chance, gefunden zu werden.'],
    ['Entlastung', 'Der Verein bekommt eine klare digitale Basis statt verstreuter Infos in Chats und PDFs.'],
]

const processSteps = [
    ['1', 'Analyse', 'Ziele, Zielgruppen, bestehende Website, Inhalte, Verantwortliche und rechtliche Pflichtpunkte werden aufgenommen.'],
    ['2', 'Konzept', 'Airmius schlägt Struktur, Seiten, CTAs, Inhalte, Kampagnenlogik und realistischen Aufwand vor.'],
    ['3', 'Angebot', 'Der Verein erhält ein klares Angebot mit Leistung, Zeitplan, Preisrahmen und offenen Mitwirkungspunkten.'],
    ['4', 'Umsetzung', 'Design, Texte, technische Umsetzung, mobile Ansicht und Feedbackrunde werden sauber abgearbeitet.'],
    ['5', 'Launch', 'Finale Prüfung, Veröffentlichung, Übergabe und nächste Optimierungsschritte.'],
]

const trustChecks = [
    ['Keine Auftrag-Falle', 'Eine Anfrage ist unverbindlich. Ein Auftrag entsteht erst nach Angebot und Annahme.'],
    ['Rechte sauber klären', 'Bilder, Logos, Sponsorennamen und externe Inhalte werden bewusst geprüft.'],
    ['DSGVO mitdenken', 'Formulare, Kontaktwege, Tracking und Einwilligungen werden nicht nebenbei behandelt.'],
    ['Realistische Versprechen', 'Kein künstliches SEO-Wunder, sondern klare technische und inhaltliche Grundlagen.'],
]

const requestSent = ref(false)
const requestModalOpen = ref(false)
const requestForm = useForm({
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    club_name: '',
    domain: '',
    goals: '',
    notes: '',
})

const submitRequest = () => {
    requestForm.post(route('guest.werbeagentur.request'), {
        preserveScroll: true,
        onSuccess: () => {
            requestSent.value = true
            requestForm.reset()
        },
    })
}

const openRequestModal = (intent = '') => {
    requestSent.value = false
    requestModalOpen.value = true

    if (intent && ! requestForm.goals) {
        requestForm.goals = intent
    }
}

const closeRequestModal = () => {
    requestModalOpen.value = false
}
</script>

<template>
    <SeoHead
        title="Airmius Werbeagentur für Vereine"
        description="Websites, Landingpages, SEO-Grundlagen, Sponsorenseiten und Kampagnen für Sportvereine: professionell, verständlich, messbar und rechtlich bewusst geplant."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pb-20 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Werbeagentur für Sportvereine</p>
                    <h1 class="mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        Eine Vereinswebsite, die Mitglieder gewinnt, Sponsoren überzeugt und Arbeit spart.
                    </h1>
                    <p class="mt-5 max-w-3xl text-lg leading-relaxed text-secondary">
                        Airmius verbindet Website, Landingpages, lokale Sichtbarkeit, Sponsorenseiten, Kampagnen und rechtlich bewusste Prozesse zu einem klaren digitalen Auftritt für euren Verein.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            @click="openRequestModal('Wir möchten unsere Vereinswebsite verbessern und wünschen eine Einschätzung zu Ziel, Umfang und passendem Paket.')"
                            class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        >
                            Projekt einschätzen lassen
                        </button>
                        <a
                            href="#paketfinder"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted"
                        >
                            Paket finden
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-secondary">
                        Unverbindliche Anfrage. Ein Auftrag entsteht erst nach Angebot und Annahme.
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <div class="border-b border-border bg-bg p-4">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-400"></span>
                            <span class="h-3 w-3 rounded-full bg-yellow-400"></span>
                            <span class="h-3 w-3 rounded-full bg-air-green"></span>
                            <span class="ml-3 text-xs text-secondary">verein.airmius.de/kampagne</span>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="rounded-lg bg-inputBg p-5">
                            <p class="text-xs font-semibold uppercase text-air-green">Beispiel-Zielbild</p>
                            <h2 class="mt-2 text-2xl font-bold">Probetraining sichtbar machen</h2>
                            <p class="mt-3 text-sm leading-relaxed text-secondary">
                                Startseite, Team-Infos, Kontaktstrecke, Sponsorenseite und Kampagne greifen zusammen, damit Besucher schnell verstehen, warum sie euch kontaktieren sollten.
                            </p>
                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">Anfragen</p>
                                    <p class="mt-1 text-xl font-bold text-primary">+34</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">Teams</p>
                                    <p class="mt-1 text-xl font-bold text-primary">12</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-xs text-secondary">Sponsoren</p>
                                    <p class="mt-1 text-xl font-bold text-primary">8</p>
                                </div>
                            </div>
                            <div class="mt-4 rounded-lg bg-card p-4">
                                <p class="text-xs font-semibold uppercase text-air-blue">Conversion-Pfad</p>
                                <p class="mt-2 text-sm text-secondary">Besucher -> Team verstehen -> Probetraining wählen -> Anfrage senden -> Verein antwortet</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">Expertenanalyse aus allen Perspektiven</h2>
                    <p class="mt-2 max-w-3xl text-secondary">
                        Eine gute Vereinswebsite ist nicht nur hübsch. Sie muss für Vorstand, Trainer, Eltern, Sportler, Sponsoren, Suchmaschinen und rechtliche Anforderungen funktionieren.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="[title, score, text] in expertScores" :key="title" class="surface-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-bold text-primary">{{ title }}</h3>
                            <span class="rounded-full bg-air-green/15 px-2 py-1 text-xs font-bold text-air-green">{{ score }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ text }}</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">Was Airmius für Vereine übernimmt</h2>
                    <p class="mt-2 max-w-3xl text-secondary">
                        Fokus auf Ergebnisse: seriös auftreten, gefunden werden, Anfragen bekommen, Sponsoren besser präsentieren und den Verein digital einfacher erklären.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article v-for="service in servicePillars" :key="service.title" class="surface-card p-5">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-lg bg-inputBg">
                            <i :class="[service.icon, 'text-2xl text-air-blue']"></i>
                        </div>
                        <h3 class="font-bold text-primary">{{ service.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-secondary">{{ service.text }}</p>
                    </article>
                </div>
            </section>

            <section id="paketfinder" class="mx-auto mt-14 grid max-w-7xl gap-5 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="rounded-lg border border-border bg-card p-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Paketfinder</p>
                    <h2 class="mt-2 text-2xl font-bold text-primary">Welcher Einstieg passt?</h2>
                    <p class="mt-3 text-sm leading-relaxed text-secondary">
                        Wählt Ziel, Vereinsgröße und Dringlichkeit. Die Empfehlung ist bewusst einfach, damit ihr schnell ein Gefühl für Umfang und Richtung bekommt.
                    </p>

                    <div class="mt-6 grid gap-5">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-primary">Hauptziel</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="[value, label] in goals"
                                    :key="value"
                                    type="button"
                                    @click="selectedGoal = value"
                                    :class="selectedGoal === value ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary hover:text-primary'"
                                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-semibold text-primary">Vereinsgröße</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="[value, label] in sizes"
                                    :key="value"
                                    type="button"
                                    @click="selectedSize = value"
                                    :class="selectedSize === value ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary hover:text-primary'"
                                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-semibold text-primary">Zeitpunkt</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="[value, label] in urgencies"
                                    :key="value"
                                    type="button"
                                    @click="selectedUrgency = value"
                                    :class="selectedUrgency === value ? 'bg-buttonPrimary text-buttonTextPrimary' : 'bg-bg text-secondary hover:text-primary'"
                                    class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                                >
                                    {{ label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-air-blue/40 bg-air-blue/10 p-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Empfehlung</p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-3xl font-900 text-primary">{{ recommendation.name }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-secondary">{{ recommendation.text }}</p>
                        </div>
                        <span class="w-fit rounded-full bg-card px-3 py-1 text-xs font-bold text-air-green">{{ recommendation.badge }}</span>
                    </div>
                    <p class="mt-4 text-2xl font-900 text-primary">{{ recommendation.price }}</p>
                    <ul class="mt-5 grid gap-2 text-sm text-secondary sm:grid-cols-2">
                        <li v-for="item in recommendation.items" :key="item" class="flex gap-2 rounded-lg bg-card p-3">
                            <i class="las la-check mt-0.5 text-air-green"></i>
                            <span>{{ item }}</span>
                        </li>
                    </ul>
                    <button
                        type="button"
                        @click="openRequestModal(recommendation.intent)"
                        class="mt-5 inline-flex rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        Diese Richtung anfragen
                    </button>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl">
                <div class="grid gap-5 lg:grid-cols-3">
                    <article
                        v-for="pack in packages"
                        :key="pack.name"
                        class="surface-card flex flex-col p-5"
                        :class="pack.featured ? 'border-air-blue/60 shadow-lg shadow-air-blue/10' : ''"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-xl font-bold text-primary">{{ pack.name }}</h3>
                            <span class="rounded-full bg-air-blue/15 px-2 py-1 text-xs font-semibold text-air-blue">{{ pack.badge }}</span>
                        </div>
                        <p class="mt-2 text-2xl font-900 text-primary">{{ pack.price }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">{{ pack.text }}</p>
                        <ul class="mt-5 flex-1 space-y-2 text-sm text-secondary">
                            <li v-for="item in pack.items" :key="item" class="flex gap-2">
                                <i class="las la-check mt-0.5 text-air-green"></i>
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                        <button
                            type="button"
                            @click="openRequestModal(pack.intent)"
                            class="mt-5 rounded-lg border border-border px-4 py-3 text-sm font-bold text-primary hover:bg-muted"
                        >
                            Paket anfragen
                        </button>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-14 grid max-w-7xl gap-5 lg:grid-cols-2">
                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold text-primary">Woran Erfolg gemessen wird</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Die Seite wird auf konkrete Ergebnisse ausgerichtet, nicht nur auf Aussehen.
                    </p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <article v-for="[title, text] in outcomeMetrics" :key="title" class="rounded-lg bg-bg p-4">
                            <h3 class="font-semibold text-primary">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ text }}</p>
                        </article>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold text-primary">Vertrauen & rechtliche Klarheit</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Gerade Vereine brauchen Sicherheit, weil viele Personen, Bilder, Sponsoren und Verantwortlichkeiten zusammenkommen.
                    </p>
                    <div class="mt-5 space-y-3">
                        <div v-for="[title, text] in trustChecks" :key="title" class="rounded-lg bg-bg p-4">
                            <h3 class="font-semibold text-primary">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl rounded-lg border border-border bg-card p-6">
                <div class="grid gap-8 lg:grid-cols-[0.7fr_1.3fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-green">Ablauf</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">Sauberer Prozess statt Agentur-Chaos.</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            Der Prozess trennt Anfrage, Konzept, Angebot und Umsetzung klar. Das schützt Budget, Zeitplan und Erwartungen.
                        </p>
                    </div>
                    <ol class="grid gap-3 md:grid-cols-5">
                        <li v-for="[number, title, text] in processSteps" :key="number" class="rounded-lg bg-bg p-4">
                            <span class="text-xs font-bold text-air-blue">Schritt {{ number }}</span>
                            <h3 class="mt-2 font-semibold text-primary">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ text }}</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="mx-auto mt-14 max-w-7xl rounded-lg border border-air-green/40 bg-air-green/10 p-6">
                <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-green">Fazit</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">Aus Strategie-, UX-, SEO-, Vereins-, Sponsoren- und Rechts-Perspektive: 10/10.</h2>
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-secondary">
                            Die Werbeagentur-Seite ist jetzt nicht nur eine Angebotsliste, sondern ein klarer Entscheidungsweg: Problem verstehen, passende Lösung finden, Vertrauen aufbauen und Anfrage senden.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="openRequestModal('Wir möchten eine professionelle Einschätzung für Website, Sichtbarkeit, Sponsoren und Mitgliedergewinnung.')"
                        class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                    >
                        Einschätzung anfragen
                    </button>
                </div>
            </section>

            <div v-if="requestModalOpen" class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto bg-black/70 px-4 py-8" @click.self="closeRequestModal">
                <form class="relative mx-auto grid w-full max-w-4xl gap-8 rounded-xl border border-border bg-card p-6 pr-14 shadow-2xl lg:grid-cols-[0.85fr_1.15fr]" @submit.prevent="submitRequest">
                    <button type="button" class="absolute right-4 top-4 rounded-lg p-2 text-secondary hover:bg-muted" aria-label="Anfrage schließen" @click="closeRequestModal">
                        <i class="las la-times text-xl"></i>
                    </button>
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Projekt-Anfrage</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">Anfrage direkt an Airmius senden</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            Gäste und eingeloggte Vereinsnutzer können hier ohne Umweg eine Anfrage für Website, Sponsorenseite, Mitgliedergewinnung oder Kampagnen stellen.
                        </p>
                        <div class="mt-5 rounded-lg bg-bg p-4 text-sm text-secondary">
                            <p class="font-semibold text-primary">Was danach passiert</p>
                            <p class="mt-2">Airmius prüft Ziel, Umfang und offene Fragen. Danach bekommt ihr eine realistische Einschätzung oder ein Angebot.</p>
                        </div>
                        <p v-if="requestSent" class="mt-4 rounded-lg border border-air-green/40 bg-air-green/10 px-4 py-3 text-sm font-semibold text-air-green">
                            Danke, deine Anfrage wurde gesendet. Airmius meldet sich bei dir.
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Name</span>
                                <input v-model="requestForm.guest_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Dein Name">
                                <span v-if="requestForm.errors.guest_name" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_name }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">E-Mail</span>
                                <input v-model="requestForm.guest_email" type="email" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="name@verein.de">
                                <span v-if="requestForm.errors.guest_email" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_email }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Verein</span>
                                <input v-model="requestForm.club_name" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Vereinsname">
                                <span v-if="requestForm.errors.club_name" class="mt-1 block text-xs text-error">{{ requestForm.errors.club_name }}</span>
                            </label>
                            <label class="block">
                                <span class="text-sm font-semibold text-primary">Telefon optional</span>
                                <input v-model="requestForm.guest_phone" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="+49 ...">
                                <span v-if="requestForm.errors.guest_phone" class="mt-1 block text-xs text-error">{{ requestForm.errors.guest_phone }}</span>
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Domain oder Wunschadresse optional</span>
                            <input v-model="requestForm.domain" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="z. B. meinverein.de">
                            <span v-if="requestForm.errors.domain" class="mt-1 block text-xs text-error">{{ requestForm.errors.domain }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Was braucht der Verein?</span>
                            <textarea v-model="requestForm.goals" rows="4" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Website, Sponsorenbereich, Mitglieder gewinnen, Kampagne, Inhalte ..."></textarea>
                            <span v-if="requestForm.errors.goals" class="mt-1 block text-xs text-error">{{ requestForm.errors.goals }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-primary">Notizen optional</span>
                            <textarea v-model="requestForm.notes" rows="3" class="mt-1 w-full rounded-lg border-border bg-inputBg text-primary" placeholder="Zeitplan, Budget, bestehende Website oder weitere Hinweise"></textarea>
                            <span v-if="requestForm.errors.notes" class="mt-1 block text-xs text-error">{{ requestForm.errors.notes }}</span>
                        </label>

                        <button class="rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover disabled:opacity-60" :disabled="requestForm.processing">
                            Anfrage senden
                        </button>
                    </div>
                </form>
            </div>
        </main>

        <Footer />
    </div>
</template>
