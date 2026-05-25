<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zustimmung bestätigen</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #111827;
            background: #f3f4f6;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f4f6;
        }

        main {
            display: flex;
            min-height: 100vh;
            width: 100%;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
        }

        .card {
            width: min(100%, 576px);
            border-radius: 10px;
            background: #ffffff;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
        }

        h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1.25;
            font-weight: 700;
        }

        p {
            margin: 16px 0 0;
            color: #374151;
            font-size: 14px;
            line-height: 1.7;
        }

        .actions {
            display: grid;
            gap: 16px;
            margin-top: 24px;
        }

        form {
            margin: 0;
        }

        .approve-form {
            display: grid;
            gap: 20px;
        }

        label {
            display: flex;
            gap: 12px;
            color: #374151;
            font-size: 14px;
            line-height: 1.5;
        }

        input[type="checkbox"] {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            accent-color: #111827;
            flex: 0 0 auto;
        }

        button {
            display: inline-flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border-radius: 8px;
            border: 1px solid transparent;
            padding: 10px 16px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
        }

        .primary {
            background: #111827;
            color: #ffffff;
        }

        .primary:hover {
            background: #374151;
        }

        .danger {
            border-color: #fca5a5;
            background: #ffffff;
            color: #b91c1c;
        }

        .danger:hover {
            background: #fef2f2;
        }

        .error {
            margin-top: 0;
            color: #dc2626;
        }
    </style>
</head>
<body>
    <main>
        <section class="card">
            <h1>Registrierung prüfen</h1>

            <p>
                {{ $minor->name }} hat sich bei Airmius registriert und ist unter 16 Jahre alt.
                Bitte bestätigen Sie die Registrierung nur, wenn Sie erziehungsberechtigt sind.
            </p>

            <div class="actions">
                <form method="POST" action="{{ route('guardian-consent.approve', $token) }}" class="approve-form">
                    @csrf

                    <label>
                        <input
                            type="checkbox"
                            name="guardian_confirmation"
                            value="1"
                            required
                        >
                        <span>Ich bin erziehungsberechtigt und stimme der Registrierung zu.</span>
                    </label>

                    @error('guardian_confirmation')
                        <p class="error">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="primary">
                        Zustimmung erteilen
                    </button>
                </form>

                <form method="POST" action="{{ route('guardian-consent.reject', $token) }}">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="danger">
                        Registrierung ablehnen
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
