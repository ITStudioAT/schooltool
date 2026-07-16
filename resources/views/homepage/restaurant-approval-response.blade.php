<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 35%, #fff 100%);
            color: #1f2937;
            font-family: Arial, sans-serif;
        }

        .card {
            width: min(560px, 100%);
            padding: 32px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(251, 146, 60, 0.24);
            box-shadow: 0 24px 60px rgba(194, 65, 12, 0.12);
        }

        .status {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(251, 146, 60, 0.14);
            color: #9a3412;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
            line-height: 1.15;
        }

        p {
            margin: 0;
            line-height: 1.6;
            color: #4b5563;
        }

        .subtitle {
            margin-bottom: 18px;
            font-weight: 700;
            color: #9a3412;
        }

        .action {
            display: inline-block;
            margin-top: 24px;
            border: 0;
            padding: 12px 18px;
            border-radius: 14px;
            background: #ea580c;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="status">{{ $status }}</div>
        <h1>{{ $title }}</h1>
        <p class="subtitle">{{ $subtitle }}</p>
        <p>{{ $text }}</p>

        @if (! empty($form_url))
            <form method="POST" action="{{ $form_url }}">
                @csrf
                <button class="action" type="submit">{{ $button_label }}</button>
            </form>
        @endif

        @if (! empty($back_url))
            <a class="action" href="{{ $back_url }}">Zur Restaurantseite</a>
        @endif
    </main>
</body>
</html>
