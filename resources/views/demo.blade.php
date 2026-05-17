<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>لوحة تحكم البرمجة المتوازية</title>
    <style>
        body { font-family: Tahoma, Arial; background: #f8f9fa; padding: 2em; }
        button { margin: 0.5em; padding: 1em 2em; font-size: 1.1em; }
        pre { background: #222; color: #0f0; padding: 1em; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>لوحة تحكم البرمجة المتوازية</h1>
    <form method="POST" action="/demo/action">
        @csrf
        <button name="action" value="stress_safe">اختبار الضغط (آمن)</button>
        <button name="action" value="stress_unsafe">اختبار الضغط (غير آمن)</button>
        <button name="action" value="benchmark">تقرير الأداء (Benchmark)</button>
        <!-- أضف أزراراً أخرى حسب الحاجة -->
    </form>
    @if(isset($output))
        <h2>النتيجة:</h2>
        <pre>{{ $output }}</pre>
    @endif
</body>
</html>
