const auditPage = document.querySelector('[data-audit-page]');
const dialog = auditPage?.querySelector('[data-audit-dialog]');

if (dialog && typeof dialog.showModal === 'function') {
    auditPage.querySelectorAll('[data-audit-details] summary').forEach((summary) => {
        summary.addEventListener('click', (event) => {
            event.preventDefault();
            // Read escaped text from the page; never interpret audit details as HTML or code.
            dialog.querySelector('[data-audit-dialog-text]').textContent =
                summary.parentElement.querySelector('[data-audit-text]').textContent;
            dialog.showModal();
        });
    });
    // Native dialog handles Escape, focus containment, and focus restoration.
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
}
