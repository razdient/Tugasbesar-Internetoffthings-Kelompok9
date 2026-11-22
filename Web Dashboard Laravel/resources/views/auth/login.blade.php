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
    <title>Login - Smart Posture Monitor</title>
</head>
<body class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-100 px-4 py-10 font-sans text-slate-600 dark:bg-slate-950 dark:text-slate-300">

    <div class="pointer-events-none absolute -top-24 -right-24 h-72 w-72 rounded-full bg-primary-200/60 blur-3xl dark:bg-primary-950/50"></div>
    <div class="pointer-events-none absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-indigo-200/60 blur-3xl dark:bg-indigo-950/50"></div>

    <div class="card relative w-full max-w-md p-8">
        <div class="mb-8 flex flex-col items-center text-center">
            <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-600 text-white shadow-lg shadow-primary-600/30">
                <i data-lucide="Activity" class="h-7 w-7"></i>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Smart Posture</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Sistem Monitoring Postur Tubuh</p>
        </div>

        <h2 class="mb-6 text-lg font-semibold text-slate-900 dark:text-white">Login</h2>
        <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">Silakan masuk ke akun Anda</p>

        @if ($errors->any())
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/60 dark:bg-rose-950/40">
                <i data-lucide="AlertCircle" class="mt-0.5 h-5 w-5 shrink-0 text-rose-500"></i>
                <p class="text-sm font-medium text-rose-700 dark:text-rose-300">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Email</label>
                <div class="relative">
                    <i data-lucide="Mail" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"></i>
                    <input id="email" type="email" name="email" class="input pl-11" value="{{ old('email') }}" placeholder="Masukkan email" required autofocus>
                </div>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Password</label>
                <div class="relative">
                    <i data-lucide="Lock" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"></i>
                    <input id="password" type="password" name="password" class="input pl-11 pr-11" placeholder="Masukkan password" required>
                    <button id="password-toggle" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-200" aria-label="Toggle password visibility">
                        <span id="password-icon-off"><i data-lucide="EyeOff" class="h-5 w-5"></i></span>
                        <span id="password-icon-on" class="hidden"><i data-lucide="Eye" class="h-5 w-5"></i></span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary w-full py-3 text-base">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
            Smart Posture Monitor © 2026
        </p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.getElementById('password-toggle');
            var input = document.getElementById('password');
            var iconOff = document.getElementById('password-icon-off');
            var iconOn = document.getElementById('password-icon-on');

            if (toggle && input) {
                toggle.addEventListener('click', function () {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    if (iconOff) iconOff.classList.toggle('hidden', show);
                    if (iconOn) iconOn.classList.toggle('hidden', !show);
                    toggle.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                });
            }
        });
    </script>
</body>
</html>
