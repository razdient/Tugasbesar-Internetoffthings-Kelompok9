import Chart from 'chart.js/auto';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Download,
    Eye,
    EyeOff,
    History,
    Info,
    LayoutDashboard,
    Lock,
    LogOut,
    Mail,
    Menu,
    Moon,
    MoveHorizontal,
    MoveVertical,
    Radar,
    RotateCcw,
    Sun,
    X,
    createIcons,
} from 'lucide';

const icons = {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Download,
    Eye,
    EyeOff,
    History,
    Info,
    LayoutDashboard,
    Lock,
    LogOut,
    Mail,
    Menu,
    Moon,
    MoveHorizontal,
    MoveVertical,
    Radar,
    RotateCcw,
    Sun,
    X,
};

window.Chart = Chart;
window.createIcons = createIcons;

window.refreshIcons = () => {
    if (typeof window.createIcons === 'function') {
        window.createIcons({ icons });
    }
};

// ==============================
// Tema (light / dark)
// ==============================
const applyTheme = (theme) => {
    const root = document.documentElement;
    root.classList.toggle('dark', theme === 'dark');
    root.style.colorScheme = theme;
};

const getInitialTheme = () => {
    try {
        return localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch (e) {
        return 'light';
    }
};

applyTheme(getInitialTheme());

const themeToggle = document.getElementById('theme-toggle');
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        applyTheme(nextTheme);
        try {
            localStorage.setItem('theme', nextTheme);
        } catch (e) {}
    });
}

// ==============================
// Sidebar (mobile drawer)
// ==============================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarBackdrop = document.getElementById('sidebar-backdrop');

const setSidebarOpen = (open) => {
    if (!sidebar) return;
    sidebar.classList.toggle('translate-x-0', open);
    sidebar.classList.toggle('-translate-x-full', !open);

    if (sidebarBackdrop) {
        sidebarBackdrop.classList.toggle('opacity-0', !open);
        sidebarBackdrop.classList.toggle('opacity-100', open);
        sidebarBackdrop.classList.toggle('pointer-events-none', !open);
        sidebarBackdrop.classList.toggle('pointer-events-auto', open);
    }

    document.body.classList.toggle('overflow-hidden', open);
};

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('translate-x-0');
        setSidebarOpen(!isOpen);
    });
}

if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
}

// ==============================
// Toast
// ==============================
window.showToast = (message, type = 'info') => {
    const root = document.getElementById('toast-root');
    if (!root) return;

    const icons = {
        success: 'CheckCircle2',
        error: 'AlertCircle',
        info: 'Info',
    };

    const colors = {
        success: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-950/40',
        error: 'border-rose-200 bg-rose-50 dark:border-rose-900/60 dark:bg-rose-950/40',
        info: 'border-primary-200 bg-primary-50 dark:border-primary-900/60 dark:bg-primary-950/40',
    };

    const el = document.createElement('div');
    el.className = 'pointer-events-auto flex w-full items-start gap-3 rounded-2xl border p-4 shadow-lg dark:border-slate-800 dark:bg-slate-900';
    el.classList.add(colors[type] || colors.info);

    const iconWrapper = document.createElement('div');
    iconWrapper.setAttribute('data-lucide', icons[type] || 'Info');
    iconWrapper.className = 'mt-0.5 h-5 w-5 shrink-0';

    const text = document.createElement('p');
    text.className = 'text-sm font-medium text-slate-700 dark:text-slate-200';
    text.textContent = message;

    el.appendChild(iconWrapper);
    el.appendChild(text);
    root.appendChild(el);

    window.refreshIcons();

    setTimeout(() => {
        el.classList.add('opacity-0', 'translate-y-2', 'scale-95');
        setTimeout(() => {
            el.remove();
        }, 200);
    }, 4000);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.refreshIcons());
} else {
    window.refreshIcons();
}

export {};
