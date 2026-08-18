// ============================================================
// FILE: assets/js/script.js
// FUNGSI: JavaScript utama aplikasi SPK-EV
// ============================================================

'use strict';

// ─────────────────────────────────────────
// DOM READY
// ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    initSidebarToggle();
    initTabSystem();
    initAnimations();
    initProgressBars();
    initMatrixHelpers();
    initAutoHideAlert();
    initFormValidation();
    initTooltips();
});

// ─────────────────────────────────────────
// SIDEBAR TOGGLE (Mobile)
// ─────────────────────────────────────────
function initSidebarToggle() {
    const toggle  = document.querySelector('.sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.createElement('div');

    if (!sidebar) return;

    overlay.id    = 'sidebar-overlay';
    overlay.style.cssText = `
        display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5);
        z-index:199; backdrop-filter:blur(2px);
    `;
    document.body.appendChild(overlay);

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        });
    }

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
    });
}

// ─────────────────────────────────────────
// TAB SYSTEM
// ─────────────────────────────────────────
function initTabSystem() {
    // Global tab handler (untuk halaman yang tidak punya inline showTab)
    window.showTab = function (tabId, btn) {
        const parent = btn.closest('[data-tab-group]') || document;
        const panes  = (btn.closest('.tab-nav') || btn.parentElement)
                       .nextElementSibling;
        const allPanes = parent.querySelectorAll('.tab-pane');
        const allBtns  = btn.closest('.tab-nav').querySelectorAll('.tab-btn');

        allBtns.forEach(b => b.classList.remove('active'));
        allPanes.forEach(p => p.classList.remove('active'));

        btn.classList.add('active');
        const target = document.getElementById(tabId);
        if (target) target.classList.add('active');
    };
}

// ─────────────────────────────────────────
// SCROLL ANIMATIONS
// ─────────────────────────────────────────
function initAnimations() {
    if (!window.IntersectionObserver) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-up').forEach(el => {
        el.style.animationPlayState = 'paused';
        observer.observe(el);
    });
}

// ─────────────────────────────────────────
// PROGRESS BARS ANIMATION
// ─────────────────────────────────────────
function initProgressBars() {
    if (!window.IntersectionObserver) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const fills = entry.target.querySelectorAll('.progress-fill, .cc-bar-fill');
                fills.forEach(fill => {
                    const targetW = fill.style.width;
                    fill.style.width = '0%';
                    setTimeout(() => { fill.style.width = targetW; }, 100);
                });
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    document.querySelectorAll('.card, .rank-card, .bobot-card').forEach(el => {
        observer.observe(el);
    });
}

// ─────────────────────────────────────────
// AHP MATRIX HELPERS
// ─────────────────────────────────────────
function initMatrixHelpers() {
    // Update sel resiprokal otomatis
    document.querySelectorAll('.matrix-input').forEach(inp => {
        inp.addEventListener('input', function () {
            updateReciprocalCell(this);
        });
        inp.addEventListener('change', function () {
            updateReciprocalCell(this);
        });
    });

    // Trigger awal
    document.querySelectorAll('.matrix-input').forEach(inp => {
        const event = new Event('change');
        inp.dispatchEvent(event);
    });
}

function updateReciprocalCell(inp) {
    const parts = inp.id.split('_');
    if (parts.length < 3) return;
    const i = parseInt(parts[1]);
    const j = parseInt(parts[2]);

    let val = parseFloat(inp.value);
    if (isNaN(val) || val <= 0) val = 1;
    val = Math.max(1 / 9, Math.min(9, val));

    const recip   = 1 / val;
    const display = document.getElementById('r_' + j + '_' + i);
    const hidden  = document.getElementById('h_' + j + '_' + i);

    if (display) display.textContent = recip.toFixed(3);
    if (hidden)  hidden.value = recip.toFixed(6);
}

// Reset matriks ke 1
window.resetMatrix = function () {
    if (!confirm('Reset semua nilai matriks menjadi 1.000?')) return;
    document.querySelectorAll('.matrix-input').forEach(inp => {
        inp.value = '1.000';
        inp.dispatchEvent(new Event('change'));
    });
};

