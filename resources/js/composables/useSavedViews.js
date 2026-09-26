import { ref } from 'vue'

export const useSavedViews = (workspace, fallbackMessage = 'Saved views could not be updated.') => {
    const views = ref([])
    const loading = ref(false)
    const error = ref('')

    const load = async () => {
        loading.value = true
        error.value = ''
        try {
            const response = await window.axios.get(route('auth.saved-views.index'), {
                params: { workspace },
            })
            views.value = response.data.data || []
        } catch (requestError) {
            error.value = requestError?.response?.data?.message || fallbackMessage
        } finally {
            loading.value = false
        }
    }

    const save = async (name, configuration) => {
        error.value = ''
        try {
            const response = await window.axios.post(route('auth.saved-views.store'), {
                workspace,
                name,
                configuration,
                is_favorite: true,
            })
            views.value = [response.data.data, ...views.value]
            return response.data.data
        } catch (requestError) {
            error.value = requestError?.response?.data?.message || fallbackMessage
            return null
        }
    }

    const remove = async (view) => {
        error.value = ''
        try {
            await window.axios.delete(route('auth.saved-views.destroy', view.id))
            views.value = views.value.filter((candidate) => candidate.id !== view.id)
            return true
        } catch (requestError) {
            error.value = requestError?.response?.data?.message || fallbackMessage
            return false
        }
    }

    return { views, loading, error, load, save, remove }
}
