<script setup>
import AppLayout from '@/Components/Auth/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {computed,ref} from 'vue'
import LearningParticipationDialog from '@/Components/Learning/LearningParticipationDialog.vue'
import { useI18n } from 'vue-i18n'
import LearningCourseCover from '@/Components/Learning/LearningCourseCover.vue'
import { learningText } from '@/support/learningExperience'

defineOptions({ layout: AppLayout })

const props = defineProps({
    enrollments: { type: Array, default: () => [] },
})

const { t, locale } = useI18n()
const ex = key => learningText(locale.value,key)
const view=ref('active'), target=ref(null), busy=ref(false), actionError=ref('')
const visibleEnrollments=computed(()=>props.enrollments.filter(e=>view.value==='hidden'?Boolean(e.hidden_at):!e.hidden_at && (view.value==='active'?e.status==='active':view.value==='left'?e.status==='left':!['active','left'].includes(e.status))))
const ask=(enrollment,action)=>{actionError.value='';target.value={id:enrollment.id,title:enrollment.course.title,action}}
const confirm=()=>router.put(route('auth.learning.enrollments.participation',target.value.id),{action:target.value.action},{preserveScroll:true,onStart:()=>busy.value=true,onFinish:()=>busy.value=false,onSuccess:()=>target.value=null,onError:errors=>actionError.value=Object.values(errors).join(' ')})
const continueUrl = enrollment => enrollment.next_lesson
    ? `${enrollment.course.show_url}?lesson=${enrollment.next_lesson.id}#learning-room`
    : `${enrollment.course.show_url}#learning-room`
const tx = (value, params = {}) => t(value, params)

const statusLabel = (status) => ({
    active: tx('Aktiv'),
    cancelled: tx('guest.learning.status.cancelled'),
    refunded: tx('Erstattet'),
}[status] || status)

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar' : (locale.value === 'fr' ? 'fr-FR' : (locale.value === 'en' ? 'en-US' : 'de-DE')), { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value))
    : '-'
</script>

<template>
    <Head :title="tx('Meine Kurse')" />

    <div class="space-y-6">
        <section class="surface-card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-air-blue">{{ tx('Lernplattform') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">{{ ex('mine') }}</h1>
            <Link :href="route('guest.e-learning')" class="mt-3 inline-flex rounded-lg border border-border px-4 py-2 text-sm font-semibold">{{ ex('discover') }}</Link>
            <p class="mt-2 text-sm text-secondary">{{ tx('Alle freigeschalteten Kurse, Fortschritte und Zertifikate an einem Ort.') }}</p>
            <div class="mt-4 flex flex-wrap gap-2"><button v-for="item in ['active','left','hidden','history']" :key="item" type="button" :aria-pressed="view===item" class="rounded-lg border border-border px-3 py-2" :class="view===item?'bg-muted font-bold':''" @click="view=item">{{ ex(item) }}</button></div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <article v-for="enrollment in visibleEnrollments" :key="enrollment.id" class="surface-card overflow-hidden">
                <div class="grid gap-4 p-5 md:grid-cols-[8rem_minmax(0,1fr)]">
                    <LearningCourseCover :src="enrollment.course?.cover_image" :title="enrollment.course?.title" class="rounded-lg" />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-bg px-2.5 py-1 text-xs font-semibold text-secondary">{{ statusLabel(enrollment.status) }}</span>
                            <span v-if="enrollment.completed_at" class="rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">{{ tx('Abgeschlossen') }}</span>
                        </div>
                        <h2 class="mt-3 break-words text-xl font-bold text-primary">{{ enrollment.course?.title }}</h2>
                        <p class="mt-1 text-sm text-secondary">{{ enrollment.course?.subtitle || enrollment.course?.description }}</p>
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-secondary">
                                <span>{{ tx('Fortschritt') }}</span>
                                <span>{{ enrollment.progress_percent || 0 }}%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-inputBg">
                                <div class="h-full rounded-full bg-air-blue" :style="{ width: `${enrollment.progress_percent || 0}%` }"></div>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-secondary">{{ tx('Start:') }} {{ formatDate(enrollment.started_at) }}</p>
                        <p v-if="enrollment.next_lesson" class="mt-3 text-sm font-semibold">{{ ex('next') }}: {{ enrollment.next_lesson.title }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-border p-4">
                    <button v-if="enrollment.status==='active'" type="button" class="rounded-lg border border-border px-4 py-2 text-sm" @click="ask(enrollment,'leave')">{{ ex('leave') }}</button>
                    <button v-if="enrollment.status==='left'" type="button" class="rounded-lg border border-border px-4 py-2 text-sm" @click="ask(enrollment,'resume')">{{ ex('resume') }}</button>
                    <button type="button" class="rounded-lg border border-border px-4 py-2 text-sm" @click="ask(enrollment,enrollment.hidden_at?'show':'hide')">{{ ex(enrollment.hidden_at?'show':'hide') }}</button>
                    <Link v-if="enrollment.status === 'active'" :href="continueUrl(enrollment)" class="rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                        {{ tx('Weiterlernen') }}
                    </Link>
                    <a v-if="enrollment.certificate?.download_url" :href="enrollment.certificate.download_url" class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success">
                        {{ tx('Zertifikat PDF') }}
                    </a>
                    <a v-if="enrollment.certificate?.verify_url" :href="enrollment.certificate.verify_url" class="rounded-lg border border-success/40 px-4 py-2 text-sm font-semibold text-success">
                        {{ tx('Öffentlich prüfen') }}
                    </a>
                </div>
            </article>
        </section>

        <LearningParticipationDialog :target="target" :busy="busy" :error="actionError" @close="target=null" @confirm="confirm" />
        <section v-if="!visibleEnrollments.length" class="surface-card p-8 text-center">
            <i class="las la-book-open text-5xl text-air-blue"></i>
            <h2 class="mt-4 text-xl font-bold text-primary">{{ tx('Noch keine Kurse') }}</h2>
            <p class="mt-2 text-sm text-secondary">{{ tx('Sobald du dich einschreibst oder einen Kurs kaufst, erscheint er hier.') }}</p>
            <Link :href="route('guest.e-learning')" class="mt-5 inline-flex rounded-lg bg-buttonPrimary px-4 py-2 text-sm font-semibold text-buttonTextPrimary">
                {{ tx('Kurse entdecken') }}
            </Link>
        </section>
    </div>
</template>
