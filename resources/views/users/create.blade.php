<x-dark-layout>
    <div class="max-w-4xl mx-auto">

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
            <div>
                <h2 class="text-4xl font-black text-white tracking-tight">Create User</h2>
                <p class="text-slate-400 mt-2 text-sm font-medium">Add a new workspace user and assign Analyst or Recruiter role.</p>
            </div>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl font-bold text-xs text-slate-300 uppercase tracking-widest transition-all">
                ← Back to Users
            </a>
        </div>

        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                {{-- LEFT: Preview Card --}}
                <div class="lg:col-span-1">
                    <div class="glass-panel border border-slate-800 rounded-[2rem] p-8 shadow-2xl text-center relative overflow-hidden">
                        <div class="absolute top-[-30%] left-[-20%] w-[70%] h-[70%] rounded-full bg-indigo-600/15 blur-[80px] pointer-events-none"></div>
                        <div class="absolute bottom-[-20%] right-[-20%] w-[50%] h-[50%] rounded-full bg-blue-600/10 blur-[60px] pointer-events-none"></div>

                        <div class="relative z-10 space-y-5">
                            <div class="w-24 h-24 mx-auto rounded-[2rem] bg-gradient-to-br from-indigo-500 via-blue-500 to-purple-600 flex items-center justify-center text-white font-black text-4xl shadow-xl shadow-indigo-500/25 border-2 border-white/10">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>

                            <div>
                                <h3 class="text-xl font-black text-white tracking-tight">New Workspace User</h3>
                                <p class="text-sm text-slate-400 mt-1">Assign Analyst or Recruiter access</p>
                            </div>

                            <span class="inline-flex px-4 py-1.5 text-[10px] font-bold uppercase tracking-[0.2em] rounded-full bg-slate-800 border border-slate-700 text-slate-400">
                                Workspace User
                            </span>

                            <div class="pt-4 border-t border-slate-800/50 text-left space-y-3">
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Access to Dashboard & Interviews
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Import candidates & generate questions
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    View evaluations & transcripts
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-400">
                                    <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                    No access to Users & Plans
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Form --}}
                <div class="lg:col-span-2 space-y-8">

                    {{-- Credentials --}}
                    <div class="glass-panel border border-slate-800 rounded-[2rem] shadow-2xl overflow-hidden">
                        <div class="px-8 py-5 border-b border-slate-800/50 bg-slate-900/30 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-white uppercase tracking-widest">Account Credentials</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">Set the recruiter's login details.</p>
                            </div>
                        </div>

                        <div class="p-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Full Name</label>
                                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="John Smith">
                                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>

                                <div>
                                    <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="recruiter@company.com">
                                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>

                                <div>
                                    <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Password</label>
                                    <input id="password" type="password" name="password" required
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="Min. 8 characters">
                                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>

                                <div>
                                    <label for="password_confirmation" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Confirm Password</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation" required
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium"
                                        placeholder="Re-enter password">
                                </div>

                                <div class="md:col-span-2">
                                    <label for="role" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Workspace Role</label>
                                    <select id="role" name="role" required
                                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium">
                                        <option value="analyst" {{ old('role', 'analyst') === 'analyst' ? 'selected' : '' }}>Analyst</option>
                                        <option value="recruiter" {{ old('role') === 'recruiter' ? 'selected' : '' }}>Recruiter</option>
                                    </select>
                                    <p class="text-[11px] text-slate-500 mt-2">This label is shown in the top-right corner when the user logs in.</p>
                                    <x-input-error :messages="$errors->get('role')" class="mt-2 text-rose-400 text-sm font-bold" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Plan Assignment --}}
                    <div class="glass-panel border border-slate-800 rounded-[2rem] shadow-2xl overflow-hidden">
                        <div class="px-8 py-5 border-b border-slate-800/50 bg-slate-900/30 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-white uppercase tracking-widest">Plan Assignment</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">Optional — can be assigned later from the users list.</p>
                            </div>
                        </div>

                        <div class="p-8">
                            <select id="plan_id" name="plan_id"
                                class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3.5 text-slate-200 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium">
                                <option value="">— No Plan Assigned —</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->name }} — ৳{{ number_format($plan->price, 2) }}/mo
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('plan_id')" class="mt-2 text-rose-400 text-sm font-bold" />

                            <div class="flex items-center justify-end mt-6 pt-6 border-t border-slate-800/50 gap-4">
                                <a href="{{ route('users.index') }}" class="text-sm font-bold text-slate-400 hover:text-white transition-colors">Cancel</a>
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-8 py-3 rounded-xl font-bold transition-all shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:shadow-[0_0_30px_rgba(79,70,229,0.4)] flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                    Create User
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-dark-layout>
