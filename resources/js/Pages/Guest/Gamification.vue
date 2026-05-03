<script setup>
import Nav from '@/Components/Guest/Nav.vue'
import Subnav from '@/Components/Guest/Subnav.vue'
import Footer from '@/Components/Guest/Footer.vue'
import SeoHead from '@/Components/Guest/SeoHead.vue'

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
})

const coreLoops = [
    ['Core Loop', 'Aktion -> XP -> Level -> Rang -> Sichtbarkeit'],
    ['Social Loop', 'Skills, Bestätigungen, Empfehlungen und hilfreiche Beiträge stärken Vertrauen.'],
    ['Real-Life Loop', 'Training, Check-ins, Events und Vereinsaktivität werden digital nachvollziehbar.'],
]

const playerActions = [
    ['Sportart hinzufügen', '+15 XP', 'Strukturiert das sportliche Profil und erzeugt passende Skills.'],
    ['Skill bearbeiten', '+3 XP', 'Pflege von Kompetenz, Notiz oder Sichtbarkeit. Maximal 3x täglich.'],
    ['Skill-Level verbessern', '+8 XP', 'Anerkennt echte persönliche Entwicklung. Maximal 3x täglich.'],
    ['Skill-Bestätigung', '+10 XP', 'Bestätigung durch andere Nutzer, Trainer oder Vereine. Maximal 3x täglich.'],
    ['Training zusagen', '+5 XP', 'Zusage zu einer Trainingseinheit.'],
    ['Training Check-in', '+2 XP', 'Dokumentierte Teilnahme, z.B. QR-Code oder GPS.'],
    ['Informativer Beitrag', '+5 XP', 'Wissen, Training, Taktik, Analyse oder Erfahrung mit Mehrwert.'],
    ['Tägliche sinnvolle Aktivität', '+2 XP', 'Nur wenn wirklich etwas Sinnvolles passiert, nicht nur Login.'],
]

const clubActions = [
    ['Training erstellen', '+5 XP'],
    ['Durchgeführtes Training', '+10 XP'],
    ['Regelmäßiger Trainingsbetrieb', '+20 XP/Woche'],
    ['Event erstellen', '+10 XP'],
    ['Durchgeführtes Event', '+25 XP'],
    ['Großevent oder Turnier', '+40 XP'],
    ['Neues aktives Mitglied', '+5 bis +20 XP'],
    ['Vollständiges Vereinsprofil', '+15 XP einmalig'],
    ['Informative Vereinsnews', '+8 XP'],
]

const trainerActions = [
    'Konstruktives Feedback und strukturierte Leistungsbewertungen',
    'Planung, Durchführung und Nachbereitung von Trainings',
    'Individuelle Trainingspläne und langfristige Entwicklungsbegleitung',
    'Bestätigung von Teilnahmen und Fortschritten',
    'Wissenstransfer durch Methoden, Analysen und Übungsmaterialien',
    'Unterstützung neuer Mitglieder und respektvolle Community-Führung',
]

const penalties = [
    ['No-Show', '-5 XP', 'Unentschuldigtes Fernbleiben trotz Anmeldung.'],
    ['Falsche Bestätigung', '-50 XP', 'Manipulierte oder unwahre Bestätigung.'],
    ['Spam oder Missbrauch', '-30 XP', 'Missbrauch, Spam oder minderwertige Wiederholung.'],
    ['Abgelehnte Empfehlung', '-5 XP', 'Qualitätssicherung bei Profil-Empfehlungen.'],
    ['Übermäßige Nutzung', '-5 XP', 'XP kann bei exzessiver Nutzung reduziert oder pausiert werden.'],
]

const ranks = [
    ['Sportler', 'Rookie', 'Level 1-2'],
    ['Sportler', 'Amateur', 'Level 3-4'],
    ['Sportler', 'Athlete', 'Level 5-6'],
    ['Sportler', 'Advanced', 'Level 7-8'],
    ['Sportler', 'Elite', 'Level 9-10'],
    ['Trainer', 'Assistant -> Master Trainer', 'Wächst mit Feedback, Planung, Betreuung und Wissenstransfer'],
    ['Vereine', 'Local Club -> Elite Club', 'Wächst mit Trainingsbetrieb, Events, Kultur und Organisation'],
]

