<x-dark-layout>

    <div class="max-w-4xl mx-auto">

        {{-- Page Header --}}
        <div class="mb-10">
            <h2 class="text-4xl font-black text-white tracking-tight">Account Settings</h2>
            <p class="text-slate-400 mt-2 text-sm font-medium">Manage your profile details and security preferences.</p>
        </div>

        {{-- Status Alerts --}}
        @if(session('status') === 'settings-updated')
        <div class="flash-message bg-emerald-500/10 text-emerald-400 px-6 py-4 rounded-2xl border border-emerald-500/20 font-bold text-sm flex items-center gap-3 mb-8 animate-pulse-once">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Account settings updated successfully.
        </div>
        @endif
        @if(session('error'))
        <div class="flash-message bg-rose-500/10 text-rose-400 px-6 py-4 rounded-2xl border border-rose-500/20 font-bold text-sm flex items-center gap-3 mb-8">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            {{ session('error') }}
        </div>
        @endif

        {{-- Main Content Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- LEFT: Profile Card --}}
            <div class="lg:col-span-1">
                <div class="glass-panel border border-slate-800 rounded-[2rem] p-8 shadow-2xl text-center relative overflow-hidden">
                    {{-- Decorative glow --}}
                    <div class="absolute top-[-30%] left-[-20%] w-[70%] h-[70%] rounded-full bg-indigo-600/15 blur-[80px] pointer-events-none"></div>
                    <div class="absolute bottom-[-20%] right-[-20%] w-[50%] h-[50%] rounded-full bg-blue-600/10 blur-[60px] pointer-events-none"></div>

                    <div class="relative z-10 space-y-5">
                        {{-- Avatar --}}
                        <div class="w-24 h-24 mx-auto rounded-[2rem] bg-gradient-to-br from-indigo-500 via-blue-500 to-purple-600 flex items-center justify-center text-white font-black text-4xl shadow-xl shadow-indigo-500/25 border-2 border-white/10">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>

                        {{-- Name & Role --}}
                        <div>
                            <h3 class="text-xl font-black text-white tracking-tight">{{ Auth::user()->name }}</h3>
                            <p class="text-sm text-slate-400 mt-1">{{ Auth::user()->email }}</p>
                        </div>

                        <span class="inline-flex px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.2em] rounded-full {{ Auth::user()->isAdmin() ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 border border-slate-700 text-slate-400' }}">
                            {{ Auth::user()->roleLabel() }}
                        </span>

                        @if(!Auth::user()->isAdmin() && Auth::user()->plan)
                        @php
                            $used = Auth::user()->interviews()->count();
                            $limit = max(1, Auth::user()->plan->interview_limit);
                            $percent = min(100, ($used / $limit) * 100);
                        @endphp
                        <div class="mt-4 p-4 rounded-xl bg-slate-900/50 border border-slate-800 text-left">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Plan: {{ Auth::user()->plan->name }}</span>
                                <span class="text-[10px] font-bold text-indigo-400">{{ $used }} / {{ Auth::user()->plan->interview_limit }} Used</span>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-1.5">
                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                        @endif

                        {{-- Account meta --}}
                        <div class="pt-4 border-t border-slate-800/50 space-y-3 text-left">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Member Since</span>
                                <span class="text-xs font-bold text-slate-300">{{ Auth::user()->created_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Status</span>
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Account Settings Form --}}
            <div class="lg:col-span-2">

                {{-- Account Settings --}}
                <div class="glass-panel border border-slate-800 rounded-[2rem] shadow-2xl overflow-hidden">
                    <div class="px-8 py-5 border-b border-slate-800/50 bg-slate-900/30 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-widest">Account Settings</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Update your profile information and change your password.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.update') }}" class="p-8">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label for="name" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Name</label>
                                <input id="name" type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                    placeholder="Your full name">
                                <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm font-bold" />
                            </div>

                            <div>
                                <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
                                    class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                    placeholder="your@email.com">
                                <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm font-bold" />
                            </div>
                        </div>

                        <div class="border-t border-slate-800/50 pt-8">
                            <h4 class="text-sm font-black text-white uppercase tracking-widest mb-4">Change Password</h4>
                            <p class="text-[11px] text-slate-500 mb-6">Leave blank if you don't want to change your password.</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="current_password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Current Password</label>
                                    <input id="current_password" type="password" name="current_password"
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="••••••••">
                                    <x-input-error :messages="$errors->get('current_password')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>

                                <div>
                                    <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">New Password</label>
                                    <input id="password" type="password" name="password"
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="Min. 8 characters">
                                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mt-8 pt-6 border-t border-slate-800/50">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold transition-all shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:shadow-[0_0_30px_rgba(79,70,229,0.4)] flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

</x-dark-layout>
