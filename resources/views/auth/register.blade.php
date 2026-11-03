<x-layouts.guest>
    <x-slot:name>Register · CargoTrace</x-slot>

    <h1 class="text-xl font-semibold">Create an account</h1>
    <p class="mt-1 text-sm text-ink-2">Customer accounts track their own shipments. Staff accounts are provisioned by an administrator.</p>

    <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1.5 block text-xs font-medium text-ink-2">Name</label>
            <x-input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Your full name"/>
            @error('name') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-xs font-medium text-ink-2">Email</label>
            <x-input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="name@company.com"/>
            @error('email') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-xs font-medium text-ink-2">Password</label>
            <x-input id="password" name="password" type="password" required autocomplete="new-password" placeholder="At least 8 characters"/>
            @error('password') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-ink-2">Confirm password</label>
            <x-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat the password"/>
            @error('password_confirmation') <p class="mt-1.5 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <x-button class="w-full">Create account</x-button>
    </form>

    <p class="mt-6 text-center text-sm text-ink-2">
        Already have an account?
        <a href="{{ route('login') }}" class="text-accent transition-colors hover:text-info">Sign in</a>
    </p>
</x-layouts.guest>