// Isi contoh nilai (untuk AHP 5 kriteria)
window.isiContoh = function () {
    const contoh = {
        '0_1': 2, '0_2': 3, '0_3': 4, '0_4': 5,
        '1_2': 2, '1_3': 3, '1_4': 4,
        '2_3': 2, '2_4': 3,
        '3_4': 2,
    };
    Object.entries(contoh).forEach(([key, val]) => {
        const inp = document.getElementById('m_' + key);
        if (inp) {
            inp.value = val;
            inp.dispatchEvent(new Event('change'));
        }
    });
    showToast('✅ Contoh nilai berhasil diisi! Klik "Hitung Bobot AHP" untuk memproses.', 'success');
};

// ─────────────────────────────────────────
// AUTO-HIDE ALERT
// ─────────────────────────────────────────
function initAutoHideAlert() {
    document.querySelectorAll('.alert').forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease, margin 0.5s ease';
                alert.style.opacity    = '0';
                alert.style.marginBottom = '0';
                setTimeout(() => alert.remove(), 500);
            }, 4000);
        }
    });
}

// ─────────────────────────────────────────
// FORM VALIDATION
// ─────────────────────────────────────────
function initFormValidation() {
    // Validasi form AHP: pastikan semua input terisi
    const formAHP = document.getElementById('formAHP');
    if (formAHP) {
        formAHP.addEventListener('submit', function (e) {
            const inputs = formAHP.querySelectorAll('.matrix-input');
            let valid = true;
            inputs.forEach(inp => {
                const val = parseFloat(inp.value);
                if (isNaN(val) || val <= 0) {
                    inp.style.borderColor = 'var(--danger)';
                    valid = false;
                } else {
                    inp.style.borderColor = '';
                }
            });
            if (!valid) {
                e.preventDefault();
                showToast('❌ Pastikan semua nilai matriks terisi dengan benar (1/9 hingga 9).', 'danger');
            }
        });
    }

    // Password confirmation
    const pw1 = document.getElementById('password');
    const pw2 = document.getElementById('password2');
    if (pw1 && pw2) {
        pw2.addEventListener('input', function () {
            if (pw1.value !== pw2.value) {
                pw2.style.borderColor = 'var(--danger)';
            } else {
                pw2.style.borderColor = 'var(--primary)';
            }
        });
    }
}

// ─────────────────────────────────────────
// TOOLTIPS
// ─────────────────────────────────────────
function initTooltips() {
    document.querySelectorAll('[title]').forEach(el => {
        // Simple native title is fine; can upgrade to custom later
    });
}

// ─────────────────────────────────────────
// TOAST NOTIFICATION
// ─────────────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
    const existing = document.getElementById('toast-container');
    if (existing) existing.remove();

    const colors = {
        success: { bg: '#ECFDF5', border: '#6EE7B7', text: '#065F46' },
        danger:  { bg: '#FEF2F2', border: '#FCA5A5', text: '#991B1B' },
        warning: { bg: '#FFFBEB', border: '#FCD34D', text: '#92400E' },
        info:    { bg: '#EFF6FF', border: '#BFDBFE', text: '#1E40AF' },
    };
    const c = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.id    = 'toast-container';
    toast.style.cssText = `
        position: fixed;
        bottom: 24px; right: 24px;
        background: ${c.bg};
        border: 1.5px solid ${c.border};
        color: ${c.text};
        padding: 14px 20px;
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 500;
        max-width: 380px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        z-index: 9999;
        animation: slideUp 0.3s ease;
        font-family: 'Plus Jakarta Sans', sans-serif;
        line-height: 1.5;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.4s ease forwards';
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

// Animasi toast
const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideUp {
        from { opacity:0; transform:translateY(16px); }
        to   { opacity:1; transform:translateY(0); }
    }
    @keyframes fadeOut {
        to { opacity:0; transform:translateY(8px); }
    }
`;
document.head.appendChild(toastStyle);

// ─────────────────────────────────────────
// PRINT HELPER
// ─────────────────────────────────────────
window.printLaporan = function () {
    window.print();
};

// ─────────────────────────────────────────
// KONFIRMASI HAPUS
// ─────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
        const msg = this.dataset.confirm || 'Apakah Anda yakin?';
        if (!confirm(msg)) e.preventDefault();
    });
});

// ─────────────────────────────────────────
// UTILITY
// ─────────────────────────────────────────
function formatNumber(num, decimals = 2) {
    return Number(num).toFixed(decimals);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('✅ Berhasil disalin!', 'success', 2000);
    });
}
