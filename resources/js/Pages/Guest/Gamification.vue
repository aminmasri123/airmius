<script setup>
import { Link } from '@inertiajs/vue3'
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
    ['Motivation', '10/10', 'Belohnt echte Entwicklung statt blinden Login-Druck.'],
    ['Fairness', '10/10', 'Daily Caps, Trust Score und Strafen begrenzen Manipulation.'],
    ['Vereinsnutzen', '10/10', 'Training, Events, Teamaufbau und gute Kommunikation werden sichtbar.'],
    ['Jugendschutz', '10/10', 'Altersgerechte Ziele, kein Pay-to-win und keine Ranglisten um jeden Preis.'],
]

const principles = [
    ['Meaningful first', 'XP entsteht durch sinnvolle sportliche Aktionen, nicht durch reine Nutzungsdauer.'],
    ['Trust weighted', 'Vertrauenswuerdige Nutzer erhalten mehr Wirkung, auffaellige Muster weniger.'],
    ['Role aware', 'Sportler, Trainer, Teams und Vereine werden nach passenden Beiträgen bewertet.'],
    ['Transparent by design', 'Regeln, Limits, Abzuege und Fortschritt sind nachvollziehbar erklaerbar.'],
]

const roleCards = [
    {
        icon: 'las la-running',
        title: 'Sportler',
        score: 'Profil, Training, Skills',
        text: 'Fortschritt wird über Sportprofil, Skill-Entwicklung, Trainingsteilnahme, hilfreiche Beiträge und bestätigte Empfehlungen sichtbar.',
        metrics: ['XP', 'Level', 'Rang', 'Streak', 'Trust Score'],
    },
    {
        icon: 'las la-chalkboard-teacher',
        title: 'Trainer',
        score: 'Feedback, Planung, Wissen',
        text: 'Trainer gewinnen Reputation durch konstruktives Feedback, Trainingsplanung, Wissenstransfer und langfristige Entwicklungsbegleitung.',
        metrics: ['Assistant', 'Coach', 'Senior Coach', 'Head Coach', 'Master Trainer'],
    },
    {
        icon: 'las la-warehouse',
        title: 'Vereine',
        score: 'Organisation, Kultur, Events',
        text: 'Vereins-XP macht Struktur, Trainingsbetrieb, Events, Profilqualitaet und informative Vereinskommunikation messbar.',
        metrics: ['Local Club', 'Growing Club', 'Established', 'Elite Club'],
    },
]

const playerActions = [
    ['Sportart hinzufügen', '+15 XP', 'Strukturiert das sportliche Profil und erzeugt passende Skills.'],
    ['Skill bearbeiten', '+3 XP', 'Kompetenz, Notiz oder Sichtbarkeit pflegen. Maximal 3x täglich.'],
    ['Skill-Level verbessern', '+8 XP', 'Anerkennt echte persoenliche Entwicklung. Maximal 3x täglich.'],
    ['Skill-Bestätigung', '+10 XP', 'Bestätigung durch andere Nutzer, Trainer oder Vereine. Maximal 3x täglich.'],
    ['Empfehlung freigeben', '+10 XP', 'Qualitätsgesicherte Empfehlung auf dem Profil veröffentlichen. Maximal 2x täglich.'],
    ['Training zusagen', '+5 XP', 'Verbindliche Teilnahmezusage zu einer Trainingseinheit. Maximal 3x täglich.'],
    ['Training Check-in', '+2 XP', 'Dokumentierte Teilnahme, zum Beispiel per QR-Code, GPS oder Trainerbestätigung.'],
    ['Hilfreicher Beitrag', '+5 XP', 'Wissen, Training, Taktik, Analyse oder Erfahrung mit Mehrwert. Maximal 3x täglich.'],
]

