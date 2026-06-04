import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { majorToCents } from '@/utils/currency'
import { confirmDialog } from '@/services/dialogService'

export function useLearningStudioWorkspace(props) {
    const page = usePage()
    const activePanel = ref('structure')
    const editingLesson = ref(null)
    const replyForms = ref({})
    const uploadState = ref({ key: '', error: '' })
    
    const courseCategories = [
        ['training', 'Training'],
        ['nutrition', 'Ernährung'],
        ['mindset', 'Mindset'],
        ['tactics', 'Taktik'],
        ['rehab', 'Reha & Prävention'],
        ['coaching', 'Coaching'],
        ['club_management', 'Vereinsführung'],
    ]
    
    const levels = [
        ['beginner', 'Einsteiger'],
        ['intermediate', 'Fortgeschritten'],
        ['advanced', 'Ambitioniert'],
        ['pro', 'Profi'],
    ]
    
    const lessonTypes = [
        ['lesson', 'Lektion'],
        ['video', 'Video'],
        ['exercise', 'Übung'],
        ['assignment', 'Aufgabe'],
        ['live_session', 'Live-Session'],
    ]
    
    const formatMoney = (cents, currency = 'EUR') => new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency,
    }).format(Number(cents || 0) / 100)
    
    const formatMinutes = (minutes) => {
        const value = Number(minutes || 0)
        if (value < 60) return `${value} Min.`
        return `${Math.floor(value / 60)} Std. ${value % 60} Min.`
    }
    
    const formatPercent = (part, total) => {
        const base = Number(total || 0)
        if (!base) return '0%'
    
        return `${Math.round((Number(part || 0) / base) * 100)}%`
    }
    
    const uploadLearningAsset = async (purpose, file, onUploaded) => {
        if (!props.selectedCourse || !file) return
    
        uploadState.value = { key: purpose, error: '' }
        const payload = new FormData()
        payload.append('purpose', purpose)
        payload.append('file', file)
    
        try {
            const response = await window.axios.post(route('auth.learning.studio.uploads.store', props.selectedCourse.id), payload, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })
            onUploaded(response.data)
        } catch (error) {
            uploadState.value = {
                key: purpose,
                error: error?.response?.data?.message || 'Upload fehlgeschlagen.',
            }
            return
        }
    
        uploadState.value = { key: '', error: '' }
    }
    
    const uploadCourseCover = (event) => {
        uploadLearningAsset('cover', event.target.files?.[0], (asset) => {
            courseForm.cover_image = asset.url
        })
        event.target.value = ''
    }
    
    const uploadLessonVideo = (event) => {
        uploadLearningAsset('lesson_video', event.target.files?.[0], (asset) => {
            lessonForm.video_url = asset.url
        })
        event.target.value = ''
    }
    
    const uploadLessonAttachment = (event) => {
        uploadLearningAsset('lesson_attachment', event.target.files?.[0], (asset) => {
            lessonForm.attachments_text = [lessonForm.attachments_text, asset.url].filter(Boolean).join('\n')
        })
        event.target.value = ''
    }
    
    const statusLabel = (status) => ({
        draft: 'Entwurf',
        review: 'Prüfung',
        published: 'Live',
        archived: 'Archiviert',
    }[status] || status)
    
    const newCourseForm = useForm({
        title: '',
        subtitle: '',
        description: '',
        category: 'training',
        sport_type: '',
        level: 'beginner',
        language: 'de',
        cover_image: '',
        status: 'draft',
        is_public: false,
        is_free: true,
        price: '',
        learning_goals_text: '',
        requirements_text: '',
        target_groups_text: '',
        sales_points_text: '',
        faq_items_text: '',
        guarantee_text: '',
        certificate_logo_url: '',
        certificate_signature_name: '',
        certificate_footer_text: '',
        tags_text: '',
    })
    
    const courseForm = useForm({
        title: '',
        subtitle: '',
        description: '',
        category: 'training',
        sport_type: '',
        level: 'beginner',
        language: 'de',
        cover_image: '',
        status: 'draft',
        is_public: false,
        is_free: true,
        price: '',
        learning_goals_text: '',
        requirements_text: '',
        target_groups_text: '',
        sales_points_text: '',
        faq_items_text: '',
        guarantee_text: '',
        certificate_logo_url: '',
        certificate_signature_name: '',
        certificate_footer_text: '',
        tags_text: '',
    })
    
    const sectionForm = useForm({
        title: '',
        description: '',
    })
    
    const lessonForm = useForm({
        learning_course_section_id: '',
        title: '',
        type: 'lesson',
        summary: '',
        content: '',
        video_url: '',
        attachments_text: '',
        duration_minutes: '',
        position: '',
        is_preview: false,
        unlock_after_days: 0,
    })
    
    const quizForm = useForm({
        learning_lesson_id: '',
        title: '',
        description: '',
        pass_percent: 70,
        question: '',
        options_text: '',
        correct_options_text: '',
        explanation: '',
    })
    
    const couponForm = useForm({
        code: '',
        discount_type: 'percent',
        discount_value: 10,
        max_redemptions: '',
        expires_at: '',
        is_active: true,
    })
    
    const assignmentForm = useForm({
        learning_lesson_id: '',
        title: '',
        instructions: '',
        points: 100,
        due_after_days: '',
        is_required: true,
    })
    
    const enrollmentForm = useForm({ email: '' })
    const gradingForms = ref({})
    
    const fillCourseForm = () => {
        if (!props.selectedCourse) return
    
        courseForm.defaults({
            title: props.selectedCourse.title || '',
            subtitle: props.selectedCourse.subtitle || '',
            description: props.selectedCourse.description || '',
            category: props.selectedCourse.category || 'training',
            sport_type: props.selectedCourse.sport_type || '',
            level: props.selectedCourse.level || 'beginner',
            language: props.selectedCourse.language || 'de',
            cover_image: props.selectedCourse.cover_image || '',
            status: props.selectedCourse.status || 'draft',
            is_public: Boolean(props.selectedCourse.is_public),
            is_free: Boolean(props.selectedCourse.is_free),
            price: props.selectedCourse.price_cents ? String(Number(props.selectedCourse.price_cents) / 100).replace('.', ',') : '',
            learning_goals_text: props.selectedCourse.learning_goals_text || '',
            requirements_text: props.selectedCourse.requirements_text || '',
            target_groups_text: props.selectedCourse.target_groups_text || '',
            sales_points_text: props.selectedCourse.sales_points_text || '',
            faq_items_text: props.selectedCourse.faq_items_text || '',
            guarantee_text: props.selectedCourse.guarantee_text || '',
            certificate_logo_url: props.selectedCourse.certificate_logo_url || '',
            certificate_signature_name: props.selectedCourse.certificate_signature_name || '',
            certificate_footer_text: props.selectedCourse.certificate_footer_text || '',
            tags_text: props.selectedCourse.tags_text || '',
        })
        courseForm.reset()
    }
    
    watch(() => props.selectedCourse?.id, () => {
        fillCourseForm()
        editingLesson.value = null
        const forms = {}
        ;(props.selectedCourse?.assignments || []).forEach((assignment) => {
            ;(assignment.submissions || []).forEach((submission) => {
                forms[String(submission.id)] = {
                    status: submission.status || 'passed',
                    score: submission.score || '',
                    feedback: submission.feedback || '',
                }
            })
        })
        gradingForms.value = forms
    }, { immediate: true })
    
    const allLessons = computed(() => (props.selectedCourse?.sections || []).flatMap((section) => section.lessons || []))
    const courseStats = computed(() => [
        ['Kapitel', props.selectedCourse?.sections?.length || 0],
        ['Lektionen', allLessons.value.length],
        ['Quiz', props.selectedCourse?.quizzes?.length || 0],
        ['Teilnehmer', props.selectedCourse?.enrollments?.length || 0],
    ])
    
    const payloadWithPrice = (form) => ({
        title: form.title,
        subtitle: form.subtitle,
        description: form.description,
        category: form.category,
        sport_type: form.sport_type,
        level: form.level,
        language: form.language,
        cover_image: form.cover_image,
        status: form.status,
        is_public: form.is_public,
        is_free: form.is_free,
        price_cents: form.is_free ? 0 : majorToCents(form.price),
        learning_goals_text: form.learning_goals_text,
        requirements_text: form.requirements_text,
        target_groups_text: form.target_groups_text,
        sales_points_text: form.sales_points_text,
        faq_items_text: form.faq_items_text,
        guarantee_text: form.guarantee_text,
        certificate_logo_url: form.certificate_logo_url,
        certificate_signature_name: form.certificate_signature_name,
        certificate_footer_text: form.certificate_footer_text,
        tags_text: form.tags_text,
    })
    
    const createCourse = () => {
        newCourseForm
            .transform(() => payloadWithPrice(newCourseForm))
            .post(route('auth.learning.studio.courses.store'), {
                preserveScroll: true,
                onSuccess: () => newCourseForm.reset(),
            })
    }
    
    const updateCourse = () => {
        if (!props.selectedCourse) return
        courseForm
            .transform(() => payloadWithPrice(courseForm))
            .put(route('auth.learning.studio.courses.update', props.selectedCourse.id), { preserveScroll: true })
    }
    
    const createSection = () => {
        if (!props.selectedCourse) return
        sectionForm.post(route('auth.learning.studio.sections.store', props.selectedCourse.id), {
            preserveScroll: true,
            onSuccess: () => sectionForm.reset(),
        })
    }
    
    const resetLessonForm = () => {
        editingLesson.value = null
        lessonForm.defaults({
            learning_course_section_id: props.selectedCourse?.sections?.[0]?.id || '',
            title: '',
            type: 'lesson',
            summary: '',
            content: '',
            video_url: '',
            attachments_text: '',
            duration_minutes: '',
            position: '',
            is_preview: false,
            unlock_after_days: 0,
        })
        lessonForm.reset()
    }
    
    watch(() => props.selectedCourse?.sections?.[0]?.id, resetLessonForm, { immediate: true })
    
    const editLesson = (lesson) => {
        editingLesson.value = lesson
        lessonForm.defaults({
            learning_course_section_id: lesson.learning_course_section_id || '',
            title: lesson.title || '',
            type: lesson.type || 'lesson',
            summary: lesson.summary || '',
            content: lesson.content || '',
            video_url: lesson.video_url || '',
            attachments_text: (lesson.attachments || []).map((item) => item.url || item).join('\n'),
            duration_minutes: lesson.duration_minutes || '',
            position: lesson.position || '',
            is_preview: Boolean(lesson.is_preview),
            unlock_after_days: lesson.unlock_after_days || 0,
        })
        lessonForm.reset()
    }
    
    const submitLesson = () => {
        if (!props.selectedCourse) return
    
        const url = editingLesson.value
            ? route('auth.learning.studio.lessons.update', [props.selectedCourse.id, editingLesson.value.id])
            : route('auth.learning.studio.lessons.store', props.selectedCourse.id)
    
        const options = {
            preserveScroll: true,
            onSuccess: resetLessonForm,
        }
    
        editingLesson.value ? lessonForm.put(url, options) : lessonForm.post(url, options)
    }
    
    const moveLesson = (section, lesson, direction) => {
        if (!props.selectedCourse) return
    
        const lessons = [...(section.lessons || [])].sort((a, b) => (a.position || 0) - (b.position || 0))
        const index = lessons.findIndex((item) => item.id === lesson.id)
        const targetIndex = index + direction
    
        if (index < 0 || targetIndex < 0 || targetIndex >= lessons.length) return
    
        const reordered = [...lessons]
        const moved = reordered.splice(index, 1)[0]
        reordered.splice(targetIndex, 0, moved)
    
        router.put(route('auth.learning.studio.lessons.reorder', props.selectedCourse.id), {
            lessons: reordered.map((item, itemIndex) => ({ id: item.id, position: itemIndex + 1 })),
        }, { preserveScroll: true })
    }
    
    const deleteLesson = async (lesson) => {
        if (!props.selectedCourse) return
    
        const confirmed = await confirmDialog({
            title: 'Lektion löschen',
            message: `Soll die Lektion "${lesson.title}" wirklich gelöscht werden?`,
            confirmLabel: 'Löschen',
            danger: true,
        })
    
        if (!confirmed) return
    
        router.delete(route('auth.learning.studio.lessons.destroy', [props.selectedCourse.id, lesson.id]), { preserveScroll: true })
    }
    
    const createQuiz = () => {
        if (!props.selectedCourse) return
        quizForm.post(route('auth.learning.studio.quizzes.store', props.selectedCourse.id), {
            preserveScroll: true,
            onSuccess: () => quizForm.reset('title', 'description', 'question', 'options_text', 'correct_options_text', 'explanation'),
        })
    }
    
    const deleteQuiz = async (quiz) => {
        if (!props.selectedCourse) return
    
        const confirmed = await confirmDialog({
            title: 'Quiz löschen',
            message: `Soll das Quiz "${quiz.title}" wirklich gelöscht werden?`,
            confirmLabel: 'Löschen',
            danger: true,
        })
    
        if (!confirmed) return
    
        router.delete(route('auth.learning.studio.quizzes.destroy', [props.selectedCourse.id, quiz.id]), { preserveScroll: true })
    }
    
    const createCoupon = () => {
        if (!props.selectedCourse) return
        couponForm.post(route('auth.learning.studio.coupons.store', props.selectedCourse.id), {
            preserveScroll: true,
            onSuccess: () => couponForm.reset('code', 'discount_value', 'max_redemptions', 'expires_at'),
        })
    }
    
    const createAssignment = () => {
        if (!props.selectedCourse) return
        assignmentForm.post(route('auth.learning.studio.assignments.store', props.selectedCourse.id), {
            preserveScroll: true,
            onSuccess: () => assignmentForm.reset('title', 'instructions', 'due_after_days'),
        })
    }
    
    const grantEnrollment = () => {
        if (!props.selectedCourse) return
        enrollmentForm.post(route('auth.learning.studio.enrollments.store', props.selectedCourse.id), {
            preserveScroll: true,
            onSuccess: () => enrollmentForm.reset(),
        })
    }
    
    const revokeEnrollment = async (enrollment) => {
        if (!props.selectedCourse) return
    
        const confirmed = await confirmDialog({
            title: 'Zugang deaktivieren',
            message: 'Soll der Zugang wirklich deaktiviert werden?',
            confirmLabel: 'Deaktivieren',
            danger: true,
        })
    
        if (!confirmed) return
    
        router.put(route('auth.learning.studio.enrollments.revoke', [props.selectedCourse.id, enrollment.id]), {}, { preserveScroll: true })
    }
    
    const gradeSubmission = (submission) => {
        if (!props.selectedCourse) return
        const form = gradingForms.value[String(submission.id)] || { status: 'passed', score: submission.score || '', feedback: submission.feedback || '' }
        router.put(route('auth.learning.studio.assignment-submissions.update', [props.selectedCourse.id, submission.id]), form, { preserveScroll: true })
    }
    
    const updateQuestionStatus = (question, status) => {
        if (!props.selectedCourse) return
    
        router.put(route('auth.learning.studio.comments.update', [props.selectedCourse.id, question.id]), {
            status,
        }, { preserveScroll: true })
    }
    
    const submitQuestionReply = (question) => {
        if (!props.selectedCourse) return
    
        router.post(route('auth.learning.studio.comments.replies.store', [props.selectedCourse.id, question.id]), {
            body: replyForms.value[String(question.id)] || '',
        }, {
            preserveScroll: true,
            onSuccess: () => {
                replyForms.value = {
                    ...replyForms.value,
                    [String(question.id)]: '',
                }
            },
        })
    }
    return {
        page,
        activePanel,
        editingLesson,
        replyForms,
        uploadState,
        courseCategories,
        levels,
        lessonTypes,
        formatMoney,
        formatMinutes,
        formatPercent,
        uploadLearningAsset,
        uploadCourseCover,
        uploadLessonVideo,
        uploadLessonAttachment,
        statusLabel,
        newCourseForm,
        courseForm,
        sectionForm,
        lessonForm,
        quizForm,
        couponForm,
        assignmentForm,
        enrollmentForm,
        gradingForms,
        fillCourseForm,
        allLessons,
        courseStats,
        payloadWithPrice,
        createCourse,
        updateCourse,
        createSection,
        resetLessonForm,
        editLesson,
        submitLesson,
        moveLesson,
        deleteLesson,
        createQuiz,
        deleteQuiz,
        createCoupon,
        createAssignment,
        grantEnrollment,
        revokeEnrollment,
        gradeSubmission,
        updateQuestionStatus,
        submitQuestionReply,
    }
}

