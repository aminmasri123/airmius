// Only an authenticated Inertia page should start the session heartbeat.
export function installSessionGuard(router, initialPage, browser = window) {
    let authenticated = Boolean(initialPage.props?.auth?.user);
    let checking = false;
    let redirecting = false;
    const login = () => {
        if (!authenticated || redirecting) return;
        redirecting = true;
        browser.location.replace('/login');
    };
    const check = async () => {
        if (!authenticated || checking || redirecting || browser.document.hidden) return;
        checking = true;
        try {
            const response = await browser.fetch('/session-status', {
                credentials: 'same-origin', cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.status === 401) login();
        } catch (_) {
            // Offline and server failures must not sign out a valid session.
        } finally { checking = false; }
    };
    router.on('success', (event) => {
        authenticated = Boolean(event.detail.page.props?.auth?.user);
    });
    router.on('invalid', (event) => {
        if (authenticated && [401, 419].includes(event.detail.response.status)) {
            event.preventDefault();
            login();
        }
    });
    browser.axios?.interceptors.response.use(response => response, error => {
        if (error.response?.status === 401) login();
        return Promise.reject(error);
    });
    browser.setInterval(check, 30000);
    browser.addEventListener('focus', check);
    browser.document.addEventListener('visibilitychange', check);
}
