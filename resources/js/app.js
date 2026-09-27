/**
 * resources/js/app.js
 * Main Vite JavaScript entry point for AssocMap Web.
 */
import './bootstrap';
import './monitoring';
import './admin-user/admin_sidebar';
import './admin-user/admin-user-management';
import './admin-user/admin-area-management';
import './admin-user/admin-association-management';
import './admin-user/admin-member-management';
import './admin-user/admin-project-management';
import './admin-user/admin-audit-log';

import './admin-user/management-ui';

// Load map code only when the administrator opens GIS Mapping.
if (document.querySelector('[data-gis-page]')) {
    import('./gis/index').catch(() => {
        const message = document.querySelector('[data-gis-map-status]');
        if (message) message.textContent = 'The map could not load. Reload the page or use the location list.';
    });
}
