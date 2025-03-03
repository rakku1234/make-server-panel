<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 Unauthorized</title>
    <link rel="stylesheet" href="/css/error.css">
    <script src="/js/error.js"></script>
</head>
<body>
    <div class="error-page">
        <div class="error-container">
            <div class="error-code" id="error-code" data-target="401">0</div>
            <div class="error-message">
                <h1>{{ __('Unauthorized') }}</h1>
                <p>{{ __('認証が必要です。') }}</p>
                <a href="#" class="btn-home" onclick="history.back(); return false;">{{ __('ホームへ戻る') }}</a>
            </div>
        </div>
    </div>
</body>
</html>
