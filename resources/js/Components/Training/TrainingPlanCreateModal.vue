<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps({
    planWizardSteps: { type: Array, default: () => [] },
    planWizardStep: { type: Number, required: true },
    canOpenPlanWizardStep: { type: Function, required: true },
    goToPlanWizardStep: { type: Function, required: true },
    planForm: { type: Object, required: true },
    planTrainingTypes: { type: Array, default: () => [] },
    selectPlanTrainingType: { type: Function, required: true },
    planSport: { type: Object, required: true },
    exerciseLibrary: { type: Array, default: () => [] },
    sportLabel: { type: Function, required: true },
    teams: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    selectedTeamMembers: { type: Array, default: () => [] },
    togglePlanUser: { type: Function, required: true },
    applyPlanExerciseTemplate: { type: Function, required: true },
    setPlanImage: { type: Function, required: true },
    setPlanImageElement: { type: Function, required: true },
    previousPlanWizardStep: { type: Function, required: true },
    nextPlanWizardStep: { type: Function, required: true },
    planWizardCanContinue: { type: Boolean, default: false },
    submitPlan: { type: Function, required: true },
})

const { locale } = useI18n()

const copy = {
    de: {
        step: 'Schritt',
        step1: 'Schritt 1',
        step2: 'Schritt 2',
        step3: 'Schritt 3',
        step4: 'Schritt 4',
        basis: 'Plan-Basis',
        basisHelp: 'Erst nur das Wichtigste: Name und Rhythmus.',
        planName: 'Planname',
        planNamePlaceholder: 'z. B. 10k Aufbau, Gym Push/Pull, Comeback',
        cadence: 'Rhythmus',
        single: 'Einmalig',
        daily: 'Täglich',
        weekly: 'Wöchentlich',
        monthly: 'Monatlich',
        goalTime: 'Ziel und Zeitraum',
        goalTimeHelp: 'Alles hier ist optional, hilft aber bei Struktur und Auswertung.',
        planGoal: 'Ziel des Plans',
        planGoalPlaceholder: 'z. B. 10 km unter 45 Minuten, Muskelaufbau, Wiedereinstieg',
        phase: 'Trainingsphase',
        base: 'Grundlage',
        build: 'Aufbau',
        peak: 'Peak / Wettkampfnähe',
        recovery: 'Regeneration',
        rehab: 'Reha / Wiedereinstieg',
        level: 'Niveau',
        beginner: 'Einsteiger',
        intermediate: 'Fortgeschritten',
        advanced: 'Advanced',
        elite: 'Leistung',
        start: 'Start',
        end: 'Ende',
        weeks: 'Wochen',
        weeklySessions: 'Einheiten pro Woche',
        advancedPlanning: 'Erweiterte Planung',
        macrocycle: 'Makrozyklus',
        macrocyclePlaceholder: 'z. B. Sommeraufbau 2026',
        mesocycle: 'Mesozyklus',
        mesocyclePlaceholder: 'z. B. Kraftblock 1',
        deloadWeek: 'Deload-Woche',
        competitionDate: 'Wettkampf / Zieltermin',
        description: 'Beschreibung',
        release: 'Freigabe',
        releaseHelp: 'Wähle, ob der Plan sofort sichtbar ist und wer Zugriff bekommt.',
        status: 'Status',
        publishNow: 'Direkt freigeben',
        draft: 'Entwurf',
        permission: 'Berechtigung',
        readOnly: 'Nur lesen',
        write: 'Mit schreiben / verbessern',
        team: 'Team',
        noFullTeam: 'Kein komplettes Team',
        individualAthletes: 'Einzelne Sportler',
        selected: 'gewählt',
        noPeople: 'Keine einzelnen Sportler verfügbar.',
        teamSelection: 'Team-Auswahl umfasst',
        people: 'Personen.',
        firstSession: 'Erste Einheit',
        firstSessionHelp: 'Der Plan braucht eine erste Einheit. Weitere Einheiten kannst du danach hinzufügen.',
        quickStart: 'Schnellstart',
        sessionType: 'Einheitstyp',
        sessionTypeHelp: 'Hier geht es um die Art dieser Einheit, nicht um die Sportart.',
        title: 'Titel',
        titlePlaceholder: 'z. B. Long Run, Push Training, Technikdrill',
        appointment: 'Termin',
        week: 'Woche',
        duration: 'Dauer',
        distanceKm: 'Distanz km',
        load: 'Belastung',
        low: 'Locker',
        medium: 'Mittel',
        high: 'Hoch',
        test: 'Test',
        focus: 'Fokus',
        focusPlaceholder: 'z. B. Technik, Zone 2, Explosivität',
        mediaTasks: 'Medien und Aufgaben',
        todoList: 'Todo-Liste',
        todoPlaceholder: 'Eine Aufgabe pro Zeile',
        videoLink: 'Video-Link',
        image: 'Bild',
        back: 'Zurück',
        next: 'Weiter',
        savePlan: 'Plan speichern',
    },
    en: {
        step: 'Step',
        step1: 'Step 1',
        step2: 'Step 2',
        step3: 'Step 3',
        step4: 'Step 4',
        basis: 'Plan basics',
        basisHelp: 'Start with the essentials: name and rhythm.',
        planName: 'Plan name',
        planNamePlaceholder: 'e.g. 10k build, gym push/pull, comeback',
        cadence: 'Rhythm',
        single: 'Once',
        daily: 'Daily',
        weekly: 'Weekly',
        monthly: 'Monthly',
        goalTime: 'Goal and timeframe',
        goalTimeHelp: 'Everything here is optional, but helps structure and analysis.',
        planGoal: 'Plan goal',
        planGoalPlaceholder: 'e.g. 10 km under 45 minutes, muscle gain, return to sport',
        phase: 'Training phase',
        base: 'Base',
        build: 'Build',
        peak: 'Peak / competition prep',
        recovery: 'Recovery',
        rehab: 'Rehab / return',
        level: 'Level',
        beginner: 'Beginner',
        intermediate: 'Intermediate',
        advanced: 'Advanced',
        elite: 'Performance',
        start: 'Start',
        end: 'End',
        weeks: 'Weeks',
        weeklySessions: 'Sessions per week',
        advancedPlanning: 'Advanced planning',
        macrocycle: 'Macrocycle',
        macrocyclePlaceholder: 'e.g. summer build 2026',
        mesocycle: 'Mesocycle',
        mesocyclePlaceholder: 'e.g. strength block 1',
        deloadWeek: 'Deload week',
        competitionDate: 'Competition / target date',
        description: 'Description',
        release: 'Release',
        releaseHelp: 'Choose whether the plan is visible immediately and who can access it.',
        status: 'Status',
        publishNow: 'Publish now',
        draft: 'Draft',
        permission: 'Permission',
        readOnly: 'Read only',
        write: 'Write / improve',
        team: 'Team',
        noFullTeam: 'No full team',
        individualAthletes: 'Individual athletes',
        selected: 'selected',
        noPeople: 'No individual athletes available.',
        teamSelection: 'Team selection includes',
        people: 'people.',
        firstSession: 'First session',
        firstSessionHelp: 'The plan needs a first session. You can add more sessions later.',
        quickStart: 'Quick start',
        sessionType: 'Session type',
        sessionTypeHelp: 'This is about the type of this session, not the sport.',
        title: 'Title',
        titlePlaceholder: 'e.g. long run, push training, technique drill',
        appointment: 'Date',
        week: 'Week',
        duration: 'Duration',
        distanceKm: 'Distance km',
        load: 'Load',
        low: 'Easy',
        medium: 'Medium',
        high: 'High',
        test: 'Test',
        focus: 'Focus',
        focusPlaceholder: 'e.g. technique, zone 2, explosiveness',
        mediaTasks: 'Media and tasks',
        todoList: 'Todo list',
        todoPlaceholder: 'One task per line',
        videoLink: 'Video link',
        image: 'Image',
        back: 'Back',
        next: 'Next',
        savePlan: 'Save plan',
    },
    fr: {
        step: 'Étape',
        step1: 'Étape 1',
        step2: 'Étape 2',
        step3: 'Étape 3',
        step4: 'Étape 4',
        basis: 'Base du plan',
        basisHelp: "Commence par l'essentiel : nom et rythme.",
        planName: 'Nom du plan',
        planNamePlaceholder: 'p. ex. préparation 10 km, gym push/pull, reprise',
        cadence: 'Rythme',
        single: 'Unique',
        daily: 'Quotidien',
        weekly: 'Hebdomadaire',
        monthly: 'Mensuel',
        goalTime: 'Objectif et période',
        goalTimeHelp: "Tout est facultatif, mais aide la structure et l'analyse.",
        planGoal: 'Objectif du plan',
        planGoalPlaceholder: 'p. ex. 10 km sous 45 min, prise de muscle, reprise',
        phase: 'Phase',
        base: 'Base',
        build: 'Construction',
        peak: 'Pic / compétition',
        recovery: 'Récupération',
        rehab: 'Rééducation / reprise',
        level: 'Niveau',
        beginner: 'Débutant',
        intermediate: 'Intermédiaire',
        advanced: 'Avancé',
        elite: 'Performance',
        start: 'Début',
        end: 'Fin',
        weeks: 'Semaines',
        weeklySessions: 'Séances par semaine',
        advancedPlanning: 'Planification avancée',
        macrocycle: 'Macrocycle',
        macrocyclePlaceholder: 'p. ex. préparation été 2026',
        mesocycle: 'Mésocycle',
        mesocyclePlaceholder: 'p. ex. bloc force 1',
        deloadWeek: 'Semaine allégée',
        competitionDate: 'Compétition / date cible',
        description: 'Description',
        release: 'Publication',
        releaseHelp: 'Choisis si le plan est visible immédiatement et qui y accède.',
        status: 'Statut',
        publishNow: 'Publier maintenant',
        draft: 'Brouillon',
        permission: 'Permission',
        readOnly: 'Lecture seule',
        write: 'Écrire / améliorer',
        team: 'Équipe',
        noFullTeam: 'Aucune équipe complète',
        individualAthletes: 'Sportifs individuels',
        selected: 'sélectionnés',
        noPeople: 'Aucun sportif individuel disponible.',
        teamSelection: "La sélection d'équipe comprend",
        people: 'personnes.',
        firstSession: 'Première séance',
        firstSessionHelp: "Le plan a besoin d'une première séance. Tu peux en ajouter ensuite.",
        quickStart: 'Démarrage rapide',
        sessionType: 'Type de séance',
        sessionTypeHelp: "Il s'agit du type de séance, pas du sport.",
        title: 'Titre',
        titlePlaceholder: 'p. ex. sortie longue, push training, drill technique',
        appointment: 'Date',
        week: 'Semaine',
        duration: 'Durée',
        distanceKm: 'Distance km',
        load: 'Charge',
        low: 'Facile',
        medium: 'Moyenne',
        high: 'Élevée',
        test: 'Test',
        focus: 'Focus',
        focusPlaceholder: 'p. ex. technique, zone 2, explosivité',
        mediaTasks: 'Médias et tâches',
        todoList: 'Liste de tâches',
        todoPlaceholder: 'Une tâche par ligne',
        videoLink: 'Lien vidéo',
        image: 'Image',
        back: 'Retour',
        next: 'Suivant',
        savePlan: 'Enregistrer le plan',
    },
    ar: {},
}

