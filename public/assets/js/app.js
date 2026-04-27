/**
 * App.js - JavaScript chung
 */

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });
});

/**
 * Load chapters dynamically when subject changes (AJAX)
 */
function loadChapters(subjectId, targetSelectId, selectedId = null) {
    const select = document.getElementById(targetSelectId);
    if (!select) return;

    select.innerHTML = '<option value="">-- Tất cả chương --</option>';

    if (!subjectId) return;

    fetch(BASE_URL + '/question/getChapters/' + subjectId)
        .then(response => response.json())
        .then(data => {
            data.chapters.forEach(function (chapter) {
                const option = document.createElement('option');
                option.value = chapter.id;
                option.textContent = chapter.name;
                if (selectedId && chapter.id == selectedId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        })
        .catch(err => console.error('Error loading chapters:', err));
}

/**
 * Confirm delete action
 */
function confirmDelete(message) {
    return confirm(message || 'Bạn có chắc chắn muốn xóa?');
}

/**
 * Select/Deselect all checkboxes
 */
function toggleAllCheckboxes(sourceCheckbox, targetClass) {
    const checkboxes = document.querySelectorAll('.' + targetClass);
    checkboxes.forEach(function (cb) {
        cb.checked = sourceCheckbox.checked;
    });
    updateSelectedCount(targetClass);
}

/**
 * Update selected count display
 */
function updateSelectedCount(targetClass) {
    const checked = document.querySelectorAll('.' + targetClass + ':checked').length;
    const countEl = document.getElementById('selectedCount');
    if (countEl) {
        countEl.textContent = checked;
    }
}
