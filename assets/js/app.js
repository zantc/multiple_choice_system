/**
 * Shared Application JavaScript.
 * Centralizes UI modal operations and standard interactivity across all portal modules.
 */

/**
 * Display modal by element ID.
 */
function showModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.display = 'block';
    }
}

/**
 * Hide modal by element ID and optionally reset standard form fields inside it.
 */
function hideModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.style.display = 'none';
        
        // Generic form reset if modal contains a form with id ending in 'Form' or simple form
        const form = el.querySelector('form');
        if (form && !form.classList.contains('no-reset')) {
            form.reset();
            // Reset any hidden id fields if present
            const idInputs = form.querySelectorAll('input[type="hidden"]');
            idInputs.forEach(input => {
                if (input.name !== 'csrf_token' && input.name !== 'action') {
                    input.value = '';
                }
            });
        }
    }
}

// Global click event to automatically close active modals when clicking outside the modal dialog box
window.addEventListener('click', function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});
