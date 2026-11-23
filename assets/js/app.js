/**
 * FITISIFY OS — CORE APP JAVASCRIPT
 * Handles Admin/Superadmin Modals, Table Searching, Sidebar Toggling, and Toast Notifications
 */

const App = {
    // Toast Notification System
    toast: function(type, message, duration = 4000) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = 'fa-info-circle';
        if (type === 'success') icon = 'fa-check-circle';
        else if (type === 'error' || type === 'danger') icon = 'fa-exclamation-circle';
        else if (type === 'warning') icon = 'fa-triangle-exclamation';

        toast.innerHTML = `
            <i class="fa-solid ${icon}"></i>
            <span>${message}</span>
            <button type="button" class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastFadeOut 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    // Modal Manager
    openModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('show');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    },

    closeModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 200);
        }
    }
};

// Global Function to Toggle Admin / Superadmin App Sidebar & Backdrop
let _lastSidebarToggleTime = 0;
function toggleAppSidebar(forceState) {
    const now = Date.now();
    if (forceState === undefined && (now - _lastSidebarToggleTime) < 250) {
        return; // Debounce guard against duplicate click firing
    }
    _lastSidebarToggleTime = now;

    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('show');
    const shouldOpen = (forceState !== undefined) ? !!forceState : !isOpen;
    if (shouldOpen) {
        sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
        if (window.innerWidth <= 992) document.body.style.overflow = 'hidden';
    } else {
        sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }
}
window.toggleAppSidebar = toggleAppSidebar;

// Global DOM Listeners for Admin / Superadmin
document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Collapse / Mobile Drawer Dismiss Listeners
    const sidebar = document.querySelector('.app-sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const sidebarToggle = document.querySelector('.topbar-toggle-btn');

    if (backdrop) {
        backdrop.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleAppSidebar(false);
        });
    }

    // Close sidebar on outer click on mobile
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('show') && !sidebar.contains(e.target) && (!sidebarToggle || !sidebarToggle.contains(e.target))) {
            toggleAppSidebar(false);
        }
    });

    // Close sidebar when clicking any navigation link on mobile
    if (sidebar) {
        sidebar.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    toggleAppSidebar(false);
                }
            });
        });
    }

    // 2. Table Real-Time Filter / Search
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const targetTable = document.querySelector(input.getAttribute('data-table-search'));
        if (targetTable) {
            input.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase();
                const rows = targetTable.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });

    // 3. Modal Triggers
    document.querySelectorAll('[data-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-modal-target');
            App.openModal(targetId);
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal');
            if (modal) App.closeModal(modal.id);
        });
    });

    // Close modal on backdrop click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                App.closeModal(modal.id);
            }
        });
    });

    // 4. Mobile Navigation Drawer & Backdrop Control
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            const sidebar = document.querySelector('.app-sidebar');
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
        if (window.innerWidth > 768) {
            const drawer = document.getElementById('mobile-nav-drawer');
            const backdrop = document.getElementById('mobile-menu-backdrop');
            if (drawer) drawer.classList.remove('open');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.querySelector('.app-sidebar');
            if (sidebar && sidebar.classList.contains('show')) {
                toggleAppSidebar(false);
            }
            const drawer = document.getElementById('mobile-nav-drawer');
            if (drawer && drawer.classList.contains('open')) {
                toggleMobileNav();
            }
        }
    });
});

// Global Mobile Nav Toggle for Inline Onclick Handlers
function toggleMobileNav() {
    const drawer = document.getElementById('mobile-nav-drawer');
    const backdrop = document.getElementById('mobile-menu-backdrop');
    const btn = document.querySelector('.mobile-menu-toggle');
    
    if (drawer) {
        const isOpen = drawer.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('show', isOpen);
        if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }
}
