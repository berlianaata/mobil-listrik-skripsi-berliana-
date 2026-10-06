<?php
// ============================================================
// FILE: includes/icons.php
// FUNGSI: Ikon garis SVG (24x24, stroke) yang dipakai di seluruh aplikasi.
// Pemakaian: echo icon('bolt'); atau echo icon('check', 'icon-sm');
// ============================================================

function icon(string $name, string $class = ''): string
{
    static $paths = [
        'bolt'     => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>',
        'car'      => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2"/><path d="M3 12h18"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/>',
        'building' => '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M9 9h.01M12 9h.01M15 9h.01M9 12.5h.01M12 12.5h.01M15 12.5h.01"/>',
        'gauge'    => '<path d="M4 18a9 9 0 1 1 16 0"/><path d="m12 14 4-5"/><circle cx="12" cy="14" r="1.2"/>',
        'calc'     => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/>',
        'scale'    => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="m5 7-3 7a3.5 3.5 0 0 0 6 0L5 7zM19 7l-3 7a3.5 3.5 0 0 0 6 0l-3-7z"/>',
        'sliders'  => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'trophy'   => '<path d="M8 4h8v5a4 4 0 0 1-8 0V4zM8 6H4v1a4 4 0 0 0 4 4M16 6h4v1a4 4 0 0 1-4 4M12 13v4M8 21h8M10 17h4"/>',
        'chart'    => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'database' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'filter'   => '<path d="M3 5h18l-7 8v6l-4-2v-4L3 5z"/>',
        'doc'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert'    => '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v5M12 18h.01"/>',
        'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M5 20h14"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'shield'   => '<path d="M12 3 5 6v6c0 4.5 3 8 7 9 4-1 7-4.5 7-9V6l-7-3z"/><path d="m9 12 2 2 4-4"/>',
        'list'     => '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'seat'     => '<path d="M7 4h4l1 8h5l3 5v3H8l-1-9V4z"/>',
        'battery'  => '<rect x="2" y="8" width="17" height="9" rx="2"/><path d="M22 11v3M6 12v1M10 12v1"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/>',
        'print'    => '<path d="M7 9V3h10v6M7 17H4v-6h16v6h-3M7 14h10v7H7z"/>',
    ];
    $d   = $paths[$name] ?? $paths['bolt'];
    $cls = trim('svg-icon ' . $class);
    return '<svg class="' . htmlspecialchars($cls, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}
