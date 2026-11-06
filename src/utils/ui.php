<?php

// Inline SVG icons (stroke style matches the navbar icons)
function icon(string $name, int $size = 16): string {
    $paths = [
        'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash'  => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'check'  => '<path d="M20 6L9 17l-5-5"/>',
        'alert'  => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'link'   => '<path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'print'  => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'home'      => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 14l2 2 4-4"/>',
        'layers'    => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-6"/>',
        'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'award'     => '<circle cx="12" cy="8" r="6"/><path d="M8.2 13.9L7 22l5-3 5 3-1.2-8.1"/>',
        'sliders'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'user-cog'  => '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4"/><circle cx="18" cy="18" r="3"/><path d="M18 13.5v1.5M18 21v1.5M22.5 18H21M15 18h-1.5"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'activity'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function flash(string $msg, string $type = 'ok'): string {
    return $msg === '' ? '' : '<div class="flash flash-' . $type . '" role="status">' . icon($type === 'ok' ? 'check' : 'alert') . htmlspecialchars($msg) . '</div>';
}

// University records are often in ALL CAPS ("BACHELOR OF INFORMATION TECHNOLOGY"). Show them in title case,
// keeping short connecting words lower case and known acronyms upper case. Mixed-case text is left alone.
function nice(?string $s): string {
    $s = trim((string)$s);
    if ($s === '' || $s !== strtoupper($s)) return $s;
    $keepUpper = ['IT', 'ICT', 'MBA', 'UML', 'DBA', 'GIS', 'HIV', 'AIDS', 'II', 'III', 'IV'];
    $lower = ['of', 'and', 'in', 'with', 'for', 'the', 'to', 'on', 'a', '&'];
    $words = preg_split('/\s+/', strtolower($s));
    foreach ($words as $i => $w) {
        if (in_array(strtoupper($w), $keepUpper, true)) $words[$i] = strtoupper($w);
        elseif ($i > 0 && in_array($w, $lower, true)) $words[$i] = $w;
        else $words[$i] = ucfirst($w);
    }
    return implode(' ', $words);
}

// Link to a file in public/ with its modification time appended, so browsers and nginx caches pick up
// new versions after a deploy instead of serving an old stylesheet or script.
function asset(string $path): string {
    $file = __DIR__ . '/../../public' . $path;
    return BASE_URL . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}
