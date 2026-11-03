<x-layouts.guest>
    <x-slot:name>Sign in · CargoTrace</x-slot>

    <h1 class="text-xl font-semibold">Sign in</h1>
    <p class="mt-1 text-sm text-ink-2">Access the operations room or your shipments.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-xs font-medium text-ink-2">Email</label>
            <x-input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@company.com"/>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-xs font-medium text-ink-2">Password</label>
            <x-input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Your password"/>
            @error('password') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <x-button class="w-full">Sign in</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-2">
        No account yet?
        <a href="{{ route('register') }}" class="text-accent transition-colors hover:text-info">Create one</a>
    </p>
</x-layouts.guest>
