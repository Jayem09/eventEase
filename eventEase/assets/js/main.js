// EventEase - Main JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initFormValidation();
    initEventActions();
    initSearchAndFilter();
    initModalHandlers();
    initJoinHandlers();
});

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showFieldError(input, 'This field is required');
            isValid = false;
        } else {
            clearFieldError(input);
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                showFieldError(input, 'Please enter a valid email address');
                isValid = false;
            }
        }
        
        // Date validation
        if (input.type === 'date' && input.value) {
            const selectedDate = new Date(input.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                showFieldError(input, 'Event date cannot be in the past');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

function showFieldError(input, message) {
    clearFieldError(input);
    input.classList.add('error');
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    
    input.parentNode.appendChild(errorDiv);
}

function clearFieldError(input) {
    input.classList.remove('error');
    const errorDiv = input.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

// Event actions (approve, decline, delete)
function initEventActions() {
    const actionButtons = document.querySelectorAll('[data-action]');
    
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const action = this.dataset.action;
            const eventId = this.dataset.eventId;
            const confirmMessage = this.dataset.confirm;
            
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }
            
            performEventAction(action, eventId, this);
        });
    });
}

function performEventAction(action, eventId, button) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('event_id', eventId);
    formData.append('ajax', '1');
    
    // Show loading state
    const originalText = button.textContent;
    button.textContent = 'Processing...';
    button.disabled = true;
    
    // Get the current page path to determine the correct URL
    const currentPath = window.location.pathname;
    const isAdminPage = currentPath.includes('/admin/');
    const baseUrl = isAdminPage ? '' : '../';
    
    fetch(baseUrl + 'admin/events.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // Reload page or update UI
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'danger');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

// Search and filter functionality
function initSearchAndFilter() {
    const searchInput = document.getElementById('search-events');
    const filterSelect = document.getElementById('filter-status');
    
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterEvents, 300));
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', filterEvents);
    }
}

function filterEvents() {
    const searchTerm = document.getElementById('search-events')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('filter-status')?.value || '';
    const eventCards = document.querySelectorAll('.event-card');
    
    eventCards.forEach(card => {
        const title = card.querySelector('.event-title')?.textContent.toLowerCase() || '';
        const description = card.querySelector('.event-description')?.textContent.toLowerCase() || '';
        const status = card.querySelector('.badge')?.textContent.toLowerCase() || '';
        
        const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
        const matchesStatus = !statusFilter || status.includes(statusFilter);
        
        if (matchesSearch && matchesStatus) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// JOIN functionality
function initJoinHandlers() {
    const joinButtons = document.querySelectorAll('[data-rsvp]');
    
    joinButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const eventId = this.dataset.eventId;
            const action = this.dataset.rsvp;
            
            performJoinAction(eventId, action, this);
        });
    });
}

function performJoinAction(eventId, action, button) {
    const formData = new FormData();
    formData.append('event_id', eventId);
    formData.append('action', action);
    
    // Show loading state
    const originalText = button.textContent;
    button.textContent = 'Processing...';
    button.disabled = true;
    
    fetch('user/rsvp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            // Update button state
            updateJoinButton(button, action);
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred. Please try again.', 'danger');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

function updateJoinButton(button, action) {
    const eventCard = button.closest('.event-card');
    const allButtons = eventCard.querySelectorAll('[data-rsvp]');
    
    // Remove active class from all buttons
    allButtons.forEach(btn => btn.classList.remove('btn-success'));
    
    // Add active class to clicked button
    if (action === 'attending') {
        button.classList.add('btn-success');
    }
}

// Modal handlers
function initModalHandlers() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modalCloses = document.querySelectorAll('.modal-close, .modal-overlay');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.dataset.modal;
            openModal(modalId);
        });
    });
    
    modalCloses.forEach(close => {
        close.addEventListener('click', function() {
            closeModal();
        });
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
}

// Utility functions
function showAlert(message, type) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <span>${message}</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alert.parentElement) {
            alert.remove();
        }
    }, 5000);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    container.style.maxWidth = '400px';
    
    document.body.appendChild(container);
    return container;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Date formatting
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Capacity indicator
function updateCapacityIndicator(current, max) {
    const percentage = (current / max) * 100;
    let color = '#28a745'; // Green
    
    if (percentage >= 90) {
        color = '#dc3545'; // Red
    } else if (percentage >= 75) {
        color = '#ffc107'; // Yellow
    }
    
    return `<span style="color: ${color}">${current}/${max}</span>`;
}

// Export functions for global use
window.EventEase = {
    showAlert,
    openModal,
    closeModal,
    formatDate,
    updateCapacityIndicator
};
