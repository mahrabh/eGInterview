<x-dark-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center w-full">
            <span>Edit User: <span class="text-indigo-400">{{ $user->name }}</span></span>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl font-bold text-xs text-slate-300 uppercase tracking-widest transition-all">
                ← Back to Users
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl">
        <div class="glass-panel border border-slate-800 rounded-[2rem] p-8 shadow-2xl">

            <div class="flex items-center gap-3 mb-8 pb-6 border-b border-slate-800">
                <div class="w-10 h-10 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 font-black text-lg">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-bold text-white">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $user->email }}</p>
                </div>
                <span class="ml-auto px-3 py-1 text-[10px] font-bold uppercase tracking-widest rounded-full bg-slate-800 border border-slate-700 text-slate-400">{{ $user->roleLabel() }}</span>
            </div>

            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Full Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required autofocus
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium">
                    <x-input-error :messages="$errors->get('name')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium">
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">
                        New Password <span class="text-[10px] text-slate-500 normal-case font-medium">(leave blank to keep current)</span>
                    </label>
                    <input id="password" type="password" name="password"
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium" placeholder="Min. 8 characters">
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Confirm New Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation"
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium" placeholder="Re-enter new password">
                </div>

                <div>
                    <label for="role" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Workspace Role</label>
                    <select id="role" name="role" required
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium">
                        <option value="analyst" {{ old('role', $user->role) === 'analyst' ? 'selected' : '' }}>Analyst</option>
                        <option value="recruiter" {{ old('role', $user->role) === 'recruiter' ? 'selected' : '' }}>Recruiter</option>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-2">Shown in the top-right corner when this user logs in.</p>
                    <x-input-error :messages="$errors->get('role')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                {{-- Plan Assignment --}}
                <div class="pt-2 border-t border-slate-800">
                    <label for="plan_id" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2 mt-4">Assigned Plan</label>
                    <select id="plan_id" name="plan_id"
                        class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all font-medium">
                        <option value="">— No Plan —</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ old('plan_id', $user->plan_id) == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }} — ৳{{ number_format($plan->price, 2) }}/mo
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('plan_id')" class="mt-2 text-rose-400 text-sm font-bold" />
                </div>

                <div class="flex items-center justify-end pt-4 gap-4">
                    <a href="{{ route('users.index') }}" class="text-sm font-bold text-slate-400 hover:text-white transition-colors">Cancel</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-[0_0_15px_rgba(79,70,229,0.3)]">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-dark-layout>
