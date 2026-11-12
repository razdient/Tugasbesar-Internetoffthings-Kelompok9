
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Smart Posture Monitor')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #1e3a5f;
            --accent: #3478c9;
            --bg: #f4f7fb;
            --text: #26364b;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 245px;
            height: 100vh;
            position: fixed;
            background: var(--primary);
            color: white;
            padding: 25px 18px;
        }

        .sidebar h5 {
            font-weight: bold;
            letter-spacing: .5px;
        }

        .sidebar a {
            display: block;
            padding: 12px 15px;
            color: #d8e4f2;
            text-decoration: none;
            border-radius: 7px;
            margin-top: 8px;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: #2b5687;
            color: white;
        }

        .main {
            margin-left: 245px;
            padding: 30px;
        }

        .topbar, .stat-card, .content-card {
            background: white;
            border: 1px solid #e4eaf1;
            border-radius: 10px;
        }

        .topbar {
            padding: 18px 22px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 22px;
            height: 100%;
        }

        .stat-label {
            color: #7b8ba0;
            font-size: 13px;
        }

        .stat-value {
            color: var(--primary);
            font-size: 29px;
            font-weight: bold;
            margin-top: 10px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            background: #e8f1fc;
            color: var(--accent);
            border-radius: 9px;
            font-size: 20px;
        }

        .content-card {
            padding: 22px;
        }

        .content-title {
            font-size: 17px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 18px;
        }

        .table thead th {
            background: #f2f6fb;
            color: #62748a;
            font-size: 12px;
        }

        .table td {
            font-size: 13px;
            vertical-align: middle;
        }

        .logout {
            position: absolute;
            bottom: 25px;
            left: 18px;
            right: 18px;
        }

        @media(max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .logout {
                position: static;
                margin-top: 20px;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>

    @stack('styles')
</head>

<body>

<aside class="sidebar">
    <h5>SMART POSTURE</h5>
    <small class="text-white-50">Monitoring System</small>

    <hr class="border-light opacity-25 my-4">

    <small class="text-white-50">MENU UTAMA</small>

    <a href="{{ route('dashboard') }}"
       class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
        Dashboard
    </a>

    <a href="{{ route('dashboard') }}#grafik">
        Monitoring Sensor
    </a>

    <a href="{{ route('dashboard') }}#riwayat">
        Riwayat Postur
    </a>

    <a href="{{ route('history') }}"
       class="{{ request()->routeIs('history') ? 'active' : '' }}">
        Riwayat Data Sensor
    </a>

    <div class="logout">
        <small class="text-white-50 d-block mb-2">
            {{ auth()->user()->name }}
        </small>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-light btn-sm w-100">
                Logout
            </button>
        </form>
    </div>
</aside>

<main class="main">

    <div class="topbar d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1">@yield('page-title', 'Dashboard')</h5>
            <small class="text-secondary">
                Sistem Monitoring Postur Tubuh Berbasis IoT
            </small>
        </div>

        <small class="text-secondary">
            {{ now()->timezone('Asia/Jakarta')->format('d M Y') }}
        </small>
    </div>

    @yield('content')

    <div class="text-center text-secondary small mt-4">
        Smart Posture Monitor © 2026
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

@stack('scripts')

</body>
</html>
