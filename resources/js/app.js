/**
 * resources/js/app.js
 * Main Vite JavaScript entry point for AssocMap Web.
 */
import './bootstrap';
import './shared/monitoring';
import './shared/sidebar';
import './admin-user/admin-user-management';
import './admin-user/admin-area-management';
import './admin-user/admin-association-management';
import './admin-user/admin-member-management';
import './admin-user/admin-project-management';
import './admin-user/admin-audit-log';

import './shared/management-ui';
import './admin-user/admin-gis-transfer';

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
