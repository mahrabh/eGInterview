<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-slate-950 min-h-screen text-slate-100 font-sans flex items-center justify-center p-6">
    <div class="relative z-10 max-w-md w-full backdrop-blur-2xl bg-white/[0.03] border border-white/[0.08] rounded-[2rem] p-10 text-center space-y-8 shadow-[0_0_40px_rgba(0,0,0,0.5)]">
        <div class="w-24 h-24 bg-rose-500/10 rounded-[2rem] flex items-center justify-center mx-auto text-rose-400 shadow-[inset_0_0_20px_rgba(244,63,94,0.1)] border border-rose-500/20">
            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <div class="space-y-3">
            <h3 class="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-400">Link Expired</h3>
            <p class="text-sm text-slate-400 leading-relaxed">
                This interview link is no longer active. Please contact administrator to request a new session link.
            </p>
        </div>
    </div>
</body>
</html>
