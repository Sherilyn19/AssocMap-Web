/**
 * resources/js/app.js
 * Main Vite JavaScript entry point for AssocMap Web.
 *
 * Contents:
 * 1. Application setup and module imports
 * 2. Profile name hover animation
 * 3. Representative review confirmation
 * 4. Conditional GIS loading
 * 5. Shared workspace details import
 *
 */

/* ==========================================================================
   1. APPLICATION SETUP AND MODULE IMPORTS
   ========================================================================== */

// Initialize the application and shared dashboard behavior.
import './bootstrap';
import './shared/monitoring';
import './shared/sidebar';
import './shared/count-up';

// Load shared record dialogs used by the Field Officer Members module.
import './shared/record-dialog';

// Load module-specific Field Officer layouts.
import '../css/field-officer-members.css';
import '../css/field-officer-projects.css';
import '../css/field-officer-trainings.css';

// Reuse scrolling context headings in drawers across user roles.
import './shared/drawer-context';

// Load Field Officer workspace interactions and editors.
import './field-officer-user/members-workspace';
import './field-officer-user/assigned-areas';
import './field-officer-user/project-management';
// FO training forms and attendance use the shared loader and native dialogs.
import './field-officer-user/training-management';

// Load administrator module interactions.
import './admin-user/admin-user-management';
import './admin-user/admin-area-management';
import './admin-user/admin-association-management';
import './admin-user/admin-member-management';
import './admin-user/admin-project-management';
import './admin-user/admin-audit-log';

// Load shared management behavior and administrator GIS transfers.
import './shared/management-ui';
import './admin-user/admin-gis-transfer';

/* ==========================================================================
   2. PROFILE NAME HOVER ANIMATION
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // Run only when the page contains the animated profile name.
    const name = document.querySelector('[data-profile-wave]');

    if (!name) return;

    const link = name.closest('a');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const words = name.textContent.trim().split(/\s+/);

    // Keep each word together while allowing its letters to animate separately.
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

    // Cancel previous animations before restarting or leaving the link.
    const stop = () => letters.forEach(letter =>
        letter.getAnimations().forEach(animation => animation.cancel())
    );

    // Lift letters in sequence unless the user prefers reduced motion.
    const wave = () => {
        stop();

        if (motion.matches) return;

        letters.forEach((letter, index) => letter.animate(
            [
                { transform: 'translateY(0)' },
                { transform: 'translateY(-3px)' },
                { transform: 'translateY(0)' },
            ],
            {
                duration: 360,
                delay: index * Math.min(25, 900 / letters.length),
                easing: 'ease-in-out',
            }
        ));
    };

    // Provide the same feedback for pointer hover and keyboard focus.
    link.addEventListener('pointerenter', wave);
    link.addEventListener('focus', wave);

    // Stop the animation when interaction ends or motion preferences change.
    link.addEventListener('pointerleave', stop);
    link.addEventListener('blur', stop);
    motion.addEventListener('change', stop);
});

/* ==========================================================================
   3. REPRESENTATIVE REVIEW CONFIRMATION
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // Run only on pages containing the representative review form.
    const form = document.querySelector('[data-representative-review]');

    if (!form) return;

    const decision = form.elements.decision;
    const reason = form.elements.rejection_reason;

    // Require a reason when rejecting an application.
    const sync = () => {
        reason.required = decision.value === 'Rejected';
        reason.setAttribute('aria-required', String(reason.required));
    };

    decision.addEventListener('change', sync);
    sync();

    // Confirm the decision before submission; authorization remains server-side.
    const dialog = document.createElement('dialog');

    dialog.setAttribute('data-review-dialog', '');
    dialog.setAttribute('aria-labelledby', 'review-confirm-title');

    dialog.innerHTML = '<h2 id="review-confirm-title" class="text-xl font-bold">Confirm application review</h2><p data-review-summary class="mt-3 text-sm leading-6 text-slate-600"></p><div class="mt-6 flex flex-wrap justify-end gap-3"><button type="button" data-review-cancel class="am-user-button am-user-button-secondary">Go back</button><button type="button" data-review-confirm class="am-user-button am-user-button-primary">Record decision</button></div>';

    document.body.append(dialog);

    let confirmed = false;

    // Pause the first submission and explain the selected decision.
    form.addEventListener('submit', (event) => {
        if (confirmed) return;

        event.preventDefault();

        dialog.querySelector('[data-review-summary]').textContent =
            decision.value === 'Approved'
                ? 'Approve this application and create an official member record? This decision cannot be changed here.'
                : 'Reject this application with the reason you entered? The application and reason will remain in the review history.';

        dialog.showModal();
        dialog.querySelector('[data-review-cancel]').focus();
    });

    // Return to the form without submitting a decision.
    dialog.querySelector('[data-review-cancel]').addEventListener(
        'click',
        () => dialog.close()
    );

    // Submit the form after the representative confirms the decision.
    dialog.querySelector('[data-review-confirm]').addEventListener('click', () => {
        confirmed = true;

        dialog.close();
        form.requestSubmit();
    });
});

/* ==========================================================================
   4. CONDITIONAL GIS LOADING
   ========================================================================== */

// Load the GIS viewer only when its container exists on the page.
if (document.querySelector('[data-gis-viewer]')) {
    import('./shared/gis/viewer').catch(() => {
        const status = document.querySelector('[data-viewer-status]');

        if (status) {
            status.textContent =
                'The map could not load. Location details and filters remain available.';
        }
    });
}

// Load the GIS Mapping module only when its page container exists.
if (document.querySelector('[data-gis-page]')) {
    import('./shared/gis/index').catch(() => {
        const message = document.querySelector('[data-gis-map-status]');

        if (message) {
            message.textContent =
                'The map could not load. Reload the page or use the location list.';
        }
    });
}

/* ==========================================================================
   5. SHARED WORKSPACE DETAILS IMPORT
   ========================================================================== */

// Preserve the existing import position for shared project and training details.
import './shared/workspace-details.ts';