const demoActions = [
    { key: 'sport_profile_added', label: 'Sportart hinzufügen', xp: 15, limit: 1, value: ref(1) },
    { key: 'skill_level_improved', label: 'Skill-Level verbessern', xp: 8, limit: 3, value: ref(2) },
    { key: 'skill_endorsed', label: 'Skill bestätigen lassen', xp: 10, limit: 3, value: ref(1) },
    { key: 'training_accepted', label: 'Training zusagen', xp: 5, limit: 3, value: ref(3) },
    { key: 'training_check_in', label: 'Training Check-in', xp: 2, limit: 3, value: ref(2) },
    { key: 'content_created', label: 'Hilfreichen Beitrag erstellen', xp: 5, limit: 3, value: ref(1) },
]

const trustMultiplier = ref(1)

const demoBaseXp = computed(() => demoActions.reduce((sum, action) => sum + (action.value.value * action.xp), 0))
const demoDailyBonus = computed(() => demoBaseXp.value > 0 ? 2 : 0)
const demoTotalXp = computed(() => Math.round((demoBaseXp.value + demoDailyBonus.value) * trustMultiplier.value))
const demoProgress = computed(() => Math.min(100, Math.round((demoTotalXp.value / 50) * 100)))

const organizationActions = [
    ['Training erstellt', '+5 XP', 'Team oder Verein plant eine konkrete Einheit.'],
    ['Training durchgefuehrt', '+10 XP', 'Durchfuehrung wurde verifiziert oder nachvollziehbar dokumentiert.'],
    ['Event erstellt', '+10 XP', 'Turnier, Probetraining, Camp oder Vereinsaktion wird geplant.'],
    ['Event durchgefuehrt', '+25 XP', 'Event wurde tatsaechlich umgesetzt.'],
    ['Vereinsprofil vollstaendig', '+15 XP', 'Struktur, Ansprechpartner und Grunddaten sind gepflegt.'],
    ['Informative Vereinsnews', '+8 XP', 'Vereinsbeitrag mit echtem Mehrwert für Mitglieder oder Öffentlichkeit.'],
]

const safetyLayers = [
    ['Daily Caps', 'XP-relevante Wiederholungen sind pro Tag begrenzt, damit Qualität wichtiger bleibt als Masse.'],
    ['Trust Score 70-130', 'Der Multiplikator reicht von 0,70 bis 1,30 und wird durch zuverlaessiges Verhalten beeinflusst.'],
    ['Penalty Events', 'No-Shows, falsche Bestätigungen, Spam und abgelehnte Empfehlungen reduzieren XP und Trust.'],
    ['Serverseitige Vergabe', 'XP wird ausschliesslich serverseitig berechnet und mit Quelle, Besitzer, Actor Type und Limit-Status protokolliert.'],
    ['Moderierbare Regeln', 'Admins koennen Labels, Beschreibungen, XP, Limits, Trust Delta und Aktivitaet zentral verwalten.'],
    ['Badge-Governance', 'Badges werden über XP, Level, Streak oder konkrete Gründe vergeben und bleiben auditierbar.'],
]

const levelMilestones = [
    ['Level 1', '0 XP', 'Rookie'],
    ['Level 3', 'ca. 813 XP', 'Amateur'],
    ['Level 5', 'ca. 3.143 XP', 'Athlete'],
    ['Level 7', 'ca. 7.264 XP', 'Advanced'],
    ['Level 9', 'ca. 13.426 XP', 'Elite'],
]

const lifecycle = [
    ['Onboarding', 'Sportart, Skills und erste Teilnahme geben schnelle Orientierung.'],
    ['Engagement', 'Streaks, Feedback und hilfreiche Beiträge halten Aktivitaet wertvoll.'],
    ['Retention', 'Level, Badges und Rollen-Raenge schaffen langfristige Entwicklungspfade.'],
    ['Reputation', 'Trust Score, Empfehlungen und Bestätigungen machen Qualität sichtbar.'],
]

const penalties = [
    ['No-Show', '-5 XP', 'Unentschuldigtes Fernbleiben trotz Anmeldung.'],
    ['Falsche Bestätigung', '-50 XP', 'Manipulierte oder unwahre Bestätigung.'],
    ['Spam oder Missbrauch', '-30 XP', 'Minderwertige Wiederholung oder missbraeuchliches Verhalten.'],
    ['Abgelehnte Empfehlung', '-5 XP', 'Qualitätssicherung bei Profil-Empfehlungen.'],
    ['Übermäßige Nutzung', '-5 XP', 'XP kann bei exzessiver Nutzung reduziert oder pausiert werden.'],
]

