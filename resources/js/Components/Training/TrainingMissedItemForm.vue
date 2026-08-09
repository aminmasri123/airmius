<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
    athleteOptions: { type: Array, default: () => [] },
    form: { type: Object, required: true },
    selectedItem: { type: Object, default: null },
})

const emit = defineEmits(['submit'])
const { locale } = useI18n()
const copy = {
    de: { mark: 'Markiere', missed: 'als nicht gemacht und dokumentiere kurz warum.', athlete: 'Sportler', reason: 'Grund', sick: 'Krank', injured: 'Verletzt', noTime: 'Keine Zeit', postponed: 'Verschoben', skipped: 'Bewusst ausgelassen', other: 'Anderes', note: 'Notiz', placeholder: 'Optional: kurze Einordnung für dich oder den Trainer', save: 'Ausfall speichern' },
    en: { mark: 'Mark', missed: 'as missed and briefly document why.', athlete: 'Athlete', reason: 'Reason', sick: 'Sick', injured: 'Injured', noTime: 'No time', postponed: 'Postponed', skipped: 'Skipped intentionally', other: 'Other', note: 'Note', placeholder: 'Optional: brief context for you or the coach', save: 'Save missed session' },
    fr: { mark: 'Marque', missed: 'comme non réalisée et indique brièvement pourquoi.', athlete: 'Sportif', reason: 'Motif', sick: 'Malade', injured: 'Blessé', noTime: 'Pas le temps', postponed: 'Reportée', skipped: 'Omission volontaire', other: 'Autre', note: 'Note', placeholder: "Facultatif : courte précision pour toi ou l'entraîneur", save: "Enregistrer l'absence" },
    ar: { mark: 'حدّد', missed: 'كحصة غير منفذة وسجّل السبب باختصار.', athlete: 'الرياضي', reason: 'السبب', sick: 'مرض', injured: 'إصابة', noTime: 'لا يوجد وقت', postponed: 'مؤجلة', skipped: 'تم تجاوزها عمداً', other: 'سبب آخر', note: 'ملاحظة', placeholder: 'اختياري: توضيح قصير لك أو للمدرب', save: 'حفظ عدم التنفيذ' },
}
const c = (key) => (copy[String(locale.value || 'de').split('-')[0]] || copy.de)[key]
</script>

<template>
    <form class="space-y-4 p-4" @submit.prevent="emit('submit')">
        <p class="text-sm text-secondary">
            {{ c('mark') }} <span class="font-semibold text-primary">{{ selectedItem?.title }}</span> {{ c('missed') }}
        </p>
        <label v-if="athleteOptions.length > 1" class="block text-sm font-semibold text-primary">{{ c('athlete') }}
            <select v-model="form.user_id" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option v-for="athlete in athleteOptions" :key="athlete.id || 'self'" :value="athlete.id">{{ athlete.name }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">{{ c('reason') }}
            <select v-model="form.reason" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary">
                <option value="krank">{{ c('sick') }}</option>
                <option value="verletzt">{{ c('injured') }}</option>
                <option value="keine_zeit">{{ c('noTime') }}</option>
                <option value="verschoben">{{ c('postponed') }}</option>
                <option value="bewusst_ausgelassen">{{ c('skipped') }}</option>
                <option value="anderes">{{ c('other') }}</option>
            </select>
        </label>
        <label class="block text-sm font-semibold text-primary">{{ c('note') }}
            <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-border bg-inputBg px-3 py-2 text-primary" :placeholder="c('placeholder')" />
        </label>
        <button type="submit" class="w-full rounded-xl bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary disabled:opacity-60" :disabled="form.processing">
            {{ c('save') }}
        </button>
    </form>
</template>
