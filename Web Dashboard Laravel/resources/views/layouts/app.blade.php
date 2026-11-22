<!DOCTYPE html>
<html lang="id">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <title>@yield('title', 'Smart Posture Monitor')</title>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-600 antialiased dark:bg-slate-950 dark:text-slate-300">

    <div class="flex min-h-screen">

        <div id="sidebar"
             class="fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-300 ease-in-out dark:border-slate-800 dark:bg-slate-900 -translate-x-full md:translate-x-0">
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-white shadow-sm">
                    <i data-lucide="Activity" class="h-5 w-5"></i>
                </div>
                <div>
                    <p class="text-base font-bold text-slate-900 dark:text-white">Smart Posture</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Monitoring System</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5">
                @php
                    $isDashboardActive = request()->routeIs('dashboard') || request()->routeIs('dashboard.data');
                    $isHistoryActive = request()->routeIs('history') || request()->routeIs('history.export');
                @endphp

                <a href="{{ route('dashboard') }}"
                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {{ $isDashboardActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-white' }}">
                    <i data-lucide="LayoutDashboard" class="h-5 w-5"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('dashboard') }}#grafik"
                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-white">
                    <i data-lucide="Activity" class="h-5 w-5"></i>
                    <span>Monitoring Sensor</span>
                </a>

                <a href="{{ route('dashboard') }}#riwayat"
                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-white">
                    <i data-lucide="AlertTriangle" class="h-5 w-5"></i>
                    <span>Riwayat Postur</span>
                </a>

                <a href="{{ route('history') }}"
                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {{ $isHistoryActive ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-white' }}">
                    <i data-lucide="History" class="h-5 w-5"></i>
                    <span>Riwayat Data Sensor</span>
                </a>
            </nav>

            <div class="border-t border-slate-200 p-4 dark:border-slate-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-500 transition-all duration-200 hover:bg-slate-100 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-slate-800/70 dark:hover:text-rose-400">
                        <i data-lucide="LogOut" class="h-5 w-5"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>

        <div id="sidebar-backdrop"
             class="pointer-events-none fixed inset-0 z-30 bg-slate-950/40 opacity-0 transition-opacity duration-300 md:hidden"></div>

        <div class="flex min-h-screen flex-1 flex-col md:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                    <button id="sidebar-toggle"
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-slate-100 md:hidden dark:text-slate-300 dark:hover:bg-slate-800">
                        <i data-lucide="Menu" class="h-5 w-5"></i>
                    </button>

                    <div class="flex-1"></div>

                    <button id="theme-toggle"
                            type="button"
                            aria-label="Ganti tema"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                        <i data-lucide="Moon" class="h-5 w-5 dark:hidden"></i>
                        <i data-lucide="Sun" class="hidden h-5 w-5 dark:block"></i>
                    </button>

                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                        </div>
                        <div class="hidden sm:block">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Smart Posture Monitor</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>

    <div id="toast-root" class="fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2"></div>

    @stack('scripts')
</body>
</html>
