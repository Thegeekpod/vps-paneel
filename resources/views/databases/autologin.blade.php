<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Database Manager...</title>
    <style>
        body {
            background: #070b14;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .box {
            text-align: center;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 2.5rem 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 420px;
        }
        .spinner {
            border: 3px solid rgba(99, 102, 241, 0.15);
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            animation: spin 0.7s linear infinite;
            margin: 0 auto 1.25rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            color: #fff;
        }
        p {
            color: #94a3b8;
            font-size: 0.875rem;
            margin: 0;
        }
        .db-badge {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            font-family: monospace;
            font-size: 0.8rem;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <h2>Opening Database Manager</h2>
        <p>Connecting securely with saved credentials...</p>
        <div class="db-badge">{{ $database->name }} ({{ $driver === 'pgsql' ? 'PostgreSQL' : 'MySQL' }})</div>
    </div>

    <!-- Hidden form to auto-post credentials into Adminer without manual login -->
    <form id="autoLoginForm" method="POST" action="{{ route('databases.manager') }}">
        <input type="hidden" name="auth[driver]" value="{{ $driver }}">
        <input type="hidden" name="auth[server]" value="{{ $host }}">
        <input type="hidden" name="auth[username]" value="{{ $username }}">
        <input type="hidden" name="auth[password]" value="{{ $password }}">
        <input type="hidden" name="auth[db]" value="{{ $database->name }}">
        <input type="hidden" name="auth[permanent]" value="1">
    </form>

    <script>
        // Submit instantly on page load
        document.getElementById('autoLoginForm').submit();
    </script>
</body>
</html>
