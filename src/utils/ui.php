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
