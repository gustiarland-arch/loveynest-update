/**
 * Keyboard, viewport and safe-area handling shared by the PWA and the Android app (same code, same WebView/browser APIs).
 *
 * Sets on <html>:
 *   --app-h   height of the part of the screen that is really visible (shrinks when the keyboard is open, also on iOS)
 *   --kb      height of the on-screen keyboard in px (0 when closed)
 *   --bg-h    stable height for the full-screen backdrop, so it never shrinks or jumps with the keyboard
 *   .kb-open  present while the keyboard is open (the bottom bar hides, page padding shrinks)
 * Pages that must not pan when the keyboard opens (the chat) set data-kb-lock on <html> while they are shown.
 */
const root = document.documentElement;
const NON_TEXT = ['checkbox', 'radio', 'button', 'submit', 'reset', 'range', 'file', 'color', 'image'];

let baseHeight = window.innerHeight;
let backdropHeight = 0; // only grows (until the screen turns), so the backdrop never shrinks with the keyboard
let frame = 0;

function isEditable(el) {
    if (!el) return false;
    if (el.isContentEditable) return true;
    if (el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') return true;
    return el.tagName === 'INPUT' && !NON_TEXT.includes(el.type);
}

function update() {
    frame = 0;
    const vv = window.visualViewport;
    const height = vv ? vv.height : window.innerHeight;
    const top = vv ? vv.offsetTop : 0;
    const typing = isEditable(document.activeElement);
    if (!typing) baseHeight = Math.max(window.innerHeight, height);

    const keyboard = typing ? Math.max(0, baseHeight - height - top) : 0;
    const open = keyboard > 100;

    root.style.setProperty('--app-h', `${Math.round(height)}px`);
    // iOS pans the visual viewport inside the layout viewport when the keyboard opens; this is how far.
    root.style.setProperty('--vv-top', `${Math.round(top)}px`);
    root.style.setProperty('--kb', `${open ? Math.round(keyboard) : 0}px`);
    backdropHeight = Math.max(backdropHeight, window.screen?.height || 0, window.screen?.width || 0, window.innerHeight, height);
    root.style.setProperty('--bg-h', `${Math.round(backdropHeight)}px`);
    if (root.classList.contains('kb-open') !== open) {
        // The glass blur is switched off while the keyboard moves the layout (see :root[data-busy] in app.css).
        root.setAttribute('data-busy', '');
        setTimeout(() => root.removeAttribute('data-busy'), 380);
    }
    root.classList.toggle('kb-open', open);

    // iOS pans the page up when the keyboard opens; pages that lock their height stay put.
    if (open && top > 0 && root.hasAttribute('data-kb-lock')) window.scrollTo(0, 0);
}

function schedule() {
    if (!frame) frame = requestAnimationFrame(update);
}

export function bootViewport() {
    update();
    window.addEventListener('resize', schedule, { passive: true });
    window.addEventListener('orientationchange', () => {
        baseHeight = 0;
        backdropHeight = 0;
        setTimeout(schedule, 250);
    });
    window.visualViewport?.addEventListener('resize', schedule, { passive: true });
    window.visualViewport?.addEventListener('scroll', schedule, { passive: true });

    document.addEventListener('focusin', (event) => {
        const el = event.target;
        if (!isEditable(el)) return;
        schedule();
        // Let the keyboard finish opening, then bring the field into view (the chat composer is already pinned to the bottom).
        if (!root.hasAttribute('data-kb-lock')) {
            setTimeout(() => el.scrollIntoView?.({ block: 'center', behavior: 'smooth' }), 320);
        }
    });
    document.addEventListener('focusout', () => setTimeout(schedule, 60));
}
