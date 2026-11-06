<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create account · CargoTrace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        try {
            if (localStorage.getItem('cargotrace_theme_v1') === 'dark') document.documentElement.classList.add('dark');
        } catch (e) {}
    </script>
    <style>
        /* CargoTrace Tri-Color Architectural System (#080B0C · #FFFFFF · #C6F432) — same tokens as the app layout */
        :root {
            --font-sans: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, monospace;
            --bg-rail: #F3F5F7;
            --bg-base: #FFFFFF;
            --bg-surface: #F7F9FA;
            --bg-raised: #EAEEF0;
            --bg-overlay: #FFFFFF;
            --bg-map: #DCE2E6;
            --line: #CFD7DC;
            --line-strong: #94A2AB;
            --ink-1: #080B0C;
            --ink-2: #1A2125;
            --ink-3: #3D4850;
            --tint: #EDF1F3;
            --tint-strong: #DCE3E7;
            --brand: #C6F432;
            --brand-hover: #B5E420;
            --brand-ink: #080B0C;
            --brand-muted: rgba(198, 244, 50, 0.28);
            --ok: #047857;
            --warn: #B45309;
            --danger: #BE123C;
            --live: #B45309;
        }
        html.dark {
            --bg-rail: #050708;
            --bg-base: #080B0C;
            --bg-surface: #0D1214;
            --bg-raised: #131A1D;
            --bg-overlay: #172024;
            --bg-map: #0B0F11;
            --line: rgba(255, 255, 255, 0.09);
            --line-strong: rgba(255, 255, 255, 0.18);
            --ink-1: #FFFFFF;
            --ink-2: #B0BEC4;
            --ink-3: #7A8990;
            --tint: rgba(255, 255, 255, 0.055);
            --tint-strong: rgba(255, 255, 255, 0.1);
            --brand: #C6F432;
            --brand-hover: #D2FA4E;
            --brand-ink: #080B0C;
            --brand-muted: rgba(198, 244, 50, 0.14);
            --ok: #10B981;
            --warn: #F59E0B;
            --danger: #EF4444;
            --live: #F59E0B;
        }
        html {
            background-color: var(--bg-base);
            color: var(--ink-1);
            font-family: var(--font-sans);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        html.dark { color-scheme: dark; }
        html:not(.dark) { color-scheme: light; }
        body {
            min-height: 100vh;
            background-color: var(--bg-base);
            color: var(--ink-1);
        }
        ::selection { background-color: #C6F432; color: #080B0C; }
        .font-mono { font-family: var(--font-mono); }
    </style>
</head>
<body class="antialiased">
    <div class="grid min-h-screen grid-cols-1 bg-[var(--bg-base)] text-[var(--ink-1)] lg:grid-cols-[1fr_1.08fr]">
        {{-- Left column: registration form --}}
        <div class="flex flex-col justify-between px-6 py-8 sm:px-12 lg:px-16 xl:px-20">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg viewBox="0 0 28 32" fill="none" class="size-7 text-[var(--ink-1)]" aria-hidden="true">
                        <path d="M4 3L20 11V19L4 11V3Z" fill="currentColor" />
                        <path d="M4 15L20 23V31L4 23V15Z" fill="#C6F432" stroke="#080B0C" stroke-width="1" />
                        <path d="M20 11L25 8.5V16.5L20 19V11Z" fill="currentColor" fillOpacity="0.55" />
                    </svg>
                    <span class="text-[16px] font-semibold tracking-tight text-[var(--ink-1)]">CargoTrace</span>
                </div>
                <button type="button" onclick="toggleTheme()" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-[var(--line)] bg-[var(--bg-surface)] px-2.5 text-xs font-medium text-[var(--ink-2)] transition-colors hover:border-[var(--line-strong)] hover:text-[var(--ink-1)]">
                    <svg id="theme-icon-moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 text-[#080B0C]"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                    <svg id="theme-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="hidden size-3.5 text-[#C6F432]"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    <span id="theme-label">Dark mode</span>
                </button>
            </div>

            <div class="mx-auto my-auto w-full max-w-[400px] py-8">
                <h1 class="text-[28px] font-semibold tracking-tight text-[var(--ink-1)]">Get Started Now</h1>
                <p class="mt-1.5 text-[13px] text-[var(--ink-2)]">Create your customer account to track shipments in real time</p>

                <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="reg-name" class="mb-1.5 block text-xs font-medium text-[var(--ink-1)]">Name</label>
                        <input id="reg-name" name="name" type="text" required autofocus value="{{ old('name') }}" placeholder="Rafiqur Rahman"
                            class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3.5 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                        @error('name')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reg-email" class="mb-1.5 block text-xs font-medium text-[var(--ink-1)]">Email address</label>
                        <input id="reg-email" name="email" type="email" required value="{{ old('email') }}" placeholder="rafiqur51@company.com"
                            class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3.5 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                        @error('email')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reg-password" class="mb-1.5 block text-xs font-medium text-[var(--ink-1)]">Password</label>
                        <div class="relative">
                            <input id="reg-password" name="password" type="password" required placeholder="min 8 chars"
                                class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] pl-3.5 pr-10 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                            <button type="button" aria-label="Show password" onclick="togglePasswordPair()" class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3.5 text-[var(--ink-3)] hover:text-[var(--ink-1)]">
                                <svg class="pw-eye size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-eye-off hidden size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reg-confirm" class="mb-1.5 block text-xs font-medium text-[var(--ink-1)]">Confirm password</label>
                        <input id="reg-confirm" name="password_confirmation" type="password" required placeholder="Repeat password"
                            class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3.5 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 pt-0.5 text-xs text-[var(--ink-2)]">
                        <input type="checkbox" name="agree" value="1" checked class="size-4 rounded border-[var(--line)] bg-[var(--bg-surface)] accent-[#080B0C]" />
                        <span>I agree to the <span class="text-[var(--ink-1)] underline decoration-[var(--ink-3)] underline-offset-2">Terms &amp; Privacy</span></span>
                    </label>

                    <button type="submit" class="mt-2 h-11 w-full cursor-pointer rounded-xl bg-[var(--brand)] text-[13px] font-semibold text-[var(--brand-ink)] shadow-sm transition-colors hover:bg-[var(--brand-hover)] focus-visible:outline-none">Create Account</button>
                </form>

                <p class="mt-6 text-xs text-[var(--ink-2)]">
                    Have an account? <a href="{{ route('login') }}" class="font-semibold text-[var(--ink-1)] underline hover:opacity-80">Sign in</a>
                </p>
            </div>

            <p class="text-center text-xs text-[var(--ink-3)]">2026 CargoTrace, All right Reserved</p>
        </div>

        {{-- Right column: showcase panel --}}
        @include('partials.auth-showcase')
    </div>

    <script>
        function applyThemeLabel() {
            var isDark = document.documentElement.classList.contains('dark');
            var label = document.getElementById('theme-label');
            var moon = document.getElementById('theme-icon-moon');
            var sun = document.getElementById('theme-icon-sun');
            if (label) label.textContent = isDark ? 'White mode' : 'Dark mode';
            if (moon) moon.classList.toggle('hidden', isDark);
            if (sun) sun.classList.toggle('hidden', !isDark);
        }
        function toggleTheme() {
            var isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('cargotrace_theme_v1', isDark ? 'dark' : 'light'); } catch (e) {}
            applyThemeLabel();
            window.dispatchEvent(new CustomEvent('ct:theme', { detail: { dark: isDark } }));
        }
        // One shared show/hide state for both password fields, like the original.
        function togglePasswordPair() {
            var pw = document.getElementById('reg-password');
            var cf = document.getElementById('reg-confirm');
            if (!pw || !cf) return;
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            cf.type = show ? 'text' : 'password';
            document.querySelectorAll('.pw-eye').forEach(function (el) { el.classList.toggle('hidden', show); });
            document.querySelectorAll('.pw-eye-off').forEach(function (el) { el.classList.toggle('hidden', !show); });
            document.querySelectorAll('button[aria-label="Show password"], button[aria-label="Hide password"]').forEach(function (b) {
                b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        }
        document.addEventListener('DOMContentLoaded', applyThemeLabel);
    </script>
</body>
</html>
