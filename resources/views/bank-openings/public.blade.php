<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bank Account Opening — {{ $application->applicant->application_reference }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="max-w-lg mx-auto px-4 py-10">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Bank Account Opening</p>
            <h1 class="text-2xl font-black mt-2 tracking-tight">Application {{ $application->applicant->application_reference }}</h1>
            <p class="text-sm text-slate-500 mt-2">Enter your name and mobile number to continue. A short live spoken interview happens next on this same link. Expires {{ $application->public_token_expiry?->diffForHumans() }}.</p>

            @if(session('success'))
                <div class="mt-4 bg-emerald-50 text-emerald-900 px-4 py-3 rounded-xl border border-emerald-200 text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mt-4 bg-rose-50 text-rose-900 px-4 py-3 rounded-xl border border-rose-200 text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 bg-rose-50 text-rose-900 px-4 py-3 rounded-xl border border-rose-200 text-sm">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('bank-opening.information', $token) }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Full name</label>
                    <input type="text" name="name" value="{{ old('name', $application->applicant->name) }}" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Mobile phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition-colors">
                    Continue to interview
                </button>
            </form>
        </div>
    </div>
</body>
</html>
