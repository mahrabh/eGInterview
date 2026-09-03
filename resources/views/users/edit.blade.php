<x-dark-layout>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-white tracking-tight">Edit User</h2>
            <p class="text-slate-400 mt-1.5 text-sm">Update credentials, workspace role, and assigned plan for <span class="text-indigo-300 font-medium">{{ $user->name }}</span>.</p>
        </div>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl text-sm font-semibold text-slate-300 transition-all shrink-0">
            ← Back to Users
        </a>
    </div>

    <div class="glass-panel border border-slate-800 rounded-2xl overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800/70 bg-slate-900/40 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-4 min-w-0 flex-1">
                <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-200 text-lg font-black shrink-0">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-base font-bold text-white truncate">{{ $user->name }}</p>
                    <p class="text-sm text-slate-400 truncate mt-0.5">{{ $user->email }}</p>
                </div>
            </div>
            <span class="inline-flex px-3 py-1 text-xs font-semibold uppercase tracking-wide rounded-full border shrink-0
                {{ $user->isAnalyst() ? 'bg-cyan-500/10 text-cyan-300 border-cyan-500/20' : 'bg-violet-500/10 text-violet-300 border-violet-500/20' }}">
                {{ $user->roleLabel() }}
            </span>
        </div>

        <form method="POST" action="{{ route('users.update', $user) }}" class="p-6 md:p-8">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-2">Full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-2">New password</label>
                    <input id="password" type="password" name="password"
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                        placeholder="Min. 8 characters">
                    <p class="text-xs text-slate-500 mt-1.5">Leave blank to keep the current password.</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-2">Confirm new password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                        placeholder="Re-enter new password">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-slate-300 mb-2">Workspace role</label>
                    <select id="role" name="role" required
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                        <option value="analyst" {{ old('role', $user->role) === 'analyst' ? 'selected' : '' }}>Analyst — Loan Applicants only</option>
                        <option value="recruiter" {{ old('role', $user->role) === 'recruiter' ? 'selected' : '' }}>Recruiter — Recruitment only</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1.5">Shown in the top-right corner when this user logs in.</p>
                    <x-input-error :messages="$errors->get('role')" class="mt-2 text-rose-400 text-sm" />
                </div>

                <div>
                    <label for="plan_id" class="block text-sm font-medium text-slate-300 mb-2">Assigned plan</label>
                    <select id="plan_id" name="plan_id"
                        class="w-full bg-slate-950/60 border border-slate-700 rounded-xl px-4 py-3 text-base text-slate-100 focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all">
                        <option value="">— No Plan —</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('plan_id', $user->plan_id) == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} — ৳{{ number_format($plan->price, 2) }}/mo
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('plan_id')" class="mt-2 text-rose-400 text-sm" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 mt-8 pt-6 border-t border-slate-800">
                <a href="{{ route('users.index') }}" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors px-2 py-2">Cancel</a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition-all shadow-[0_0_20px_rgba(79,70,229,0.25)]">
                    Update User
                </button>
            </div>
        </form>
    </div>
</x-dark-layout>
