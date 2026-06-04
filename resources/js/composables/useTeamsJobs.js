import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

export function useTeamsJobs({ openDeleteModal, setActionNotice }) {
    const showJobModal = ref(false)
    const selectedJobClub = ref(null)
    const jobModalNotice = ref(null)
    const jobForms = ref({})
    const editingJobId = ref(null)
    const isSubmittingJob = ref(false)

    const emptyJobForm = () => ({
        title: '',
        type: 'volunteer',
        location: '',
        workload: '',
        employment_type: '',
        description: '',
        contact_email: '',
        application_url: '',
        is_published: true,
    })

    const jobFormFor = (club) => {
        jobForms.value[club.id] ??= emptyJobForm()
        return jobForms.value[club.id]
    }

    const resetJobForm = (club) => {
        jobForms.value[club.id] = emptyJobForm()
        editingJobId.value = null
    }

    const openJobModal = (club) => {
        selectedJobClub.value = club
        jobModalNotice.value = null
        resetJobForm(club)
        showJobModal.value = true
    }

    const closeJobModal = () => {
        showJobModal.value = false
        selectedJobClub.value = null
        jobModalNotice.value = null
        editingJobId.value = null
        isSubmittingJob.value = false
    }

    const editJob = (club, job) => {
        selectedJobClub.value = club
        jobModalNotice.value = null
        editingJobId.value = job.id

        jobForms.value[club.id] = {
            title: job.title || '',
            type: job.type || 'volunteer',
            location: job.location || '',
            workload: job.workload || '',
            employment_type: job.employment_type || '',
            description: job.description || '',
            contact_email: job.contact_email || '',
            application_url: job.application_url || '',
            is_published: Boolean(job.is_published),
        }

        showJobModal.value = true
    }

    const submitJob = (club) => {
        if (isSubmittingJob.value) return

        setActionNotice(null)
        jobModalNotice.value = null
        isSubmittingJob.value = true
        const isEditing = Boolean(editingJobId.value)

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                resetJobForm(club)
                closeJobModal()
                setActionNotice('success', isEditing ? 'Eintrag wurde aktualisiert.' : 'Eintrag wurde erstellt.')
            },
            onError: () => {
                jobModalNotice.value = {
                    type: 'error',
                    message: 'Eintrag konnte nicht gespeichert werden. Bitte prüfe die markierten Felder.',
                }
            },
            onFinish: () => {
                isSubmittingJob.value = false
            },
        }

        editingJobId.value
            ? router.put(route('auth.organization-jobs.update', editingJobId.value), jobFormFor(club), options)
            : router.post(route('auth.clubs.jobs.store', club.id), jobFormFor(club), options)
    }

    const deleteJob = (job) => {
        openDeleteModal({
            title: `Stelle "${job.title}" löschen`,
            description: 'Dieser Eintrag wird dauerhaft gelöscht und erscheint danach nicht mehr auf der Jobs-Seite.',
            route: 'auth.organization-jobs.destroy',
            params: job.id,
            successMessage: 'Eintrag wurde gelöscht.',
            errorMessage: 'Eintrag konnte nicht gelöscht werden.',
            confirmText: 'löschen',
            buttonLabel: 'Stelle löschen',
        })
    }

    return {
        closeJobModal,
        deleteJob,
        editJob,
        editingJobId,
        isSubmittingJob,
        jobFormFor,
        jobModalNotice,
        openJobModal,
        resetJobForm,
        selectedJobClub,
        showJobModal,
        submitJob,
    }
}

