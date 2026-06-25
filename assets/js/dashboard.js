/**
 * assets/js/dashboard.js
 * SALON PRO - Dashboard Interactions & UI Enhancements
 * Features: Tooltips, Sidebar Toggle, Dynamic Stats, Appointment Cards
 */

document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // 1. TOOLTIPS FOR ICONS (Desktop Only)
    // ============================================
    const tooltipElements = document.querySelectorAll('[data-tooltip]');

    tooltipElements.forEach(function(el) {
        let tooltip = null;

        el.addEventListener('mouseenter', function(e) {
            // Create tooltip element
            tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);

            // Position tooltip
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - 35) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
        });

        el.addEventListener('mouseleave', function() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        });
    });

    // ============================================
    // 2. SIDEBAR TOGGLE (Already in header.php, but fallback)
    // ============================================
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');

    if (hamburgerBtn && sidebar && overlay) {
        // Open
        hamburgerBtn.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });

        // Close via overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });

        // Close via X button
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }

        // Close via Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }

    // ============================================
    // 3. ACTIVE NAV LINK HIGHLIGHT
    // ============================================
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.sidebar-menu a');

    navLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });

    // ============================================
    // 4. APPOINTMENT CARD INTERACTIONS
    // ============================================
    const appointmentCards = document.querySelectorAll('.appointment-card');

    appointmentCards.forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Don't trigger if clicking on a button/link inside
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                return;
            }
            // Optional: Expand or show details
            this.classList.toggle('expanded');
        });
    });

    // ============================================
    // 5. DYNAMIC STATS COUNTER (Animated Numbers)
    // ============================================
    const statNumbers = document.querySelectorAll('.stat-number, .dashboard-card .number');

    statNumbers.forEach(function(el) {
        const target = parseInt(el.textContent.replace(/[^0-9.]/g, ''));
        if (target > 0 && target < 10000) {
            let current = 0;
            const increment = Math.ceil(target / 30);
            const duration = 600;
            const stepTime = Math.floor(duration / 30);

            const timer = setInterval(function() {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                el.textContent = current.toLocaleString();
                // Re-add KSh if it was there
                if (el.textContent.includes('KSh')) {
                    el.textContent = 'KSh ' + current.toLocaleString();
                }
            }, stepTime);
        }
    });

    // ============================================
    // 6. CONFIRM DIALOGS FOR DESTRUCTIVE ACTIONS
    // ============================================
    const confirmLinks = document.querySelectorAll('[data-confirm]');

    confirmLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // ============================================
    // 7. SEARCH INPUT CLEAR BUTTON (if present)
    // ============================================
    const searchInputs = document.querySelectorAll('.search-input');

    searchInputs.forEach(function(input) {
        const wrapper = input.closest('.search-wrapper');
        if (wrapper) {
            const clearBtn = wrapper.querySelector('.search-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    input.focus();
                });
            }
        }
    });

    // ============================================
    // 8. RESPONSIVE TABLE WRAPPER (Ensure tables scroll)
    // ============================================
    const tables = document.querySelectorAll('table');
    tables.forEach(function(table) {
        const wrapper = table.closest('.table-wrapper');
        if (!wrapper) {
            // Wrap table in a scrollable container if not already wrapped
            const parent = table.parentNode;
            const wrapperDiv = document.createElement('div');
            wrapperDiv.className = 'table-wrapper';
            parent.insertBefore(wrapperDiv, table);
            wrapperDiv.appendChild(table);
        }
    });

    // ============================================
    // 9. NOTIFICATION TOAST (Example - Can be expanded)
    // ============================================
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + (type || 'info');
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(function() {
            toast.classList.add('show');
        }, 100);

        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    }

    // ============================================
    // 10. SIDEBAR CLOSE ON LINK CLICK (Mobile UX)
    // ============================================
    if (sidebar) {
        const menuLinks = sidebar.querySelectorAll('a');
        menuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                // Close sidebar on mobile after clicking a link (except logout)
                if (!this.href.includes('logout.php') && window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    }

    // ============================================
    // 11. USER BADGE CLICK (Show profile dropdown - optional)
    // ============================================
    const userBadge = document.getElementById('userBadge');
    if (userBadge) {
        userBadge.addEventListener('click', function() {
            // Optional: Toggle profile dropdown or navigate to profile
            const profileLink = this.closest('.header-right').querySelector('a[href*="profile"]');
            if (profileLink) {
                window.location.href = profileLink.href;
            }
        });
    }

    // ============================================
    // 12. DEBUG: LOG USER ROLE (Remove in production)
    // ============================================
    const userRole = document.querySelector('meta[name="user-role"]');
    if (userRole) {
        console.log('Salon Pro - User Role: ' + userRole.getAttribute('content'));
    }

    console.log('Salon Pro Dashboard initialized ✅');

});

// ============================================
// GLOBAL FUNCTIONS (Available everywhere)
// ============================================

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - 'success', 'error', 'info', 'warning'
 */
function notify(message, type) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification toast-' + (type || 'info');
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(function() {
        toast.classList.add('show');
    }, 100);

    setTimeout(function() {
        toast.classList.remove('show');
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, 3000);
}

/**
 * Format currency (KSh)
 * @param {number} amount - The amount to format
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount) {
    return 'KSh ' + amount.toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Get status badge class
 * @param {string} status - The status string
 * @returns {string} CSS class name
 */
function getStatusClass(status) {
    const statusMap = {
        'confirmed': 'status-confirmed',
        'pending': 'status-pending',
        'completed': 'status-completed',
        'cancelled': 'status-cancelled',
        'served': 'status-served'
    };
    return statusMap[status] || 'status-pending';
}