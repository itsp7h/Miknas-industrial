<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    {{-- Dev credentials hint --}}
    @if(app()->isLocal())
    <div style="margin-top:1.5rem; padding:0.75rem 1rem; background:#f0f9ff; border:1px solid #bae6fd; border-radius:0.5rem; font-size:0.8rem; color:#0369a1;">
        <div style="font-weight:600; margin-bottom:0.25rem;">Dev Credentials</div>
        <div>Email: <span style="font-family:monospace; font-weight:600;">admin@erp.com</span></div>
        <div>Password: <span style="font-family:monospace; font-weight:600;">password</span></div>
        <button type="button" onclick="document.getElementById('email').value='admin@erp.com'; document.getElementById('password').value='password';"
            style="margin-top:0.5rem; padding:0.25rem 0.75rem; background:#0ea5e9; color:#fff; border:none; border-radius:0.375rem; cursor:pointer; font-size:0.75rem;">
            Fill in
        </button>
    </div>
    @endif
</x-guest-layout>
