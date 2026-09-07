@props([
    'value' => null,
    'name' => 'expires_at',
    'id' => 'expires_at',
])

@php
    $iso = old($name, $value);
    if ($iso instanceof \Carbon\CarbonInterface) {
        $iso = $iso->format('Y-m-d');
    } elseif (is_string($iso) && $iso !== '') {
        try {
            $iso = \Carbon\Carbon::parse($iso)->format('Y-m-d');
        } catch (\Throwable) {
            $iso = '';
        }
    } else {
        $iso = '';
    }
@endphp

<div class="md:col-span-2" data-expiry-picker>
    <label class="block text-xs font-semibold text-slate-700 mb-1">Account expiry</label>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}" value="{{ $iso }}" data-expiry-input>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-expiry-open
            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-sm font-semibold text-slate-800 transition-colors">
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span data-expiry-label>{{ $iso ? \Carbon\Carbon::parse($iso)->format('M j, Y') : 'No expiry set' }}</span>
        </button>
        <button type="button" data-expiry-clear
            class="text-xs font-semibold text-slate-500 hover:text-rose-600 transition-colors px-2 py-1.5 {{ $iso ? '' : 'hidden' }}">
            Clear
        </button>
    </div>
    <p class="text-[11px] text-slate-500 mt-1.5">Optional. Access ends at the end of the selected day. Leave empty for no expiry.</p>
    <x-input-error :messages="$errors->get($name)" class="mt-1 text-rose-600 text-xs" />

    <div data-expiry-modal class="fixed inset-0 z-50 hidden" aria-hidden="true">
        <div class="absolute inset-0 bg-slate-900/40" data-expiry-backdrop></div>
        <div class="relative min-h-full flex items-center justify-center p-4">
            <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden" role="dialog" aria-modal="true" aria-label="Pick expiry date">
                <div class="px-4 py-3 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-indigo-50/50 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">Select expiry date</p>
                        <p class="text-[11px] text-slate-500">Access remains valid through that day</p>
                    </div>
                    <button type="button" data-expiry-close class="w-8 h-8 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors" aria-label="Close">
                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <button type="button" data-expiry-prev class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors" aria-label="Previous month">‹</button>
                        <p class="text-sm font-bold text-slate-900" data-expiry-month-label></p>
                        <button type="button" data-expiry-next class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors" aria-label="Next month">›</button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 mb-1">
                        @foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $dow)
                            <div class="text-center text-[10px] font-bold uppercase tracking-wide text-slate-400 py-1">{{ $dow }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 gap-1" data-expiry-grid></div>
                </div>

                <div class="px-4 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-2">
                    <button type="button" data-expiry-clear-modal class="text-xs font-semibold text-slate-500 hover:text-rose-600 transition-colors">
                        No expiry
                    </button>
                    <button type="button" data-expiry-apply class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-colors">
                        Apply date
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }
    function toIso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function parseIso(s) {
        if (!s) return null;
        var p = s.split('-');
        if (p.length !== 3) return null;
        return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    }
    function formatLabel(iso) {
        if (!iso) return 'No expiry set';
        var d = parseIso(iso);
        if (!d) return 'No expiry set';
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function monthLabel(y, m) {
        return new Date(y, m, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }

    function initPicker(root) {
        if (root.dataset.expiryReady === '1') return;
        root.dataset.expiryReady = '1';

        var input = root.querySelector('[data-expiry-input]');
        var label = root.querySelector('[data-expiry-label]');
        var clearBtn = root.querySelector('[data-expiry-clear]');
        var modal = root.querySelector('[data-expiry-modal]');
        var grid = root.querySelector('[data-expiry-grid]');
        var monthEl = root.querySelector('[data-expiry-month-label]');
        var draft = parseIso(input.value) || new Date();
        var viewY = draft.getFullYear();
        var viewM = draft.getMonth();
        var selectedIso = input.value || '';

        function syncDisplay() {
            label.textContent = formatLabel(input.value);
            clearBtn.classList.toggle('hidden', !input.value);
        }

        function open() {
            selectedIso = input.value || '';
            var base = parseIso(selectedIso) || new Date();
            viewY = base.getFullYear();
            viewM = base.getMonth();
            render();
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        }

        function close() {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }

        function render() {
            monthEl.textContent = monthLabel(viewY, viewM);
            grid.innerHTML = '';
            var first = new Date(viewY, viewM, 1);
            var startPad = first.getDay();
            var daysInMonth = new Date(viewY, viewM + 1, 0).getDate();
            var todayIso = toIso(new Date());

            for (var i = 0; i < startPad; i++) {
                var empty = document.createElement('div');
                empty.className = 'h-9';
                grid.appendChild(empty);
            }

            for (var day = 1; day <= daysInMonth; day++) {
                var iso = toIso(new Date(viewY, viewM, day));
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = String(day);
                btn.dataset.iso = iso;
                var classes = 'h-9 rounded-lg text-sm font-semibold transition-colors ';
                if (iso === selectedIso) {
                    classes += 'bg-indigo-600 text-white';
                } else if (iso === todayIso) {
                    classes += 'bg-indigo-50 text-indigo-700 border border-indigo-200';
                } else {
                    classes += 'text-slate-700 hover:bg-slate-100';
                }
                btn.className = classes;
                btn.addEventListener('click', function (e) {
                    selectedIso = e.currentTarget.dataset.iso;
                    render();
                });
                grid.appendChild(btn);
            }
        }

        root.querySelector('[data-expiry-open]').addEventListener('click', open);
        root.querySelector('[data-expiry-close]').addEventListener('click', close);
        root.querySelector('[data-expiry-backdrop]').addEventListener('click', close);
        root.querySelector('[data-expiry-prev]').addEventListener('click', function () {
            viewM -= 1;
            if (viewM < 0) { viewM = 11; viewY -= 1; }
            render();
        });
        root.querySelector('[data-expiry-next]').addEventListener('click', function () {
            viewM += 1;
            if (viewM > 11) { viewM = 0; viewY += 1; }
            render();
        });
        root.querySelector('[data-expiry-apply]').addEventListener('click', function () {
            if (!selectedIso) {
                selectedIso = toIso(new Date(viewY, viewM, 1));
            }
            input.value = selectedIso;
            syncDisplay();
            close();
        });
        function clearExpiry() {
            input.value = '';
            selectedIso = '';
            syncDisplay();
            close();
        }
        clearBtn.addEventListener('click', clearExpiry);
        root.querySelector('[data-expiry-clear-modal]').addEventListener('click', clearExpiry);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                close();
            }
        });

        syncDisplay();
    }

    document.querySelectorAll('[data-expiry-picker]').forEach(initPicker);
})();
</script>
@endpush
@endonce
