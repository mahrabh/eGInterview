<x-dark-layout>
    <div class="mb-8">
        <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Account Settings</h2>
        <p class="text-slate-400 mt-1.5 text-sm">Manage your profile details and security preferences.</p>
    </div>

    @if(session('status') === 'settings-updated')
        <div class="flash-message mb-6 bg-emerald-500/10 text-emerald-400 px-5 py-3.5 rounded-2xl border border-emerald-500/20 text-sm font-semibold flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Account settings updated successfully.
        </div>
    @endif
    @if(session('error'))
        <div class="flash-message mb-6 bg-rose-500/10 text-rose-400 px-5 py-3.5 rounded-2xl border border-rose-500/20 text-sm font-semibold flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        {{-- Profile summary --}}
        <aside class="xl:col-span-4">
            <div class="glass-panel border border-slate-800 rounded-2xl p-6 h-full">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white text-2xl font-black border border-white/10 shrink-0">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-white truncate">{{ Auth::user()->name }}</h3>
                        <p class="text-sm text-slate-400 truncate mt-0.5">{{ Auth::user()->email }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mb-6">
                    <span class="inline-flex px-3 py-1 text-xs font-semibold uppercase tracking-wide rounded-full {{ Auth::user()->isAdmin() ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : (Auth::user()->isAnalyst() ? 'bg-cyan-500/10 text-cyan-300 border border-cyan-500/20' : 'bg-violet-500/10 text-violet-300 border border-violet-500/20') }}">
                        {{ Auth::user()->roleLabel() }}
                    </span>
                </div>

                @if(!Auth::user()->isAdmin() && Auth::user()->plan)
                    @php
                        $used = Auth::user()->interviews()->count();
                        $limit = max(1, Auth::user()->plan->interview_limit);
                        $percent = min(100, ($used / $limit) * 100);
                    @endphp
                    <div class="mb-6 p-4 rounded-xl bg-slate-900/70 border border-slate-800">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-slate-300">Plan: {{ Auth::user()->plan->name }}</span>
                            <span class="text-sm font-semibold text-indigo-300">{{ $used }} / {{ Auth::user()->plan->interview_limit }}</span>
                        </div>
                        <div class="w-full bg-slate-800 rounded-full h-2">
                            <div class="bg-indigo-500 h-2 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Interview usage</p>
                    </div>
                @endif

                <div class="space-y-3 pt-4 border-t border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-400">Member since</span>
                        <span class="text-sm font-semibold text-slate-200">{{ Auth::user()->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm text-slate-400">Status</span>
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </aside>

        {{-- Settings form --}}
        <section class="xl:col-span-8">
            <div class="glass-panel border border-slate-800 rounded-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800/70 bg-slate-900/40 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Profile & Security</h3>
                        <p class="text-sm text-slate-400 mt-0.5">Update your name, email, or password.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.update') }}" class="p-6 md:p-8">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                        <div>
                            <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Full name</label>
                            <input id="name" type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                                class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                placeholder="Your full name">
                            <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm" />
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                                class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                placeholder="your@email.com">
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm" />
                        </div>
                    </div>

                    <div class="border-t border-slate-800 pt-7">
                        <h4 class="text-base font-bold text-white mb-1">Change password</h4>
                        <p class="text-sm text-slate-400 mb-5">Leave blank if you don’t want to change your password.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-slate-300 mb-2">Current password</label>
                                <input id="current_password" type="password" name="current_password"
                                    class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="••••••••">
                                <x-input-error :messages="$errors->get('current_password')" class="mt-2 text-rose-400 text-sm" />
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-slate-300 mb-2">New password</label>
                                <input id="password" type="password" name="password"
                                    class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                                    placeholder="Min. 8 characters">
                                <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm" />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-8 pt-6 border-t border-slate-800">
                        <button type="submit" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-[0_0_20px_rgba(79,70,229,0.25)]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</x-dark-layout>
