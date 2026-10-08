<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VPS Control Panel</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#070b14] text-slate-200 font-sans antialiased selection:bg-indigo-500 selection:text-white min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- Glowing Background Blobs -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/20 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-500 text-white shadow-xl shadow-indigo-500/30 mb-4 transform hover:scale-105 transition">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">VPS Control Panel</h1>
            <p class="text-xs text-slate-400 mt-1">Sign in to manage your servers, Next.js, Laravel & WordPress apps</p>
        </div>

        <!-- Login Card -->
        <div class="p-8 rounded-3xl bg-[#0b101e]/90 backdrop-blur-xl border border-white/10 shadow-2xl space-y-6">

            @if(session('info'))
                <div class="p-3.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 text-xs">
                    {{ session('info') }}
                </div>
            @endif

            @if($errors->any())
                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs space-y-1">
                    @foreach($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@vps-panel.local') }}" required autofocus
                        class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-medium text-slate-300">Password</label>
                    </div>
                    <input type="password" name="password" required value="password"
                        class="w-full bg-white/[0.03] border border-white/10 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition">
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1" checked class="rounded border-slate-700 text-indigo-600 focus:ring-0">
                        <span class="text-xs text-slate-400">Remember this device</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-semibold shadow-lg shadow-indigo-600/30 transition transform hover:-translate-y-0.5">
                        Sign In to Dashboard
                    </button>
                </div>
            </form>

            <!-- Quick Demo Credentials Info Box -->
            <div class="p-3.5 rounded-xl bg-indigo-950/40 border border-indigo-500/20 text-xs text-slate-400 space-y-1">
                <div class="font-semibold text-indigo-300 text-[11px] uppercase tracking-wider">Default Admin Credentials</div>
                <div class="font-mono text-slate-300 text-[11px] flex items-center justify-between">
                    <span>Email: <strong class="text-white">admin@vps-panel.local</strong></span>
                    <span>Pass: <strong class="text-white">password</strong></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6 text-xs text-slate-500">
            VPS Paneel • Automated Server Provisioner & App Manager
        </div>
    </div>

</body>
</html>
