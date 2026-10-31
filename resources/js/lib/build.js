import { reactive } from 'vue';

// Set by BuildPrompt when the server has a newer web build than the one this page was loaded with.
export const buildState = reactive({ stale: false });

/** Fetches the new build: the service worker is refreshed first, then the page is loaded again. */
export async function applyUpdate(url = null) {
    try {
        const registration = await navigator.serviceWorker?.getRegistration();
        await registration?.update();
    } catch {
        // The page reload below is what matters.
    }
    if (url) window.location.assign(url);
    else window.location.reload();
}
