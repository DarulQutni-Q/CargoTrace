import React, { useState } from 'react';
import { Eye, EyeOff, Ship, Anchor, Globe, Compass, Wind, Sun, Moon } from 'lucide-react';
import { useStore } from '../domain/store';

const BrandMark: React.FC = () => (
  <div className="flex items-center gap-2.5">
    <svg
      viewBox="0 0 28 32"
      fill="none"
      className="size-7 text-ink"
      aria-hidden="true"
    >
      <path d="M4 3L20 11V19L4 11V3Z" fill="currentColor" />
      <path d="M4 15L20 23V31L4 23V15Z" fill="#C6F432" stroke="#080B0C" strokeWidth="1" />
      <path d="M20 11L25 8.5V16.5L20 19V11Z" fill="currentColor" fillOpacity="0.55" />
    </svg>
    <span className="text-[16px] font-semibold tracking-tight text-ink">
      CargoTrace
    </span>
  </div>
);

const AuthShowcasePanel: React.FC = () => {
  return (
    <div className="hidden lg:flex lg:p-4 xl:p-5">
      <div className="relative flex w-full flex-col justify-between overflow-hidden rounded-[28px] border border-white/10 bg-[#080B0C] px-10 py-10 text-white shadow-2xl xl:px-14 xl:py-12">
        {/* Decorative Cartographic Arcs & Volt-Lime Glow (#C6F432) */}
        <svg
          className="pointer-events-none absolute inset-0 h-full w-full opacity-25"
          viewBox="0 0 800 900"
          fill="none"
          aria-hidden="true"
        >
          <circle cx="680" cy="140" r="280" stroke="#C6F432" strokeOpacity="0.35" strokeDasharray="4 6" />
          <circle cx="680" cy="140" r="420" stroke="#FFFFFF" strokeOpacity="0.12" />
          <circle cx="120" cy="820" r="340" stroke="#C6F432" strokeOpacity="0.22" strokeDasharray="3 6" />
          <path
            d="M-40 520 C 180 360, 460 340, 840 470"
            stroke="#C6F432"
            strokeOpacity="0.45"
            strokeWidth="2"
          />
          <path
            d="M-40 620 C 220 450, 510 430, 840 580"
            stroke="#FFFFFF"
            strokeOpacity="0.16"
            strokeWidth="1.2"
            strokeDasharray="5 5"
          />
        </svg>
        <div
          className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-[#C6F432]/15 blur-3xl"
          aria-hidden="true"
        />

        {/* Top Headline */}
        <div className="relative z-10 max-w-md">
          <h2 className="text-[30px] font-semibold leading-[1.2] tracking-tight text-white xl:text-[34px]">
            The simplest way to manage your cargo corridor
          </h2>
          <p className="mt-2.5 text-[14px] leading-relaxed text-[#A3B1B6]">
            Enter your credentials to access real-time vessel telemetry, deterministic delay detection, and exception workflows.
          </p>
        </div>

        {/* Center Layered Product UI Showcase (Main Dashboard + Floating Telemetry Card) */}
        <div className="relative z-10 my-6 flex items-center justify-center">
          <div className="relative w-full max-w-[520px] pr-6 xl:pr-10">
            {/* Main Dashboard Card */}
            <div className="rounded-2xl border border-white/15 bg-[#0D1214]/95 p-5 text-white shadow-[0_30px_70px_-15px_rgba(0,0,0,0.85)] backdrop-blur-md">
              {/* Top Window Header */}
              <div className="flex items-center justify-between gap-2 pb-4">
                <div>
                  <p className="text-[13px] font-semibold text-white">Corridor Dashboard</p>
                  <div className="mt-1.5 flex items-center gap-2">
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/[0.04] px-2 py-1 text-[10px] text-[#A3B1B6]">
                      <span className="size-3.5 rounded-full bg-[#C6F432] text-center text-[8px] font-bold leading-[14px] text-[#080B0C]">
                        A
                      </span>
                      All Corridors ▾
                    </span>
                    <span className="rounded-md border border-white/10 bg-white/[0.04] px-2 py-1 font-mono text-[10px] text-[#6B797E]">
                      ‹ Oct 30 - Nov 20, 2026 ›
                    </span>
                  </div>
                </div>
                <div className="flex items-center gap-1.5">
                  <div className="flex -space-x-1.5">
                    <span className="flex size-5 items-center justify-center rounded-full bg-[#C6F432] text-[9px] font-semibold text-[#080B0C] ring-1 ring-[#080B0C]">
                      AW
                    </span>
                    <span className="flex size-5 items-center justify-center rounded-full bg-white text-[9px] font-semibold text-[#080B0C] ring-1 ring-[#080B0C]">
                      BS
                    </span>
                    <span className="flex size-5 items-center justify-center rounded-full bg-[#172024] text-[9px] font-semibold text-white ring-1 ring-white/20">
                      CH
                    </span>
                  </div>
                  <span className="rounded-md bg-[#C6F432] px-2 py-1 text-[10px] font-semibold text-[#080B0C]">
                    +14 Active
                  </span>
                </div>
              </div>

              {/* Two KPI Sparkline Cards */}
              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-xl border border-white/10 bg-[#080B0C] p-3.5">
                  <p className="text-[10px] text-[#6B797E]">On-Time Corridor / Day</p>
                  <div className="mt-1.5 flex items-end justify-between">
                    <div>
                      <p className="font-mono text-lg font-semibold text-white tabular-nums">
                        94.2%
                      </p>
                      <p className="mt-1 whitespace-nowrap text-[10px] text-[#C6F432]">
                        ↑ +4.8% last week
                      </p>
                    </div>
                    <svg className="h-9 w-20 overflow-visible" viewBox="0 0 80 36" fill="none">
                      <path
                        d="M2 28 C 16 28, 20 14, 34 18 C 46 22, 54 6, 78 8"
                        stroke="#C6F432"
                        strokeWidth="2"
                        strokeLinecap="round"
                      />
                      <circle cx="58" cy="10" r="3" fill="#C6F432" />
                    </svg>
                  </div>
                </div>

                <div className="rounded-xl border border-white/10 bg-[#080B0C] p-3.5">
                  <p className="text-[10px] text-[#6B797E]">Mean Transit Window</p>
                  <div className="mt-1.5 flex items-end justify-between">
                    <div>
                      <p className="font-mono text-lg font-semibold text-white tabular-nums">
                        18.5 d
                      </p>
                      <p className="mt-1 text-[10px] text-[#A3B1B6]">
                        LOW ≤ 6h · MED ≤ 24h
                      </p>
                    </div>
                    <svg className="h-9 w-16 overflow-visible" viewBox="0 0 64 36" fill="none">
                      <path
                        d="M2 24 C 18 16, 30 26, 44 14 C 52 8, 58 12, 62 6"
                        stroke="#FFFFFF"
                        strokeWidth="2"
                        strokeLinecap="round"
                      />
                    </svg>
                  </div>
                </div>
              </div>

              {/* Corridor Utilization Table */}
              <div className="mt-4">
                <p className="mb-2 text-[11px] font-medium text-[#A3B1B6]">
                  Corridor Utilization &amp; Active Vessels
                </p>
                <div className="space-y-2 border-t border-white/10 pt-2.5 text-[10px]">
                  {[
                    { code: 'S', name: 'SIN → RTM Sea Line', status: 'ON TIME', statusColor: 'text-[#C6F432]', load: '82%', bar: 'w-4/5' },
                    { code: 'B', name: 'BKK → HAM Air Cargo', status: 'MED DELAY', statusColor: 'text-amber-400', load: '64%', bar: 'w-3/5' },
                    { code: 'J', name: 'JKT → RTM Deepwater', status: 'ON TIME', statusColor: 'text-[#C6F432]', load: '91%', bar: 'w-11/12' },
                    { code: 'S', name: 'SUB → RTM Feeder', status: 'DELIVERED', statusColor: 'text-white', load: '100%', bar: 'w-full' },
                    { code: 'M', name: 'MNL → HAM Express', status: 'ON TIME', statusColor: 'text-[#C6F432]', load: '58%', bar: 'w-3/5' },
                  ].map((row, idx) => (
                    <div key={idx} className="grid grid-cols-12 items-center gap-2 py-0.5">
                      <div className="col-span-5 flex items-center gap-2 truncate">
                        <span className="flex size-4 shrink-0 items-center justify-center rounded-full bg-[#C6F432] font-mono text-[9px] font-bold text-[#080B0C]">
                          {row.code}
                        </span>
                        <span className="truncate text-white">{row.name}</span>
                      </div>
                      <div className={`col-span-3 font-mono text-[9px] ${row.statusColor}`}>
                        {row.status}
                      </div>
                      <div className="col-span-2 font-mono text-[#6B797E] tabular-nums">
                        {row.load}
                      </div>
                      <div className="col-span-2 flex items-center gap-1.5">
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-white/10">
                          <div className={`h-full rounded-full bg-[#C6F432] ${row.bar}`} />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Overlapping Right Floating Card (Live Telemetry Dispatch) */}
            <div className="absolute -right-3 top-14 w-[235px] rounded-2xl border border-[#C6F432]/40 bg-[#080B0C] p-4 text-white shadow-[0_25px_50px_-12px_rgba(0,0,0,0.9)] xl:-right-6 xl:w-[250px]">
              <div className="flex items-center justify-between">
                <span className="text-[11px] font-semibold text-white">Live Telemetry</span>
                <span className="flex size-4 items-center justify-center rounded-full border border-white/15 text-[10px] text-[#6B797E]">
                  ×
                </span>
              </div>

              <div className="mt-2.5">
                <p className="text-[9px] text-[#6B797E]">Active Corridor Shipment</p>
                <div className="mt-1 flex items-center gap-1.5">
                  <div className="flex flex-1 items-center justify-between rounded-md border border-white/10 bg-[#0D1214] px-2 py-1 text-[10px]">
                    <span className="font-mono font-medium text-white">CT0009MAC</span>
                    <span className="text-[9px] text-[#6B797E]">BKK → HAM</span>
                  </div>
                  <span className="rounded-md bg-[#C6F432] px-2 py-1 text-[9px] font-semibold text-[#080B0C]">
                    Track
                  </span>
                </div>
              </div>

              <div className="mt-3">
                <p className="text-[9px] text-[#6B797E]">Checkpoints</p>
                <div className="mt-1.5 space-y-2">
                  {[
                    { loc: 'Colombo Deepwater Hub', coord: '6.9497° N, 79.8428° E', tag: '05:30', dot: 'bg-[#C6F432]' },
                    { loc: 'Departed Bangkok Port', coord: '13.6900° N, 100.7501° E', tag: '19:30', dot: 'bg-white' },
                    { loc: 'Suvarnabhumi Cargo', coord: 'Origin Gate Cleared', tag: '06:30', dot: 'bg-[#6B797E]' },
                  ].map((cp, i) => (
                    <div key={i} className="flex items-center justify-between gap-2">
                      <div className="flex min-w-0 items-center gap-2">
                        <span className={`size-2 shrink-0 rounded-full ${cp.dot}`} />
                        <div className="min-w-0">
                          <p className="truncate text-[10px] font-medium text-white">{cp.loc}</p>
                          <p className="truncate font-mono text-[8px] text-[#6B797E]">{cp.coord}</p>
                        </div>
                      </div>
                      <span className="rounded bg-white/[0.06] px-1.5 py-0.5 font-mono text-[8px] text-[#A3B1B6]">
                        {cp.tag}
                      </span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="mt-3 border-t border-white/10 pt-2.5">
                <p className="text-[9px] text-[#6B797E]">Corridor Feed Endpoint</p>
                <div className="mt-1 flex items-center justify-between gap-1.5 rounded-md border border-white/10 bg-[#0D1214] px-2 py-1">
                  <span className="truncate font-mono text-[9px] text-[#A3B1B6]">
                    /api/shipments/9/tracking
                  </span>
                  <span className="rounded bg-[#C6F432] px-1.5 py-0.5 text-[8px] font-semibold text-[#080B0C]">
                    JSON
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Bottom Maritime & Logistics Partner Strip */}
        <div className="relative z-10 flex flex-wrap items-center justify-between gap-4 border-t border-white/15 pt-5 text-white/75">
          <div className="flex items-center gap-1.5 text-xs font-medium tracking-tight">
            <Ship className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
            <span>Maersk Line</span>
          </div>
          <div className="flex items-center gap-1.5 text-xs font-semibold tracking-tight">
            <Anchor className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
            <span>Port of Rotterdam</span>
          </div>
          <div className="flex items-center gap-1.5 text-xs font-medium tracking-tight">
            <Globe className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
            <span>PSA Singapore</span>
          </div>
          <div className="flex items-center gap-1.5 text-xs font-medium tracking-tight">
            <Wind className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
            <span>Open-Meteo</span>
          </div>
          <div className="flex items-center gap-1.5 text-xs font-medium tracking-tight">
            <Compass className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
            <span>Hapag-Lloyd</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export const LoginView: React.FC = () => {
  const { login, navigate, theme, toggleTheme } = useStore();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [agreed, setAgreed] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [infoNotice, setInfoNotice] = useState<string | null>(null);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setInfoNotice(null);
    const res = login(email, password);
    if (!res.ok) {
      setError(res.error ?? 'Invalid credentials');
    }
  };

  return (
    <div className="grid min-h-screen grid-cols-1 bg-base text-ink lg:grid-cols-[1fr_1.08fr]">
      {/* Left Column: Auth Form */}
      <div className="flex flex-col justify-between px-6 py-8 sm:px-12 lg:px-16 xl:px-20">
        <div className="flex items-center justify-between">
          <BrandMark />
          <button
            type="button"
            onClick={toggleTheme}
            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs font-medium text-ink-2 transition-colors hover:border-line-strong hover:text-ink cursor-pointer"
          >
            {theme === 'dark' ? (
              <>
                <Sun className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
                <span>White mode</span>
              </>
            ) : (
              <>
                <Moon className="size-3.5 text-[#080B0C]" strokeWidth={1.75} />
                <span>Dark mode</span>
              </>
            )}
          </button>
        </div>

        <div className="mx-auto my-auto w-full max-w-[400px] py-8">
          <h1 className="text-[28px] font-semibold tracking-tight text-ink">
            Get Started Now
          </h1>
          <p className="mt-1.5 text-[13px] text-ink-2">
            Enter your credentials to access your account
          </p>

          {/* Enterprise SSO Buttons (Google / Apple) */}
          <div className="mt-6 grid grid-cols-2 gap-3">
            <button
              type="button"
              onClick={() => {
                setError(null);
                setInfoNotice(
                  'Google Workspace SSO requires an enterprise domain token. Please sign in using your CargoTrace email and password below.',
                );
              }}
              className="flex h-10 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-3 text-xs font-medium text-ink transition-colors hover:border-line-strong hover:bg-raised cursor-pointer"
            >
              <svg className="size-4" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  fill="#EA4335"
                  d="M12 10.2v3.9h5.5c-.2 1.3-1.6 3.8-5.5 3.8-3.3 0-6-2.7-6-6s2.7-6 6-6c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.8 3.4 14.6 2.5 12 2.5 6.8 2.5 2.5 6.8 2.5 12s4.3 9.5 9.5 9.5c5.5 0 9.1-3.9 9.1-9.3 0-.6-.1-1.1-.2-1.6H12z"
                />
                <path
                  fill="#34A853"
                  d="M3.6 7.4l3.2 2.4C7.7 7.7 9.7 6 12 6c1.9 0 3.1.8 3.8 1.5l2.6-2.5C16.8 3.4 14.6 2.5 12 2.5 8.3 2.5 5.1 4.6 3.6 7.4z"
                />
                <path
                  fill="#FBBC05"
                  d="M12 21.5c2.5 0 4.7-.8 6.3-2.3l-2.9-2.4c-.8.6-1.9 1.1-3.4 1.1-3.8 0-5.2-2.5-5.5-3.8l-3.2 2.5c1.6 3 4.8 4.9 8.7 4.9z"
                />
                <path
                  fill="#4285F4"
                  d="M21.1 12.2c0-.6-.1-1.1-.2-1.6H12v3.9h5.5c-.3 1.4-1.1 2.5-2.1 3.2l2.9 2.4c1.7-1.6 2.8-4 2.8-7.9z"
                />
              </svg>
              <span>Log in with Google</span>
            </button>

            <button
              type="button"
              onClick={() => {
                setError(null);
                setInfoNotice(
                  'Apple ID Federation is disabled for this corridor environment. Please sign in with your CargoTrace account below.',
                );
              }}
              className="flex h-10 items-center justify-center gap-2 rounded-xl border border-line bg-surface px-3 text-xs font-medium text-ink transition-colors hover:border-line-strong hover:bg-raised cursor-pointer"
            >
              <svg className="size-4 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16.365 1.43c0 1.14-.493 2.27-1.177 3.08-.744.9-1.99 1.57-2.987 1.57-.12 0-.23-.02-.3-.03-.01-.06-.04-.22-.04-.39 0-1.15.572-2.27 1.206-2.98.804-.94 2.142-1.64 3.248-1.68.03.13.05.28.05.43zm4.565 15.71c-.03.07-.463 1.58-1.518 3.12-.945 1.34-1.94 2.71-3.43 2.71-1.517 0-1.9-.88-3.63-.88-1.698 0-2.302.91-3.67.91-1.377 0-2.332-1.26-3.428-2.8-1.287-1.82-2.323-4.63-2.323-7.28 0-4.28 2.797-6.55 5.552-6.55 1.448 0 2.675.95 3.6.95.865 0 2.222-1.01 3.902-1.01.613 0 2.886.06 4.374 2.19-.13.09-2.383 1.37-2.383 4.19 0 3.26 2.854 4.42 2.955 4.45z" />
              </svg>
              <span>Log in with Apple</span>
            </button>
          </div>

          {/* Divider */}
          <div className="my-6 flex items-center gap-3">
            <div className="h-px flex-1 bg-line" />
            <span className="text-xs text-ink-3">or</span>
            <div className="h-px flex-1 bg-line" />
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {error && (
              <div className="rounded-xl border border-rose-500/40 bg-rose-500/15 px-3.5 py-2.5 text-xs text-rose-600 dark:text-rose-200">
                {error}
              </div>
            )}

            {infoNotice && (
              <div className="rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 text-xs text-ink-2">
                {infoNotice}
              </div>
            )}

            <div>
              <label
                htmlFor="login-email"
                className="mb-1.5 block text-xs font-medium text-ink"
              >
                Email address
              </label>
              <input
                id="login-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="name@company.com"
                autoComplete="email"
                className="h-11 w-full rounded-xl border border-line bg-surface px-3.5 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
              />
            </div>

            <div>
              <div className="mb-1.5 flex items-center justify-between">
                <label
                  htmlFor="login-password"
                  className="block text-xs font-medium text-ink"
                >
                  Password
                </label>
                <button
                  type="button"
                  onClick={() => {
                    setError(null);
                    setInfoNotice(
                      'Password recovery is managed by your corridor administrator. Refer to README.md for seeded workspace accounts.',
                    );
                  }}
                  className="text-xs font-medium text-ink-2 underline hover:text-ink cursor-pointer"
                >
                  Forgot password?
                </button>
              </div>
              <div className="relative">
                <input
                  id="login-password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="min 8 chars"
                  autoComplete="current-password"
                  className="h-11 w-full rounded-xl border border-line bg-surface pl-3.5 pr-10 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
                />
                <button
                  type="button"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  onClick={() => setShowPassword((s) => !s)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-3 hover:text-ink cursor-pointer"
                >
                  {showPassword ? (
                    <EyeOff className="size-4" strokeWidth={1.5} />
                  ) : (
                    <Eye className="size-4" strokeWidth={1.5} />
                  )}
                </button>
              </div>
            </div>

            <label className="flex items-center gap-2.5 pt-0.5 text-xs text-ink-2 cursor-pointer">
              <input
                type="checkbox"
                checked={agreed}
                onChange={(e) => setAgreed(e.target.checked)}
                className="size-4 rounded border-line bg-surface accent-[#080B0C]"
              />
              <span>
                I agree to the{' '}
                <span className="underline decoration-ink-3 underline-offset-2 text-ink">
                  Terms &amp; Privacy
                </span>
              </span>
            </label>

            <button
              type="submit"
              className="mt-2 h-11 w-full rounded-xl bg-brand text-[13px] font-semibold text-brand-ink shadow-sm transition-colors hover:bg-brand-hover focus-visible:outline-none cursor-pointer"
            >
              Login
            </button>
          </form>

          <p className="mt-6 text-xs text-ink-2">
            Don&apos;t have an account?{' '}
            <button
              type="button"
              onClick={() => navigate('/register')}
              className="font-semibold text-ink underline hover:opacity-80 cursor-pointer"
            >
              Sign up
            </button>
          </p>
        </div>

        <p className="text-center text-xs text-ink-3">
          2026 CargoTrace, All right Reserved
        </p>
      </div>

      {/* Right Column: Showcase Panel */}
      <AuthShowcasePanel />
    </div>
  );
};

export const RegisterView: React.FC = () => {
  const { register, navigate, theme, toggleTheme } = useStore();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [agreed, setAgreed] = useState(true);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const res = register(name, email, password, confirm);
    if (!res.ok) setErrors(res.errors ?? {});
  };

  return (
    <div className="grid min-h-screen grid-cols-1 bg-base text-ink lg:grid-cols-[1fr_1.08fr]">
      {/* Left Column: Registration Form */}
      <div className="flex flex-col justify-between px-6 py-8 sm:px-12 lg:px-16 xl:px-20">
        <div className="flex items-center justify-between">
          <BrandMark />
          <button
            type="button"
            onClick={toggleTheme}
            className="inline-flex h-8 items-center gap-1.5 rounded-md border border-line bg-surface px-2.5 text-xs font-medium text-ink-2 transition-colors hover:border-line-strong hover:text-ink cursor-pointer"
          >
            {theme === 'dark' ? (
              <>
                <Sun className="size-3.5 text-[#C6F432]" strokeWidth={1.75} />
                <span>White mode</span>
              </>
            ) : (
              <>
                <Moon className="size-3.5 text-[#080B0C]" strokeWidth={1.75} />
                <span>Dark mode</span>
              </>
            )}
          </button>
        </div>

        <div className="mx-auto my-auto w-full max-w-[400px] py-8">
          <h1 className="text-[28px] font-semibold tracking-tight text-ink">
            Get Started Now
          </h1>
          <p className="mt-1.5 text-[13px] text-ink-2">
            Create your customer account to track shipments in real time
          </p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <div>
              <label
                htmlFor="reg-name"
                className="mb-1.5 block text-xs font-medium text-ink"
              >
                Name
              </label>
              <input
                id="reg-name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Rafiqur Rahman"
                className="h-11 w-full rounded-xl border border-line bg-surface px-3.5 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
              />
              {errors.name && <p className="mt-1 text-xs text-rose-500">{errors.name}</p>}
            </div>

            <div>
              <label
                htmlFor="reg-email"
                className="mb-1.5 block text-xs font-medium text-ink"
              >
                Email address
              </label>
              <input
                id="reg-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="rafiqur51@company.com"
                className="h-11 w-full rounded-xl border border-line bg-surface px-3.5 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
              />
              {errors.email && <p className="mt-1 text-xs text-rose-500">{errors.email}</p>}
            </div>

            <div>
              <label
                htmlFor="reg-password"
                className="mb-1.5 block text-xs font-medium text-ink"
              >
                Password
              </label>
              <div className="relative">
                <input
                  id="reg-password"
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="min 8 chars"
                  className="h-11 w-full rounded-xl border border-line bg-surface pl-3.5 pr-10 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
                />
                <button
                  type="button"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  onClick={() => setShowPassword((s) => !s)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3.5 text-ink-3 hover:text-ink cursor-pointer"
                >
                  {showPassword ? (
                    <EyeOff className="size-4" strokeWidth={1.5} />
                  ) : (
                    <Eye className="size-4" strokeWidth={1.5} />
                  )}
                </button>
              </div>
              {errors.password && <p className="mt-1 text-xs text-rose-500">{errors.password}</p>}
            </div>

            <div>
              <label
                htmlFor="reg-confirm"
                className="mb-1.5 block text-xs font-medium text-ink"
              >
                Confirm password
              </label>
              <input
                id="reg-confirm"
                type={showPassword ? 'text' : 'password'}
                value={confirm}
                onChange={(e) => setConfirm(e.target.value)}
                placeholder="Repeat password"
                className="h-11 w-full rounded-xl border border-line bg-surface px-3.5 text-[13px] text-ink placeholder:text-ink-3 transition-colors focus:border-line-strong focus:outline-none"
              />
              {errors.password_confirmation && (
                <p className="mt-1 text-xs text-rose-500">{errors.password_confirmation}</p>
              )}
            </div>

            <label className="flex items-center gap-2.5 pt-0.5 text-xs text-ink-2 cursor-pointer">
              <input
                type="checkbox"
                checked={agreed}
                onChange={(e) => setAgreed(e.target.checked)}
                className="size-4 rounded border-line bg-surface accent-[#080B0C]"
              />
              <span>
                I agree to the{' '}
                <span className="underline decoration-ink-3 underline-offset-2 text-ink">
                  Terms &amp; Privacy
                </span>
              </span>
            </label>

            <button
              type="submit"
              className="mt-2 h-11 w-full rounded-xl bg-brand text-[13px] font-semibold text-brand-ink shadow-sm transition-colors hover:bg-brand-hover focus-visible:outline-none cursor-pointer"
            >
              Create Account
            </button>
          </form>

          <p className="mt-6 text-xs text-ink-2">
            Have an account?{' '}
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="font-semibold text-ink underline hover:opacity-80 cursor-pointer"
            >
              Sign in
            </button>
          </p>
        </div>

        <p className="text-center text-xs text-ink-3">
          2026 CargoTrace, All right Reserved
        </p>
      </div>

      {/* Right Column: Showcase Panel */}
      <AuthShowcasePanel />
    </div>
  );
};
