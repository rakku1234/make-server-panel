<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 Too Many Requests</title>
    <link rel="stylesheet" href="/css/error.css">
    <script src="/js/error.js"></script>
</head>
<body>
    <div class="error-page">
        <div class="error-container">
            <div class="error-code" id="error-code" data-target="429">0</div>
            <div class="error-message">
                <h1>{{ __('Too Many Requests') }}</h1>
                <p>{{ __('要求が多すぎます。') }}</p>
                <a href="#" class="btn-home" onclick="history.back(); return false;">{{ __('ホームへ戻る') }}</a>
            </div>
        </div>
    </div>
</body>
</html>
