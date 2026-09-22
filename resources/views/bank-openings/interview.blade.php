<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Opening Interview — {{ $application->applicant->application_reference }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/bank-opening-interview.tsx'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div
        id="bank-opening-interview-root"
        data-token="{{ $token }}"
        data-interview='@json($interview)'
    ></div>
</body>
</html>
