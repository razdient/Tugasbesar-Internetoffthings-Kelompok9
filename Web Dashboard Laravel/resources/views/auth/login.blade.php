
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Smart Posture Monitor</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border: 1px solid #e4eaf1;
            border-radius: 10px;
            padding: 35px;
        }

        .brand {
            color: #1e3a5f;
            font-weight: bold;
            letter-spacing: .5px;
        }

        .form-control {
            padding: 11px;
        }

        .btn-primary {
            background: #1e3a5f;
            border-color: #1e3a5f;
            padding: 11px;
        }

        .btn-primary:hover {
            background: #2b5687;
            border-color: #2b5687;
        }
        
    </style>
</head>

<body>

<div class="login-card">

    <div class="text-center mb-4">
        <h4 class="brand">SMART POSTURE MONITOR</h4>
        <p class="text-secondary small">
            Sistem Monitoring Postur Tubuh
        </p>
        <hr>
        <h5 class="fw-bold mt-4">Login</h5>
        <small class="text-secondary">
            Silakan masuk ke akun Anda
        </small>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger small">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input
                type="email"
                name="email"
                class="form-control"
                value="{{ old('email') }}"
                placeholder="Masukkan email"
                required
                autofocus
            >
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <input
                type="password"
                name="password"
                class="form-control"
                placeholder="Masukkan password"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary w-100">
            Masuk
        </button>
    </form>

    <p class="text-center text-secondary small mt-4 mb-0">
        Smart Posture Monitor © 2026
    </p>

</div>

</body>
</html>
