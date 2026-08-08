import { router } from '@inertiajs/vue3'

/**
 * Coalesces bursty realtime updates into one small Inertia request.
 * Hidden tabs defer work until they become visible again.
 */
export const createPartialReloader = ({ only, minInterval = 750 }) => {
    let timer = null
    let inFlight = false
    let queued = false
    let lastStartedAt = 0

    const run = () => {
        timer = null

        if (typeof document !== 'undefined' && document.hidden) {
            queued = true
            return
        }

        if (inFlight) {
            queued = true
            return
        }

        inFlight = true
        queued = false
        lastStartedAt = Date.now()

        router.reload({
            only,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                inFlight = false

                if (queued) refresh()
            },
        })
    }

    const refresh = () => {
        if (timer) {
            queued = true
            return
        }

        const wait = Math.max(0, minInterval - (Date.now() - lastStartedAt))

        if (wait > 0) {
            queued = true
            timer = window.setTimeout(run, wait)
            return
        }

        run()
    }

    const cancel = () => {
        if (timer) window.clearTimeout(timer)
        timer = null
        queued = false
    }

    return { refresh, cancel }
}
