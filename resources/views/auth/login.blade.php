<x-layouts.guest title="Log in · MiniTally">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center text-center">
            <img src="/icons/icon-192.png" alt="" class="size-14 rounded-2xl shadow">
            <h1 class="mt-3 text-xl font-semibold text-slate-900">{{ \App\Models\Setting::get('business_name') }}</h1>
            <p class="text-sm text-slate-500">Billing, payments and reminders</p>
        </div>
        <form method="POST" action="{{ route('login') }}" class="card card-pad space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>
            @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="btn btn-primary w-full">Log in</button>
        </form>
    </div>
</x-layouts.guest>
