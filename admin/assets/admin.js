document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize file upload preview
    initFileUploadPreview();
});

function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.dataset.tooltip;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
}

function hideTooltip() {
    document.querySelector('.tooltip')?.remove();
}

function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });
}

function validateForm(e) {
    const required = e.target.querySelectorAll('[required]');
    let valid = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            valid = false;
            
            // Show error message
            const error = document.createElement('span');
            error.className = 'field-error';
            error.textContent = 'This field is required';
            field.parentElement.appendChild(error);
        } else {
            field.classList.remove('error');
            field.parentElement.querySelector('.field-error')?.remove();
        }
    });
    
    if (!valid) {
        e.preventDefault();
    }
}

function initFileUploadPreview() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('img');
                    preview.src = e.target.result;
                    preview.className = 'upload-preview';
                    
                    const container = input.parentElement;
                    const existingPreview = container.querySelector('.upload-preview');
                    if (existingPreview) {
                        container.replaceChild(preview, existingPreview);
                    } else {
                        container.appendChild(preview);
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

// Bulk actions
function selectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.select-item');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function bulkAction(action) {
    const selected = [];
    document.querySelectorAll('.select-item:checked').forEach(cb => {
        selected.push(cb.value);
    });
    
    if (selected.length === 0) {
        alert('Please select items');
        return;
    }
    
    if (confirm(`Are you sure you want to ${action} ${selected.length} item(s)?`)) {
        // Perform bulk action via AJAX
        fetch('api/bulk-action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: action,
                ids: selected
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Export data
function exportData(type) {
    window.location.href = `api/export.php?type=${type}`;
}