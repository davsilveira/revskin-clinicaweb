<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .header {
            background: #059669;
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 32px 24px;
        }
        .content p {
            margin: 0 0 16px 0;
        }
        .section {
            background: #f8f9fa;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 20px;
            margin: 24px 0;
        }
        .section-title {
            font-weight: 600;
            color: #111827;
            margin: 0 0 12px 0;
            font-size: 16px;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #059669;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
        }
        .button:hover {
            background-color: #047857;
        }
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .footer {
            background: #f9fafb;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 4px 0;
        }
        .text-muted {
            color: #6b7280;
            font-size: 14px;
        }
        .text-small {
            font-size: 12px;
        }
        a {
            color: #059669;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>@yield('header', config('app.name'))</h1>
        </div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.</p>
            <p>Este é um email automático. Por favor, não responda diretamente.</p>
        </div>
    </div>
</body>
</html>
