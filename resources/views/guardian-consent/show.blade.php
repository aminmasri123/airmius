<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zustimmung bestaetigen</title>
    @vite('resources/js/app.js')
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-xl items-center px-6 py-12">
        <section class="w-full rounded-lg bg-white p-8 shadow">
            <h1 class="text-2xl font-semibold">Registrierung prüfen</h1>

            <p class="mt-4 text-sm leading-6 text-gray-700">
                {{ $minor->name }} hat sich bei Airmius registriert und ist unter 16 Jahre alt.
                Bitte bestaetigen Sie die Registrierung nur, wenn Sie erziehungsberechtigt sind.
            </p>

            <div class="mt-6 space-y-4">
                <form method="POST" action="{{ route('guardian-consent.approve', $token) }}" class="space-y-5">
                    @csrf

                    <label class="flex gap-3 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            name="guardian_confirmation"
                            value="1"
                            required
                            class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        >
                        <span>Ich bin erziehungsberechtigt und stimme der Registrierung zu.</span>
                    </label>

                    @error('guardian_confirmation')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
                    >
                        Zustimmung erteilen
                    </button>
                </form>

                <form method="POST" action="{{ route('guardian-consent.reject', $token) }}">
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-md border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                    >
                        Registrierung ablehnen
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