// Keep Arabic copy explicit here so the wizard never falls back to a damaged
// or stale generated catalog while the selected locale changes.
Object.assign(copy.ar, {
    step: 'خطوة',
    step1: 'الخطوة 1',
    step2: 'الخطوة 2',
    step3: 'الخطوة 3',
    step4: 'الخطوة 4',
    basis: 'أساس الخطة',
    basisHelp: 'ابدأ بالأهم: الاسم والإيقاع.',
    planName: 'اسم الخطة',
    planNamePlaceholder: 'مثال: إعداد 10 كم، تمارين دفع/سحب، عودة للتدريب',
    cadence: 'الإيقاع',
    single: 'مرة واحدة',
    daily: 'يومي',
    weekly: 'أسبوعي',
    monthly: 'شهري',
    goalTime: 'الهدف والفترة الزمنية',
    goalTimeHelp: 'كل ما هنا اختياري، لكنه يساعد في التنظيم والتحليل.',
    planGoal: 'هدف الخطة',
    planGoalPlaceholder: 'مثال: 10 كم في أقل من 45 دقيقة، بناء العضلات، العودة للتدريب',
    phase: 'مرحلة التدريب',
    base: 'أساس',
    build: 'بناء',
    peak: 'ذروة / استعداد للمنافسة',
    recovery: 'استشفاء',
    rehab: 'تأهيل / عودة',
    level: 'المستوى',
    beginner: 'مبتدئ',
    intermediate: 'متوسط',
    advanced: 'متقدم',
    elite: 'أداء عالٍ',
    start: 'البداية',
    end: 'النهاية',
    weeks: 'أسابيع',
    weeklySessions: 'الجلسات أسبوعياً',
    advancedPlanning: 'تخطيط متقدم',
    macrocycle: 'الدورة الكبرى',
    macrocyclePlaceholder: 'مثال: إعداد صيف 2026',
    mesocycle: 'الدورة المتوسطة',
    mesocyclePlaceholder: 'مثال: كتلة قوة 1',
    deloadWeek: 'أسبوع تخفيف الحمل',
    competitionDate: 'المنافسة / التاريخ المستهدف',
    description: 'الوصف',
    release: 'النشر والمشاركة',
    releaseHelp: 'اختر ما إذا كانت الخطة ستظهر فوراً ومن يمكنه الوصول إليها.',
    status: 'الحالة',
    publishNow: 'نشر الآن',
    draft: 'مسودة',
    permission: 'الصلاحية',
    readOnly: 'قراءة فقط',
    write: 'كتابة / تحسين',
    team: 'الفريق',
    noFullTeam: 'لا يوجد فريق كامل',
    individualAthletes: 'رياضيون أفراد',
    selected: 'محدد',
    noPeople: 'لا يوجد رياضيون أفراد متاحون.',
    teamSelection: 'يشمل اختيار الفريق',
    people: 'أشخاص.',
    firstSession: 'الجلسة الأولى',
    firstSessionHelp: 'تحتاج الخطة إلى جلسة أولى. يمكنك إضافة جلسات أخرى لاحقاً.',
    quickStart: 'بدء سريع',
    sessionType: 'نوع الجلسة',
    sessionTypeHelp: 'المقصود هنا نوع الجلسة، وليس الرياضة نفسها.',
    title: 'العنوان',
    titlePlaceholder: 'مثال: جري طويل، تمارين دفع، تدريب تقني',
    appointment: 'الموعد',
    week: 'الأسبوع',
    duration: 'المدة',
    distanceKm: 'المسافة بالكيلومتر',
    load: 'الحمل',
    low: 'خفيف',
    medium: 'متوسط',
    high: 'عالٍ',
    test: 'اختبار',
    focus: 'التركيز',
    focusPlaceholder: 'مثال: تقنية، المنطقة 2، القوة الانفجارية',
    mediaTasks: 'الوسائط والمهام',
    todoList: 'قائمة المهام',
    todoPlaceholder: 'مهمة واحدة في كل سطر',
    videoLink: 'رابط الفيديو',
    image: 'صورة',
    back: 'رجوع',
    next: 'التالي',
    savePlan: 'حفظ الخطة',
})

