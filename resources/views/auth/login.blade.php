<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign in · CargoTrace</title>
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
        {{-- Left column: auth form --}}
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
                <p class="mt-1.5 text-[13px] text-[var(--ink-2)]">Enter your credentials to access your account</p>

                {{-- Enterprise SSO buttons (Google / Apple) — same as the original:
                     SSO is not wired up; clicking shows the original info notice. --}}
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button type="button" onclick="showNotice('Google Workspace SSO requires an enterprise domain token. Please sign in using your CargoTrace email and password below.')" class="flex h-10 cursor-pointer items-center justify-center gap-2 rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3 text-xs font-medium text-[var(--ink-1)] transition-colors hover:border-[var(--line-strong)] hover:bg-[var(--bg-raised)]">
                        <svg class="size-4" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.3-1.6 3.8-5.5 3.8-3.3 0-6-2.7-6-6s2.7-6 6-6c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.8 3.4 14.6 2.5 12 2.5 6.8 2.5 2.5 6.8 2.5 12s4.3 9.5 9.5 9.5c5.5 0 9.1-3.9 9.1-9.3 0-.6-.1-1.1-.2-1.6H12z" />
                            <path fill="#34A853" d="M3.6 7.4l3.2 2.4C7.7 7.7 9.7 6 12 6c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.8 3.4 14.6 2.5 12 2.5 8.3 2.5 5.1 4.6 3.6 7.4z" />
                            <path fill="#FBBC05" d="M12 21.5c2.5 0 4.7-.8 6.3-2.3l-2.9-2.4c-.8.6-1.9 1.1-3.4 1.1-3.8 0-5.2-2.5-5.5-3.8l-3.2 2.5c1.6 3 4.8 4.9 8.7 4.9z" />
                            <path fill="#4285F4" d="M21.1 12.2c0-.6-.1-1.1-.2-1.6H12v3.9h5.5c-.3 1.4-1.1 2.5-2.1 3.2l2.9 2.4c1.7-1.6 2.8-4 2.8-7.9z" />
                        </svg>
                        <span>Log in with Google</span>
                    </button>
                    <button type="button" onclick="showNotice('Apple ID Federation is disabled for this corridor environment. Please sign in with your CargoTrace account below.')" class="flex h-10 cursor-pointer items-center justify-center gap-2 rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3 text-xs font-medium text-[var(--ink-1)] transition-colors hover:border-[var(--line-strong)] hover:bg-[var(--bg-raised)]">
                        <svg class="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.287-1.82-2.323-4.63-2.323-7.28 0-4.28 2.797-6.55 5.552-6.55 1.448 0 2.675.95 3.6.95.865 0 2.222-1.01 3.902-1.01.613 0 2.886.06 4.374 2.19-.13.09-2.383 1.37-2.383 4.19 0 3.26 2.854 4.42 2.955 4.45z" />
                        </svg>
                        <span>Log in with Apple</span>
                    </button>
                </div>

                {{-- Divider --}}
                <div class="my-6 flex items-center gap-3">
                    <div class="h-px flex-1 bg-[var(--line)]"></div>
                    <span class="text-xs text-[var(--ink-3)]">or</span>
                    <div class="h-px flex-1 bg-[var(--line)]"></div>
                </div>

                <form method="POST" action="{{ route('login.attempt') }}" id="login-form" class="space-y-4">
                    @csrf
                    @if ($errors->any())
                        <div id="error-box" class="rounded-xl border border-rose-500/40 bg-rose-500/15 px-3.5 py-2.5 text-xs text-rose-600 dark:text-rose-200">{{ $errors->first() }}</div>
                    @endif
                    <div id="info-notice" class="hidden rounded-xl border border-[var(--line-strong)] bg-[var(--bg-surface)] px-3.5 py-2.5 text-xs text-[var(--ink-2)]"></div>

                    <div>
                        <label for="login-email" class="mb-1.5 block text-xs font-medium text-[var(--ink-1)]">Email address</label>
                        <input id="login-email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}" placeholder="name@company.com"
                            class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] px-3.5 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="login-password" class="block text-xs font-medium text-[var(--ink-1)]">Password</label>
                            <button type="button" onclick="showNotice('Password recovery is managed by your corridor administrator. Refer to README.md for seeded workspace accounts.')" class="cursor-pointer text-xs font-medium text-[var(--ink-2)] underline hover:text-[var(--ink-1)]">Forgot password?</button>
                        </div>
                        <div class="relative">
                            <input id="login-password" name="password" type="password" required autocomplete="current-password" placeholder="min 8 chars"
                                class="h-11 w-full rounded-xl border border-[var(--line)] bg-[var(--bg-surface)] pl-3.5 pr-10 text-[13px] text-[var(--ink-1)] placeholder:text-[var(--ink-3)] transition-colors focus:border-[var(--line-strong)] focus:outline-none" />
                            <button type="button" aria-label="Show password" onclick="togglePassword('login-password', this)" class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3.5 text-[var(--ink-3)] hover:text-[var(--ink-1)]">
                                <svg class="pw-eye size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="pw-eye-off hidden size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                            </button>
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-center gap-2.5 pt-0.5 text-xs text-[var(--ink-2)]">
                        <input type="checkbox" id="agree-checkbox" name="agree" value="1" @checked(old('agree', true)) class="size-4 rounded border-[var(--line)] bg-[var(--bg-surface)] accent-[#080B0C]" />
                        <span>I agree to the <span class="text-[var(--ink-1)] underline decoration-[var(--ink-3)] underline-offset-2">Terms &amp; Privacy</span></span>
                    </label>
                    @error('agree')
                        <p id="agree-error" class="text-xs text-rose-500">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="mt-2 h-11 w-full cursor-pointer rounded-xl bg-[var(--brand)] text-[13px] font-semibold text-[var(--brand-ink)] shadow-sm transition-colors hover:bg-[var(--brand-hover)] focus-visible:outline-none">Login</button>
                </form>

                <p class="mt-6 text-xs text-[var(--ink-2)]">
                    Don&apos;t have an account? <a href="{{ route('register') }}" class="font-semibold text-[var(--ink-1)] underline hover:opacity-80">Sign up</a>
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
        function showNotice(text) {
            var box = document.getElementById('info-notice');
            var err = document.getElementById('error-box');
            if (err) err.classList.add('hidden');
            if (!box) return;
            box.textContent = text;
            box.classList.remove('hidden');
        }
        function togglePassword(inputId, btn) {
            var input = document.getElementById(inputId);
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            var eye = btn.querySelector('.pw-eye');
            var eyeOff = btn.querySelector('.pw-eye-off');
            if (eye) eye.classList.toggle('hidden', show);
            if (eyeOff) eyeOff.classList.toggle('hidden', !show);
        }
        document.addEventListener('DOMContentLoaded', applyThemeLabel);
        (function () {
            var form = document.getElementById('login-form');
            var box = document.getElementById('agree-checkbox');
            if (!form || !box) return;
            function showAgreeError() {
                var err = document.getElementById('agree-error');
                if (!err) {
                    err = document.createElement('p');
                    err.id = 'agree-error';
                    err.className = 'text-xs text-rose-500';
                    err.textContent = 'You must agree to the Terms & Privacy to continue.';
                    box.closest('label').insertAdjacentElement('afterend', err);
                }
                err.classList.remove('hidden');
            }
            form.addEventListener('submit', function (e) {
                if (!box.checked) {
                    e.preventDefault();
                    showAgreeError();
                    box.focus();
                }
            });
            box.addEventListener('change', function () {
                var err = document.getElementById('agree-error');
                if (box.checked && err) err.classList.add('hidden');
            });
        })();
    </script>
</body>
</html>
