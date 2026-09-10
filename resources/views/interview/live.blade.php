<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live AI Interview | {{ $interview->candidate_name }}</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/live-interview.tsx'])
</head>
<body class="bg-slate-950 min-h-screen text-slate-100 font-sans">
    <div
        id="interview-root"
        data-interview='@json($interview)'
    ></div>
</body>
</html>