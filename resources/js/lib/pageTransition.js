// Smooth menu changes.
// Where the browser has the View Transitions API (Chrome/Android WebView 111+, Safari 18+) Inertia swaps the page inside
// a transition: the old page is laid on top as a still picture and fades and slides away, while the new page underneath
// (live, not a picture) glides in with .page-rise. Nothing gets a view-transition-name on purpose: a named element becomes
// a "backdrop root" and the liquid-glass cards inside it would lose their blur for the length of the transition.
// Older browsers get only the .page-rise entrance. Direction: deeper page or a tab further right = forward, otherwise back.
import { reactive } from 'vue';
import { LITE } from '@/lib/native';

const TABS = ['/dashboard', '/tasks', '/berdua', '/chat', '/settings'];
export const pageNav = reactive({ dir: 'forward' });

const depth = (path) => path.split('/').filter(Boolean).length;
const tab = (path) => TABS.findIndex((t) => path === t || path.startsWith(`${t}/`));
const reduced = () => typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;

function direction(from, to) {
    const a = tab(from);
    const b = tab(to);
    if (a >= 0 && b >= 0 && a !== b && depth(from) === 1 && depth(to) === 1) return b > a ? 'forward' : 'back';
    return depth(to) < depth(from) ? 'back' : 'forward';
}

/** Inertia `defaults.visitOptions`: adds a view transition to plain page visits only (no form posts, reloads or prefetches). */
export function visitOptions(href, options) {
    if (typeof window === 'undefined') return {};
    if ((options.method ?? 'get') !== 'get' || options.prefetch || options.only?.length || options.except?.length || options.preserveState || options.async) return {};
    let to;
    try {
        to = new URL(href, location.href).pathname;
    } catch {
        return {};
    }
    if (to === location.pathname) return {};
    pageNav.dir = direction(location.pathname, to);
    if (LITE || !document.startViewTransition || reduced()) return {};
    const dir = pageNav.dir;
    return {
        viewTransition: (transition) => {
            const root = document.documentElement;
            root.dataset.vt = dir;
            const clear = () => {
                if (root.dataset.vt === dir) delete root.dataset.vt;
            };
            transition.finished.then(clear, clear);
        },
    };
}

// The browser's back and forward buttons do not go through visitOptions.
if (typeof window !== 'undefined') window.addEventListener('popstate', () => (pageNav.dir = 'back'));
