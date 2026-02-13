<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login-Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #fd802e;
            margin-top: 0;
        }
        .code-box {
            background-color: #f8efe7;
            border: 2px solid #fd802e;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 25px 0;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #233d4c;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hallo {{ $student->first_name }}!</h1>

        <p>Du hast einen Login-Code für den Unterrichtsbereich angefordert.</p>

        <p>Dein 6-stelliger Code lautet:</p>

        <div class="code-box">
            <div class="code">{{ $code }}</div>
        </div>

        <p>Dieser Code ist 15 Minuten gültig.</p>

        <p>Falls du diesen Code nicht angefordert hast, kannst du diese E-Mail ignorieren.</p>

        <div class="footer">
            <p>SchoolTool - {{ config('schooltool.copyright') }}</p>
        </div>
    </div>
</body>
</html>
