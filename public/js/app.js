// App-wide JavaScript utilities

// Theme: 'light' | 'dark' | 'system' (the initial value is applied inline in header.php)
const darkQuery = matchMedia('(prefers-color-scheme: dark)');

function applyTheme(pref) {
    const dark = pref === 'dark' || (pref === 'system' && darkQuery.matches);
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    document.documentElement.dataset.themePref = pref;
    document.querySelectorAll('[data-theme-set]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.themeSet === pref)));
    restyleCharts();
}

// Chart colors that depend on the theme come from CSS variables
function chartTheme() {
    const css = getComputedStyle(document.documentElement);
    const v = name => css.getPropertyValue(name).trim();
    return { ink: v('--ink'), muted: v('--muted'), grid: v('--chart-grid') };
}

function restyleCharts() {
    if (!window.Chart) return;
    const t = chartTheme();
    Chart.defaults.color = t.muted;
    Object.values(Chart.instances).forEach(chart => {
        Object.values(chart.options.scales || {}).forEach(s => {
            if (s.grid && s.grid.display !== false) s.grid.color = t.grid;
            if (s.ticks) s.ticks.color = s.grid && s.grid.display === false ? t.ink : t.muted;
        });
        chart.update('none');
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const pref = document.documentElement.dataset.themePref || 'system';
    document.querySelectorAll('[data-theme-set]').forEach(btn => {
        btn.setAttribute('aria-checked', String(btn.dataset.themeSet === pref));
        btn.addEventListener('click', () => {
            try { localStorage.setItem('theme', btn.dataset.themeSet); } catch (e) {}
            applyTheme(btn.dataset.themeSet);
        });
    });
    darkQuery.addEventListener('change', () => {
        if (document.documentElement.dataset.themePref === 'system') applyTheme('system');
    });

    // Auto-hide flash messages after 5s (add .alert-static to keep one visible)
    document.querySelectorAll('.alert:not(.alert-static)').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 500);
        }, 5000);
    });
});