const antiSpam = [
    'Skill-Updates: maximal 3x täglich mit XP',
    'Empfehlungen: maximal 2x täglich mit XP',
    'Trainingsaktivitäten: maximal 3x täglich mit XP',
    'Bestätigungen: maximal 3x täglich mit XP',
    'Beiträge/Inhalte: maximal 3x täglich mit XP',
    'Nach definierten Nutzungszeiten kann XP reduziert oder pausiert werden',
]

const antiCheat = [
    ['Verifizierte Aktionen', 'Check-ins über QR-Code, GPS oder Trainerbestätigung.'],
    ['Mehrstufige Validierung', 'Skills, Empfehlungen und Beiträge können bestätigt, moderiert oder geprüft werden.'],
    ['Trust Score', 'Niedrige Werte reduzieren XP, hohe Werte verstärken vertrauenswürdiges Verhalten.'],
    ['Mustererkennung', 'Ungewöhnlich hohe Frequenz und inhaltsarme Wiederholung werden erkannt.'],
    ['Serverseitige Berechnung', 'XP entsteht ausschließlich auf dem Server und nicht im Endgerät.'],
    ['Audit Logs', 'Relevante Aktionen bleiben für Administrationszwecke nachvollziehbar.'],
]
</script>

<template>
    <SeoHead
        title="Gamification System für Sportler, Trainer und Vereine"
        description="Das Airmius Gamification-System macht sportliche Entwicklung, Engagement und Vereinsaktivitaet sichtbar, fair und nachvollziehbar."
    />

    <div class="min-h-screen bg-bg text-primary">
        <Nav :canLogin="canLogin" />
        <Subnav />

        <main class="px-4 pt-36 md:pt-44">
            <section class="mx-auto max-w-6xl">
                <span class="text-sm font-semibold uppercase tracking-wider text-air-blue">Offizielles System</span>
                <h1 class="mt-3 max-w-4xl font-heading text-4xl font-900 leading-tight sm:text-5xl">
                    Gamification für gesunde sportliche Entwicklung.
                </h1>
                <p class="mt-5 max-w-3xl text-lg leading-relaxed text-secondary">
                    Airmius belohnt nicht bloß Aktivität, sondern nachvollziehbare Entwicklung: Training, Zuverlässigkeit, Kompetenz, Wissen, Fairness und Vereinsarbeit. Das System ist rollenbasiert, zweckgebunden und so gestaltet, dass besonders bei jungen Sportlern kein ungesunder Leistungsdruck entsteht.
                </p>
            </section>

            <section class="mx-auto mt-12 grid max-w-6xl gap-5 lg:grid-cols-3">
                <article v-for="[title, text] in coreLoops" :key="title" class="surface-card p-5">
                    <h2 class="text-lg font-bold">{{ title }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-secondary">{{ text }}</p>
                </article>
            </section>

            <section class="mx-auto mt-12 max-w-6xl rounded-xl border border-border bg-card p-6">
                <h2 class="text-2xl font-bold">Level-System</h2>
                <p class="mt-3 leading-relaxed text-secondary">
                    Die XP-Schwelle steigt exponentiell. Für den nächsten Level-Abschnitt gilt:
                </p>
                <div class="mt-4 rounded-lg bg-inputBg p-4 font-mono text-sm text-air-green">
                    XP_needed = 250 * level^1.7
                </div>
                <p class="mt-4 leading-relaxed text-secondary">
                    Niedrige Level geben schnelle Erfolgserlebnisse. Höhere Level erfordern langfristige Aktivität, echte Teilnahme, Vertrauen und Qualität. Fortschritt entsteht nicht durch reine Nutzungsdauer, sondern durch sinnvolle Aktionen.
                </p>
            </section>

            <section class="mx-auto mt-12 grid max-w-6xl gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">XP-Aktionen für Sportler</h2>
                    <div class="mt-5 overflow-hidden rounded-lg border border-border">
                        <div v-for="[action, xp, text] in playerActions" :key="action" class="grid gap-3 border-b border-border bg-bg p-4 last:border-b-0 md:grid-cols-[180px_100px_1fr]">
                            <div class="font-semibold">{{ action }}</div>
                            <div class="font-mono text-air-green">{{ xp }}</div>
                            <div class="text-sm text-secondary">{{ text }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Streaks</h2>
                    <p class="mt-3 text-sm leading-relaxed text-secondary">
                        Ein Login allein reicht nicht. Eine Streak zählt nur, wenn eine sinnvolle Aktion stattfindet.
                    </p>
                    <div class="mt-5 grid gap-3">
                        <div class="rounded-lg bg-bg p-4"><strong>3 Tage:</strong> +5 Bonus XP</div>
                        <div class="rounded-lg bg-bg p-4"><strong>7 Tage:</strong> +10 Bonus XP</div>
                        <div class="rounded-lg bg-bg p-4"><strong>14 Tage:</strong> +20 Bonus XP</div>
                        <div class="rounded-lg bg-bg p-4"><strong>30 Tage:</strong> +40 Bonus XP</div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-6xl gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Trainer</h2>
                    <ul class="mt-5 space-y-3 text-sm leading-relaxed text-secondary">
                        <li v-for="item in trainerActions" :key="item">- {{ item }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Vereine</h2>
                    <div class="mt-5 space-y-3">
                        <div v-for="[action, xp] in clubActions" :key="action" class="flex justify-between gap-4 rounded-lg bg-bg p-3 text-sm">
                            <span>{{ action }}</span>
                            <span class="font-mono text-air-green">{{ xp }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-6xl gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Trust Score</h2>
                    <p class="mt-3 leading-relaxed text-secondary">
                        Der Trust Score bewertet Vertrauenswürdigkeit. Er beeinflusst den XP-Multiplikator zwischen <strong>0,7</strong> und <strong>1,3</strong>. Positive, verlässliche Aktionen erhöhen Trust; Manipulation, Spam oder No-Shows senken ihn.
                    </p>
                </div>

                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Strafen</h2>
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
            </section>

            <section class="mx-auto mt-12 max-w-6xl rounded-xl border border-border bg-card p-6">
                <h2 class="text-2xl font-bold">Ränge</h2>
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="[actor, rank, level] in ranks" :key="`${actor}-${rank}`" class="rounded-lg border border-border bg-bg p-4">
                        <p class="text-xs font-semibold uppercase text-secondary">{{ actor }}</p>
                        <h3 class="mt-1 font-bold">{{ rank }}</h3>
                        <p class="mt-1 text-sm text-secondary">{{ level }}</p>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 grid max-w-6xl gap-5 lg:grid-cols-2">
                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Anti-Spam-Limits</h2>
                    <ul class="mt-5 space-y-3 text-sm leading-relaxed text-secondary">
                        <li v-for="item in antiSpam" :key="item">- {{ item }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Anti-Cheat</h2>
                    <div class="mt-5 space-y-3">
                        <div v-for="[title, text] in antiCheat" :key="title" class="rounded-lg bg-bg p-4">
                            <h3 class="font-semibold">{{ title }}</h3>
                            <p class="mt-1 text-sm text-secondary">{{ text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto mt-12 max-w-6xl pb-20">
                <div class="rounded-xl border border-border bg-card p-6">
                    <h2 class="text-2xl font-bold">Datenschutz und Jugendschutz</h2>
                    <p class="mt-3 leading-relaxed text-secondary">
                        Die Verarbeitung personenbezogener Daten erfolgt zweckgebunden, transparent und nur im notwendigen Umfang. Rollenbasierte Zugriffe beschränken Trainingsdokumentation auf berechtigte Personen. Das System vermeidet übermäßige Nutzungsanreize, unangemessenen sozialen Druck und ungesunde Leistungsanreize. Besonders bei Minderjährigen stehen sichere Entwicklung, Regeneration und altersgerechte Ziele im Vordergrund.
                    </p>
                </div>
            </section>
        </main>

        <Footer />
    </div>
</template>