const labels = computed(() => copy[locale.value] || copy.de)
const c = (key) => labels.value[key] || copy.de[key] || key
const cadenceOptions = computed(() => [
    { value: 'single', label: c('single'), icon: 'las la-calendar-day' },
    { value: 'daily', label: c('daily'), icon: 'las la-redo' },
    { value: 'weekly', label: c('weekly'), icon: 'las la-calendar-week' },
    { value: 'monthly', label: c('monthly'), icon: 'las la-calendar-alt' },
])
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="submitPlan">
        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
            <button
                v-for="(step, index) in planWizardSteps"
                :key="step.label"
                type="button"
                class="rounded-2xl border p-3 text-left transition"
                :class="[
                    planWizardStep === index ? 'border-air-blue bg-air-blue text-white shadow-lg shadow-air-blue/20' : 'border-border bg-card text-primary hover:bg-muted',
                    !canOpenPlanWizardStep(index) ? 'cursor-not-allowed opacity-50' : '',
                ]"
                :disabled="!canOpenPlanWizardStep(index)"
                @click="goToPlanWizardStep(index)"
            >
                <span class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide" :class="planWizardStep === index ? 'text-white/80' : 'text-secondary'">
                    <i :class="step.icon"></i>
                    {{ c('step') }} {{ index + 1 }}
                </span>
                <span class="mt-2 block text-sm font-semibold">{{ step.label }}</span>
                <span class="hidden text-xs opacity-80 sm:block">{{ step.hint }}</span>
            </button>
        </div>

        <section v-if="planWizardStep === 0" class="rounded-2xl border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('step1') }}</p>
            <h3 class="mt-1 text-lg font-semibold text-primary">{{ c('basis') }}</h3>
            <p class="mt-1 text-sm text-secondary">{{ c('basisHelp') }}</p>

            <div class="mt-4 space-y-4">
                <label class="block text-sm font-semibold text-primary">{{ c('planName') }}
                    <input v-model="planForm.title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('planNamePlaceholder')" required />
                </label>

                <div>
                    <p class="text-sm font-semibold text-primary">{{ c('cadence') }}</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="option in cadenceOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                            :class="planForm.cadence === option.value ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                            @click="planForm.cadence = option.value"
                        >
                            <i :class="option.icon" class="mr-2"></i>{{ option.label }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="planWizardStep === 1" class="rounded-2xl border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('step2') }}</p>
            <h3 class="mt-1 text-lg font-semibold text-primary">{{ c('goalTime') }}</h3>
            <p class="mt-1 text-sm text-secondary">{{ c('goalTimeHelp') }}</p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-semibold text-primary md:col-span-2">{{ c('planGoal') }}
                    <input v-model="planForm.goal" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('planGoalPlaceholder')" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('phase') }}
                    <select v-model="planForm.phase" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="base">{{ c('base') }}</option>
                        <option value="build">{{ c('build') }}</option>
                        <option value="peak">{{ c('peak') }}</option>
                        <option value="recovery">{{ c('recovery') }}</option>
                        <option value="rehab">{{ c('rehab') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('level') }}
                    <select v-model="planForm.level" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="beginner">{{ c('beginner') }}</option>
                        <option value="intermediate">{{ c('intermediate') }}</option>
                        <option value="advanced">{{ c('advanced') }}</option>
                        <option value="elite">{{ c('elite') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('start') }}
                    <input v-model="planForm.starts_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('end') }}
                    <input v-model="planForm.ends_on" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('weeks') }}
                    <input v-model="planForm.weeks" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('weeklySessions') }}
                    <input v-model="planForm.weekly_sessions" type="number" min="1" max="21" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
            </div>

            <details class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                <summary class="cursor-pointer text-sm font-semibold text-primary">{{ c('advancedPlanning') }}</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-semibold text-primary">{{ c('macrocycle') }}
                        <input v-model="planForm.macrocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('macrocyclePlaceholder')" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ c('mesocycle') }}
                        <input v-model="planForm.mesocycle" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('mesocyclePlaceholder')" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ c('deloadWeek') }}
                        <input v-model="planForm.deload_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ c('competitionDate') }}
                        <input v-model="planForm.competition_date" type="date" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary md:col-span-2">{{ c('description') }}
                        <textarea v-model="planForm.description" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                    </label>
                </div>
            </details>
        </section>

        <section v-if="planWizardStep === 2" class="rounded-2xl border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('step3') }}</p>
            <h3 class="mt-1 text-lg font-semibold text-primary">{{ c('release') }}</h3>
            <p class="mt-1 text-sm text-secondary">{{ c('releaseHelp') }}</p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <label class="block text-sm font-semibold text-primary">{{ c('status') }}
                    <select v-model="planForm.status" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="published">{{ c('publishNow') }}</option>
                        <option value="draft">{{ c('draft') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('permission') }}
                    <select v-model="planForm.share_permission" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="read">{{ c('readOnly') }}</option>
                        <option value="write">{{ c('write') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary md:col-span-2">{{ c('team') }}
                    <select v-model="planForm.team_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="">{{ c('noFullTeam') }}</option>
                        <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-primary">{{ c('individualAthletes') }}</p>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-secondary">{{ planForm.user_ids.length }} {{ c('selected') }}</span>
                </div>
                <div class="mt-3 grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2">
                    <label v-for="person in people" :key="person.id" class="flex items-center gap-2 rounded-xl border border-border bg-bg/40 px-3 py-2 text-sm text-primary">
                        <input type="checkbox" class="rounded border-border bg-inputBg" :checked="planForm.user_ids.map(Number).includes(Number(person.id))" @change="togglePlanUser(person.id)" />
                        <span class="truncate">{{ person.name }}</span>
                    </label>
                    <p v-if="!people.length" class="text-sm text-secondary">{{ c('noPeople') }}</p>
                </div>
                <p v-if="selectedTeamMembers.length" class="mt-3 text-xs text-secondary">{{ c('teamSelection') }} {{ selectedTeamMembers.length }} {{ c('people') }}</p>
            </div>
        </section>

        <section v-if="planWizardStep === 3" class="rounded-2xl border border-border bg-card p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ c('step4') }}</p>
            <h3 class="mt-1 text-lg font-semibold text-primary">{{ c('firstSession') }}</h3>
            <p class="mt-1 text-sm text-secondary">{{ c('firstSessionHelp') }}</p>

            <div class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-secondary">{{ c('quickStart') }}</p>
                <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                    <button v-for="template in exerciseLibrary" :key="template.title" type="button" class="shrink-0 rounded-xl border border-border bg-bg/50 px-3 py-2 text-left text-xs text-primary hover:bg-muted" @click="applyPlanExerciseTemplate(template)">
                        <span class="block font-semibold">{{ template.title }}</span>
                        <span class="text-secondary">{{ sportLabel(template.sport_type) }} - {{ template.focus }}</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <p class="text-sm font-semibold text-primary">{{ c('sessionType') }}</p>
                    <p class="mt-1 text-xs text-secondary">{{ c('sessionTypeHelp') }}</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <button
                            v-for="type in planTrainingTypes"
                            :key="type.key"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left text-sm font-semibold transition"
                            :class="planForm.item_training_type === type.key ? 'border-air-blue bg-air-blue/10 text-air-blue' : 'border-border bg-inputBg text-primary hover:bg-muted'"
                            @click="selectPlanTrainingType(type.key)"
                        >
                            <span :class="['mb-2 block h-1.5 w-8 rounded-full', type.accent]"></span>
                            <i :class="type.icon" class="mr-2"></i>{{ type.label }}
                        </button>
                    </div>
                </div>
                <label class="block text-sm font-semibold text-primary md:col-span-2">{{ c('title') }}
                    <input v-model="planForm.item_title" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('titlePlaceholder')" required />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('appointment') }}
                    <input v-model="planForm.item_scheduled_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('week') }}
                    <input v-model="planForm.item_week" type="number" min="1" max="104" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('duration') }}
                    <input v-model="planForm.item_duration_minutes" type="number" min="0" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label v-if="['laufen', 'cycling', 'schwimmen', 'fussball'].includes(planForm.item_sport_type)" class="block text-sm font-semibold text-primary">{{ c('distanceKm') }}
                    <input v-model="planForm.item_distance_km" type="number" min="0" step="0.01" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('load') }}
                    <select v-model="planForm.item_load" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary">
                        <option value="low">{{ c('low') }}</option>
                        <option value="medium">{{ c('medium') }}</option>
                        <option value="high">{{ c('high') }}</option>
                        <option value="test">{{ c('test') }}</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold text-primary">{{ c('focus') }}
                    <input v-model="planForm.item_focus" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('focusPlaceholder')" />
                </label>
                <label v-for="metric in planSport.metrics" :key="metric" class="block text-sm font-semibold text-primary">
                    {{ metric }}
                    <input v-model="planForm.item_metrics[metric]" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                </label>
            </div>

            <details class="mt-4 rounded-2xl border border-border bg-inputBg/40 p-3">
                <summary class="cursor-pointer text-sm font-semibold text-primary">{{ c('mediaTasks') }}</summary>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm font-semibold text-primary md:col-span-2">{{ c('todoList') }}
                        <textarea v-model="planForm.item_todos" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" :placeholder="c('todoPlaceholder')" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ c('videoLink') }}
                        <input v-model="planForm.item_video_url" type="url" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary" />
                    </label>
                    <label class="block text-sm font-semibold text-primary">{{ c('image') }}
                        <input :ref="setPlanImageElement" type="file" accept="image/*" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-3 text-primary file:mr-3 file:rounded-md file:border-0 file:bg-buttonPrimary file:px-3 file:py-1 file:text-sm file:font-semibold file:text-buttonTextPrimary" @change="setPlanImage" />
                    </label>
                </div>
            </details>
        </section>

        <div class="sticky bottom-0 -mx-4 -mb-4 flex items-center justify-between gap-3 border-t border-border bg-bg/95 p-4 backdrop-blur">
            <button type="button" class="rounded-xl border border-border px-4 py-3 text-sm font-semibold text-primary disabled:opacity-40" :disabled="planWizardStep === 0" @click="previousPlanWizardStep">
                Zurück
            </button>
            <button v-if="planWizardStep < planWizardSteps.length - 1" type="button" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-50" :disabled="!planWizardCanContinue" @click="nextPlanWizardStep">
                Weiter
            </button>
            <button v-else type="submit" class="rounded-xl bg-buttonPrimary px-5 py-3 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="planForm.processing || !planWizardCanContinue">
                Plan speichern
            </button>
        </div>
    </form>
</template>
