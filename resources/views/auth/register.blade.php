<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create account · CargoTrace</title>
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

        <h1 class="mt-8 text-2xl font-bold tracking-tight">Create your account</h1>
        <p class="mt-1 text-sm opacity-70">New accounts start as Customer. An administrator can promote your role later.</p>

        @if ($errors->any())
            <div class="mt-5 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="text-xs font-semibold">Full name</label>
                <input id="name" name="name" type="text" required autofocus value="{{ old('name') }}"
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="Citra Hapsari" />
            </div>
            <div>
                <label for="email" class="text-xs font-semibold">Email address</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="you@cargotrace.io" />
            </div>
            <div>
                <label for="password" class="text-xs font-semibold">Password</label>
                <input id="password" name="password" type="password" required
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="Minimum 8 characters" />
            </div>
            <div>
                <label for="password_confirmation" class="text-xs font-semibold">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="mt-1.5 h-10 w-full rounded-md border border-black/15 bg-white px-3 text-sm outline-none focus:border-[#080B0C]" placeholder="Repeat your password" />
            </div>
            <button type="submit" class="h-10 w-full rounded-md bg-[#C6F432] text-sm font-semibold text-[#080B0C] hover:brightness-95">Create account</button>
        </form>

        <p class="mt-5 text-sm opacity-80">
            Already have an account? <a href="{{ route('login') }}" class="font-semibold underline">Sign in</a>
        </p>
    </div>
</body>
</html>