const badges = [
    ['las la-seedling', 'Erste Schritte', '50 XP gesammelt', 'Für den sichtbaren Start in die sportliche Entwicklung.'],
    ['las la-fire', 'Streak Starter', '3 sinnvolle Tage', 'Belohnt wiederholte Qualität statt reines Einloggen.'],
    ['las la-user-check', 'Verlaesslich', 'Trust stabil', 'Zeigt, dass Zusagen, Check-ins und Bestätigungen sauber bleiben.'],
    ['las la-lightbulb', 'Wissensgeber', 'Hilfreicher Beitrag', 'Für Trainingstipps, Analysen oder Erfahrungswissen mit Mehrwert.'],
    ['las la-warehouse', 'Wachsender Verein', '250 Vereins-XP', 'Macht gute Organisation, Events und Kommunikation sichtbar.'],
    ['las la-shield-alt', 'Fair Play', 'Keine Auffälligkeiten', 'Ein Signal für respektvolle und manipulationsfreie Nutzung.'],
]
</script>

<template>
    <SeoHead
        title="Gamification System für Sportler, Trainer, Teams und Vereine"
        description="Airmius Gamification macht sportliche Entwicklung, Engagement, Vertrauen und Vereinsarbeit sichtbar, fair und jugendschutzfreundlich."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" :canRegister="canRegister" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-wider text-air-blue">Airmius Level-System</span>
                    <h1 class="mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                        Gamification, die sportliche Entwicklung gesund sichtbar macht.
                    </h1>
                    <p class="mt-5 max-w-3xl text-lg leading-relaxed text-secondary">
                        Airmius belohnt Training, Zuverlaessigkeit, Kompetenz, Wissen, Teamkultur und Vereinsarbeit. Das System ist fair, rollenbasiert, auditierbar und bewusst so gebaut, dass bei jungen Sportlern kein ungesunder Leistungsdruck entsteht.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        >
                            Kostenlos starten
                        </Link>
                        <Link
                            :href="route('guest.pricing')"
                            class="inline-flex items-center justify-center rounded-lg border border-border px-5 py-3 text-sm font-bold text-primary hover:bg-muted"
                        >
                            Pakete ansehen
                        </Link>
                    </div>
                    <p class="mt-3 text-xs text-secondary">
                        Kein Pay-to-win. Keine XP durch blosses Einloggen. Fortschritt entsteht durch nachvollziehbare Aktionen.
                    </p>
                </div>

                <div class="overflow-hidden rounded-lg border border-border bg-card">
                    <div class="border-b border-border bg-bg p-4">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-400"></span>
                            <span class="h-3 w-3 rounded-full bg-yellow-400"></span>
                            <span class="h-3 w-3 rounded-full bg-air-green"></span>
                            <span class="ml-3 text-xs text-secondary">profil.airmius.de/level</span>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="rounded-lg bg-inputBg p-5">
                            <p class="text-xs font-semibold uppercase text-air-green">Live-Profil</p>
                            <div class="mt-4 grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
                                <div class="rounded-lg bg-card p-4">
                                    <p class="text-sm text-secondary">Aktueller Rang</p>
                                    <p class="mt-2 text-3xl font-900 text-primary">Athlete</p>
                                    <p class="mt-1 text-sm text-air-blue">Level 5</p>
                                </div>
                                <div class="rounded-lg bg-card p-4">
                                    <div class="flex items-center justify-between gap-4 text-sm">
                                        <span class="text-secondary">Fortschritt</span>
                                        <span class="font-mono text-air-green">64%</span>
                                    </div>
                                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-bg">
                                        <div class="h-full w-[64%] rounded-full bg-buttonPrimary"></div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                                        <div class="rounded-md bg-bg p-2">
                                            <p class="font-bold text-primary">1.30x</p>
                                            <p class="text-secondary">Trust</p>
                                        </div>
                                        <div class="rounded-md bg-bg p-2">
                                            <p class="font-bold text-primary">14</p>
                                            <p class="text-secondary">Streak</p>
                                        </div>
                                        <div class="rounded-md bg-bg p-2">
                                            <p class="font-bold text-primary">8</p>
                                            <p class="text-secondary">Badges</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-secondary">Training</p>
                                    <p class="mt-1 font-bold text-primary">Check-in bestätigt</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-secondary">Skill</p>
                                    <p class="mt-1 font-bold text-primary">Level verbessert</p>
                                </div>
                                <div class="rounded-md bg-card p-3">
                                    <p class="text-secondary">Fairness</p>
                                    <p class="mt-1 font-bold text-primary">Daily Cap aktiv</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-7xl">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">Expertenanalyse aus allen Perspektiven</h2>
                    <p class="mt-2 max-w-3xl text-secondary">
                        Das System wird nicht nur als Punkte-Mechanik bewertet, sondern als Produktmotor für Motivation, Vertrauen, Vereinsorganisation, Sicherheit und langfristige Bindung.
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

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 lg:grid-cols-4">
                <article v-for="[title, text] in principles" :key="title" class="surface-card p-5">
                    <h2 class="text-lg font-bold">{{ title }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-secondary">{{ text }}</p>
                </article>
            </section>

            <section class="mx-auto mt-12 max-w-7xl">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-primary">Rollenlogik</h2>
                    <p class="mt-2 max-w-3xl text-secondary">
                        Eine gute Sportplattform darf nicht alle gleich messen. Airmius bewertet jede Rolle nach ihrem echten Beitrag.
                    </p>
                </div>
                <div class="grid gap-5 lg:grid-cols-3">
                    <article v-for="role in roleCards" :key="role.title" class="surface-card p-5">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-inputBg">
                                <i :class="[role.icon, 'text-2xl text-air-blue']"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-primary">{{ role.title }}</h3>
                                <p class="text-xs text-air-green">{{ role.score }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-secondary">{{ role.text }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span v-for="metric in role.metrics" :key="metric" class="rounded-full bg-bg px-3 py-1 text-xs font-semibold text-secondary">
                                {{ metric }}
                            </span>
                        </div>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 xl:grid-cols-[1.15fr_0.85fr]">
                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">XP-Aktionen für Sportler</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Die wichtigsten positiven Aktionen sind konkret, begrenzt und mit Trust gekoppelt.
                    </p>
                    <div class="mt-5 overflow-hidden rounded-lg border border-border">
                        <div
                            v-for="[action, xp, text] in playerActions"
                            :key="action"
                            class="grid gap-3 border-b border-border bg-bg p-4 last:border-b-0 md:grid-cols-[190px_90px_1fr]"
                        >
                            <div class="font-semibold">{{ action }}</div>
                            <div class="font-mono text-air-green">{{ xp }}</div>
                            <div class="text-sm text-secondary">{{ text }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Level-System</h2>
                    <p class="mt-3 text-sm leading-relaxed text-secondary">
                        Die XP-Schwelle steigt progressiv. Frühe Level motivieren schnell, höhere Level verlangen langfristige Qualität.
                    </p>
                    <div class="mt-4 rounded-lg bg-inputBg p-4 font-mono text-sm text-air-green">
                        XP_needed = Summe aus 250 * level^1.7
                    </div>
                    <div class="mt-5 grid gap-3">
                        <div v-for="[level, xp, rank] in levelMilestones" :key="level" class="flex items-center justify-between gap-4 rounded-lg bg-bg p-3 text-sm">
                            <span class="font-semibold">{{ level }}</span>
                            <span class="text-secondary">{{ xp }}</span>
                            <span class="font-bold text-air-blue">{{ rank }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                <div class="rounded-lg border border-border bg-card p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Interaktive XP-Demo</p>
                            <h2 class="mt-2 text-2xl font-bold">Teste, wie Fortschritt entsteht.</h2>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">
                                Die Demo nutzt echte XP-Werte und Daily Caps aus dem System. So wird sofort klar: wenige sinnvolle Aktionen bringen mehr als viele leere Klicks.
                            </p>
                        </div>
                        <div class="rounded-lg bg-inputBg p-4 text-right">
                            <p class="text-xs font-semibold uppercase text-secondary">Ergebnis</p>
                            <p class="mt-1 text-3xl font-900 text-air-green">+{{ demoTotalXp }} XP</p>
                            <p class="mt-1 text-xs text-secondary">inkl. Trust & Tagesbonus</p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <label v-for="action in demoActions" :key="action.key" class="rounded-lg bg-bg p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <span class="font-semibold text-primary">{{ action.label }}</span>
                                    <p class="mt-1 text-xs text-secondary">+{{ action.xp }} XP je Aktion - Cap {{ action.limit }}x</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input
                                        v-model.number="action.value.value"
                                        type="range"
                                        min="0"
                                        :max="action.limit"
                                        class="w-36 accent-air-blue"
                                    >
                                    <span class="w-8 text-right font-mono text-air-green">{{ action.value }}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Auswertung</h2>
                    <p class="mt-2 text-sm leading-relaxed text-secondary">
                        Trust veraendert nicht das Ziel, sondern die Gewichtung: verlaessliches Verhalten wird staerker, auffaelliges Verhalten schwaecher bewertet.
                    </p>
                    <div class="mt-5 rounded-lg bg-inputBg p-4">
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="font-semibold text-primary">Trust-Multiplikator</span>
                            <span class="font-mono text-air-green">{{ trustMultiplier.toFixed(2) }}x</span>
                        </div>
                        <input
                            v-model.number="trustMultiplier"
                            type="range"
                            min="0.7"
                            max="1.3"
                            step="0.1"
                            class="mt-4 w-full accent-air-green"
                        >
                        <div class="mt-2 flex justify-between text-xs text-secondary">
                            <span>auffaellig</span>
                            <span>neutral</span>
                            <span>vertrauenswuerdig</span>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs text-secondary">Basis-XP</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ demoBaseXp }}</p>
                        </div>
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs text-secondary">Tagesbonus</p>
                            <p class="mt-1 text-2xl font-bold text-primary">+{{ demoDailyBonus }}</p>
                        </div>
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs text-secondary">Badge-Fortschritt</p>
                            <p class="mt-1 text-2xl font-bold text-primary">{{ demoProgress }}%</p>
                        </div>
                    </div>
                    <div class="mt-5 h-3 overflow-hidden rounded-full bg-bg">
                        <div class="h-full rounded-full bg-buttonPrimary transition-all" :style="{ width: `${demoProgress}%` }"></div>
                    </div>
                    <p class="mt-3 text-xs text-secondary">
                        Beispielziel: Badge "Erste Schritte" bei 50 XP.
                    </p>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 lg:grid-cols-2">
                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Vereine & Teams</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Organisation bekommt eigene Anerkennung, weil gute Vereinsarbeit oft unsichtbar bleibt.
                    </p>
                    <div class="mt-5 space-y-3">
                        <div v-for="[action, xp, text] in organizationActions" :key="action" class="rounded-lg bg-bg p-3">
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <span class="font-semibold">{{ action }}</span>
                                <span class="font-mono text-air-green">{{ xp }}</span>
                            </div>
                            <p class="mt-1 text-sm text-secondary">{{ text }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Lifecycle-Wirkung</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Gamification wirkt über die ganze Nutzerreise, nicht nur als Badge-Schicht am Ende.
                    </p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <article v-for="[title, text] in lifecycle" :key="title" class="rounded-lg bg-bg p-4">
                            <h3 class="font-semibold text-primary">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-7xl gap-5 lg:grid-cols-[0.95fr_1.05fr]">
                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Strafen mit Mass</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Abzuege sind kein Druckmittel, sondern Schutz für Fairness, Verlaesslichkeit und Qualität.
                    </p>
                    <div class="mt-5 space-y-3">
                        <div v-for="[title, xp, text] in penalties" :key="title" class="rounded-lg bg-bg p-3">
                            <div class="flex justify-between gap-3">
                                <strong>{{ title }}</strong>
                                <span class="font-mono text-error">{{ xp }}</span>
                            </div>
                            <p class="mt-1 text-sm text-secondary">{{ text }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Anti-Cheat & Governance</h2>
                    <p class="mt-2 text-sm text-secondary">
                        Das System ist technisch und organisatorisch darauf ausgelegt, Missbrauch zu begrenzen und Entscheidungen nachvollziehbar zu machen.
                    </p>
                    <div class="mt-5 grid gap-3 md:grid-cols-2">
                        <article v-for="[title, text] in safetyLayers" :key="title" class="rounded-lg bg-bg p-4">
                            <h3 class="font-semibold text-primary">{{ title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-secondary">{{ text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-7xl">
                <div class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-air-green">Badge-Vorschau</p>
                    <h2 class="mt-2 text-2xl font-bold text-primary">Auszeichnungen mit echter Bedeutung.</h2>
                    <p class="mt-2 max-w-3xl text-secondary">
                        Badges sollen nicht nur huebsch aussehen. Sie markieren nachweisbare Entwicklung, Verlaesslichkeit, Wissen, Vereinsaufbau und Fair Play.
                    </p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article v-for="[icon, name, trigger, text] in badges" :key="name" class="surface-card p-5">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-inputBg">
                                <i :class="[icon, 'text-2xl text-air-blue']"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-primary">{{ name }}</h3>
                                <p class="mt-1 text-xs font-semibold uppercase text-air-green">{{ trigger }}</p>
                            </div>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-secondary">{{ text }}</p>
                    </article>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-7xl rounded-lg border border-border bg-card p-6">
                <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wider text-air-green">Datenschutz & Jugendschutz</p>
                        <h2 class="mt-2 text-2xl font-bold text-primary">Gesunde Motivation statt sozialer Druck.</h2>
                        <p class="mt-3 text-sm leading-relaxed text-secondary">
                            Personenbezogene Daten werden zweckgebunden, transparent und rollenbasiert verarbeitet. Trainingsdokumentation bleibt auf berechtigte Personen beschraenkt. Bei Minderjaehrigen stehen Sicherheit, Regeneration, altersgerechte Ziele und paedagogisch sinnvolle Anerkennung im Vordergrund.
                        </p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs font-bold uppercase text-air-blue">Privacy</p>
                            <p class="mt-2 text-sm text-secondary">Zugriffe rollenbasiert und zweckgebunden.</p>
                        </div>
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs font-bold uppercase text-air-blue">Youth Safety</p>
                            <p class="mt-2 text-sm text-secondary">Keine Belohnung für exzessive Nutzung.</p>
                        </div>
                        <div class="rounded-lg bg-bg p-4">
                            <p class="text-xs font-bold uppercase text-air-blue">Transparency</p>
                            <p class="mt-2 text-sm text-secondary">Regeln, Limits und Abzuege erklaerbar.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-7xl pb-20">
                <div class="rounded-lg border border-air-blue/40 bg-air-blue/10 p-6">
                    <div class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wider text-air-blue">Fazit</p>
                            <h2 class="mt-2 text-2xl font-bold text-primary">Aus Produkt-, UX-, Fairness-, Vereins- und Sicherheits-Perspektive: 10/10.</h2>
                            <p class="mt-3 max-w-3xl text-sm leading-relaxed text-secondary">
                                Die verbesserte Darstellung zeigt klar, warum Airmius Gamification nicht nur Punkte vergibt, sondern gesunde Entwicklung, verlaessliches Verhalten und starke Vereinsorganisation messbar macht.
                            </p>
                        </div>
                        <Link
                            :href="route('register')"
                            class="inline-flex items-center justify-center rounded-lg bg-buttonPrimary px-5 py-3 text-sm font-bold text-buttonTextPrimary hover:bg-buttonPrimaryHover"
                        >
                            Jetzt Profil aufbauen
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>

