<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign in · CargoTrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-white text-[#080B0C] antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-md bg-[#080B0C] text-[#C6F432]">
                <span class="font-mono text-xs font-bold">CT</span>
            </div>
            <span class="text-base font-semibold tracking-tight">CargoTrace</span>
        </a>

        <h1 class="mt-8 text-2xl font-bold tracking-tight">Sign in to your corridor</h1>
        <p class="mt-1 text-sm opacity-70">Global shipment visibility &amp; tracking platform.</p>

        @if ($errors->any())
            <div class="mt-5 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="text-xs font-semibold">Email address</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="you@cargotrace.io" />
            </div>
            <div>
                <label for="password" class="text-xs font-semibold">Password</label>
                <input id="password" name="password" type="password" required
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="Minimum 8 characters" />
            </div>
            <label class="flex items-center gap-2 text-xs opacity-80">
                <input type="checkbox" name="remember" value="1" class="accent-[#080B0C]" /> Remember me
            </label>
            <button type="submit" class="h-10 w-full rounded-md bg-[#C6F432] text-sm font-semibold text-[#080B0C] hover:brightness-95">Sign in</button>
        </form>

        <p class="mt-5 text-sm opacity-80">
            Don't have an account? <a href="{{ route('register') }}" class="font-semibold underline">Create one</a>
        </p>

        <div class="mt-8 rounded-lg border border-black/10 bg-[#F3F5F6] p-4 text-xs leading-relaxed">
            <p class="font-semibold">Demo accounts (password: <span class="font-mono">password123</span>)</p>
            <ul class="mt-2 space-y-1 font-mono">
                <li>Administrator — admin@cargotrace.io</li>
                <li>Logistics Operator — operator@cargotrace.io</li>
                <li>Customer — customer@cargotrace.io</li>
            </ul>
        </div>
    </div>
</body>
</html>
