/**
 * resources/js/app.js
 * Main Vite JavaScript entry point for AssocMap Web.
 */
import './bootstrap';
import './shared/monitoring';
import './shared/sidebar';
import './shared/count-up';
// Reuse scrolling context headings in drawers across user roles.
import './shared/drawer-context';
// Initialize the coverage dialog only on the Field Officer Assigned Areas page.
import './field-officer-user/assigned-areas';
import './admin-user/admin-user-management';
import './admin-user/admin-area-management';
import './admin-user/admin-association-management';
import './admin-user/admin-member-management';
import './admin-user/admin-project-management';
import './admin-user/admin-audit-log';

import './shared/management-ui';
import './admin-user/admin-gis-transfer';

// Keep words together when wrapping, then lift each letter in a short hover wave.
document.addEventListener('DOMContentLoaded', () => {
    const name = document.querySelector('[data-profile-wave]');
    if (!name) return;
    const link = name.closest('a');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const words = name.textContent.trim().split(/\s+/);
    name.replaceChildren();
    words.forEach((word, index) => {
        if (index) name.append(' ');
        const group = document.createElement('span');
        group.className = 'am-profile-word';
        Array.from(word).forEach(character => {
            const letter = document.createElement('span');
            letter.className = 'am-profile-letter';
            letter.textContent = character;
            group.append(letter);
        });
        name.append(group);
    });
    const letters = [...name.querySelectorAll('.am-profile-letter')];
    const stop = () => letters.forEach(letter => letter.getAnimations().forEach(animation => animation.cancel()));
    const wave = () => {
        stop();
        if (motion.matches) return;
        letters.forEach((letter, index) => letter.animate(
            [{ transform: 'translateY(0)' }, { transform: 'translateY(-3px)' }, { transform: 'translateY(0)' }],
            { duration: 360, delay: index * Math.min(25, 900 / letters.length), easing: 'ease-in-out' }
        ));
    };
    // Keyboard focus gets the same feedback; leaving or reducing motion stops it.
    link.addEventListener('pointerenter', wave);
    link.addEventListener('focus', wave);
    link.addEventListener('pointerleave', stop);
    link.addEventListener('blur', stop);
    motion.addEventListener('change', stop);
});

// Review stays server-authorized; this dialog makes the irreversible decision explicit.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-representative-review]');
    if (!form) return;
    const decision = form.elements.decision;
    const reason = form.elements.rejection_reason;
    const sync = () => {
        reason.required = decision.value === 'Rejected';
        reason.setAttribute('aria-required', String(reason.required));
    };
    decision.addEventListener('change', sync);
    sync();
    const dialog = document.createElement('dialog');
    dialog.setAttribute('data-review-dialog', '');
    dialog.setAttribute('aria-labelledby', 'review-confirm-title');
    dialog.innerHTML = '<h2 id="review-confirm-title" class="text-xl font-bold">Confirm application review</h2><p data-review-summary class="mt-3 text-sm leading-6 text-slate-600"></p><div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" data-review-cancel class="am-user-button am-user-button-secondary">Go back</button><button type="button" data-review-confirm class="am-user-button am-user-button-primary">Record decision</button></div>';
    document.body.append(dialog);
    let confirmed = false;
    form.addEventListener('submit', (event) => {
        if (confirmed) return;
        event.preventDefault();
        dialog.querySelector('[data-review-summary]').textContent = decision.value === 'Approved'
            ? 'Approve this application and create an official member record? This decision cannot be changed here.'
            : 'Reject this application with the reason you entered? The application and reason will remain in the review history.';
        dialog.showModal();
        dialog.querySelector('[data-review-cancel]').focus();
    });
    dialog.querySelector('[data-review-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-review-confirm]').addEventListener('click', () => {
        confirmed = true;
        dialog.close();
        form.requestSubmit();
    });
});

if (document.querySelector('[data-gis-viewer]')) {
    import('./shared/gis/viewer').catch(() => {
        const status = document.querySelector('[data-viewer-status]');
        if (status) status.textContent = 'The map could not load. Location details and filters remain available.';
    });
}

// Load map code when an authorized user opens GIS Mapping.
if (document.querySelector('[data-gis-page]')) {
    import('./shared/gis/index').catch(() => {
        const message = document.querySelector('[data-gis-map-status]');
        if (message) message.textContent = 'The map could not load. Reload the page or use the location list.';
    });
}

import './shared/workspace-details.ts';

