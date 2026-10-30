/**
 * Smart Mess Management System
 * Main JavaScript File
 * 
 * Contains:
 * - Navigation handling
 * - Form interactions
 * - UI utilities
 * - Mobile menu toggle
 */

// ============================================
// SIDEBAR TOGGLE (Mobile)
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Set active navigation link based on current page
    setActiveNav();
    
    // Initialize star rating if present
    initStarRating();
    
    // Close sidebar when a nav link is clicked (mobile)
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && window.innerWidth < 768) {
                sidebar.classList.remove('active');
            }
        });
    });
});

// ============================================
// SET ACTIVE NAVIGATION LINK
// ============================================
function setActiveNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        // Match the current page with the href
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// ============================================
// FORM VALIDATION
// ============================================
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (input.value.trim() === '') {
            input.classList.add('input-error');
            isValid = false;
        } else {
            input.classList.remove('input-error');
        }
    });

    return isValid;
}

// Handle form submission with success message
function handleFormSubmit(formId, successMessage, callback) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (validateForm(formId)) {
            showSuccessMessage('✓ ' + successMessage);
            if (callback && typeof callback === 'function') {
                callback();
            }
            form.reset();
        } else {
            showErrorMessage('❌ Please fill in all required fields');
        }
    });
}

// ============================================
// ALERT/NOTIFICATION HANDLING
// ============================================
function closeAlert(element) {
    if (element && element.parentElement) {
        element.parentElement.remove();
    }
}

// Show success message (for frontend demo)
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success';
    alertDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button onclick="closeAlert(this)" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: inherit;">×</button>
        </div>
    `;
    
    const container = document.querySelector('.main-content') || document.body;
    container.insertBefore(alertDiv, container.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// Show error message (for frontend demo)
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger';
    alertDiv.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button onclick="closeAlert(this)" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: inherit;">×</button>
        </div>
    `;
    
    const container = document.querySelector('.main-content') || document.body;
    container.insertBefore(alertDiv, container.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

// ============================================
// MODAL HANDLING
// ============================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal when clicking outside of it
window.addEventListener('click', (event) => {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});

// ============================================
// STAR RATING FUNCTIONALITY
// ============================================
function initStarRating() {
    const starElements = document.querySelectorAll('.star');
    if (starElements.length === 0) return;

    const ratingValue = document.getElementById('ratingValue');
    const ratingText = document.getElementById('ratingText');

    const ratingDescriptions = {
        1: '😞 Very Poor - We\'ll work to improve this',
        2: '😕 Poor - Let us know what needs improvement',
        3: '😐 Average - Room for improvement',
        4: '😊 Good - Thank you for the feedback',
        5: '😍 Excellent - Great! Keep it up'
    };

    starElements.forEach(star => {
        // Click to set rating
        star.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            ratingValue.value = value;
            
            // Update star display
            starElements.forEach(s => {
                if (s.getAttribute('data-value') <= value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });

            // Update rating text
            if (ratingText) {
                ratingText.textContent = ratingDescriptions[value];
            }
        });

        // Hover effect
        star.addEventListener('mouseover', function() {
            const hoverValue = this.getAttribute('data-value');
            starElements.forEach(s => {
                if (s.getAttribute('data-value') <= hoverValue) {
                    s.classList.add('hover');
                } else {
                    s.classList.remove('hover');
                }
            });
        });
    });

    // Remove hover effect when mouse leaves
    const starRating = document.getElementById('starRating');
    if (starRating) {
        starRating.addEventListener('mouseleave', function() {
            starElements.forEach(s => s.classList.remove('hover'));
        });
    }
}

// ============================================
// FILTER FUNCTIONALITY
// ============================================
function filterTable(filterId, tableBodySelector, filterableAttribute) {
    const filterSelect = document.getElementById(filterId);
    if (!filterSelect) return;

    const tableBody = document.querySelector(tableBodySelector);
    if (!tableBody) return;

    filterSelect.addEventListener('change', () => {
        const selectedValue = filterSelect.value;
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            if (selectedValue === '') {
                row.style.display = '';
            } else {
                const badgeElement = row.querySelector('.badge');
                if (badgeElement && badgeElement.textContent.toLowerCase().includes(selectedValue.toLowerCase())) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    });
}

// ============================================
// DATE INPUT - Set Today's Date as Default
// ============================================
function setTodayDate(inputId) {
    const dateInput = document.getElementById(inputId);
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.value = today;
    }
}

// ============================================
// INITIALIZE ON DOM READY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    setActiveNav();
    initStarRating();
    filterTable('statusFilter', 'tbody', 'status');
    
    // Set today's date for date inputs
    setTodayDate('mealDate');
    setTodayDate('incidentDate');
});
