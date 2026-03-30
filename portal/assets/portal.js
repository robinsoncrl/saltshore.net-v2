/**
 * Saltshore Owner Portal — Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Mobile sidebar toggle ─────────────────────────────────────────────
    const menuToggle = document.getElementById('portal-menu-toggle');
    const sidebar = document.getElementById('portal-sidebar');
    const sidebarBackdrop = document.querySelector('[data-sidebar-close]');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('is-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('is-open');
        if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    }

    function toggleSidebar() {
        if (!sidebar) return;
        const isOpen = sidebar.classList.toggle('is-open');
        if (sidebarBackdrop) sidebarBackdrop.classList.toggle('is-open', isOpen);
        if (menuToggle) menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', toggleSidebar);
    }
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
    document.querySelectorAll('.portal-nav a').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    // ── Flash messages: auto-dismiss after 4s ──────────────────────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.4s';
            el.style.opacity   = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // ── Confirm destructive actions ────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // ── Table row highlight on click (visual affordance) ──────────────────
    document.querySelectorAll('.data-table tbody tr').forEach(function (row) {
        row.style.cursor = 'default';
    });

    // ── Earnings calculator (FinPro) ───────────────────────────────────────
    const rateInput  = document.getElementById('calc-rate');
    const hoursInput = document.getElementById('calc-hours');
    const resultEl   = document.getElementById('calc-result');

    function updateCalc() {
        if (!rateInput || !hoursInput || !resultEl) return;
        const rate  = parseFloat(rateInput.value)  || 0;
        const hours = parseFloat(hoursInput.value) || 0;
        const total = (rate * hours).toFixed(2);
        resultEl.textContent = '$' + parseFloat(total).toLocaleString('en-US', { minimumFractionDigits: 2 });
    }

    if (rateInput)  rateInput.addEventListener('input', updateCalc);
    if (hoursInput) hoursInput.addEventListener('input', updateCalc);
    updateCalc();

    // ── CSV / file upload preview label ───────────────────────────────────
    const fileInput = document.getElementById('csv-upload');
    const fileLabel = document.getElementById('csv-label');
    if (fileInput && fileLabel) {
        fileInput.addEventListener('change', function () {
            fileLabel.textContent = fileInput.files.length
                ? fileInput.files[0].name
                : 'Choose file…';
        });
    }

    // ── Reports: toggle date range visibility ─────────────────────────────
    const periodSelect = document.getElementById('report-period');
    const customRange  = document.getElementById('custom-range');
    if (periodSelect && customRange) {
        periodSelect.addEventListener('change', function () {
            customRange.style.display = periodSelect.value === 'custom' ? 'flex' : 'none';
        });
    }

    // ── Reusable modals (Management + CalGen scheduling) ─────────────────
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-modal-open');
            openModal(document.getElementById(id));
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.closest('.portal-modal'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.portal-modal.is-open').forEach(function (m) { closeModal(m); });
    });

});
