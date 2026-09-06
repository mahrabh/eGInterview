<x-dark-layout>
    <div class="max-w-4xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-indigo-600 mb-1.5">Question Review</p>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    Review AI Questions for
                    <span class="text-indigo-600">{{ $interview->candidate_name }}</span>
                </h2>
                <p class="text-slate-500 mt-1.5 text-sm">
                    Role: <span class="font-semibold text-slate-700">{{ $interview->applied_role }}</span>
                    · Edit questions below, then approve to activate the interview link.
                </p>
            </div>
            <a href="{{ route('recruitment.index', array_filter(['page' => request('page')])) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 transition-all shrink-0">
                ← Back to Recruitment
            </a>
        </div>

        <form action="{{ route('interviews.approve', array_filter(['interview' => $interview->id, 'page' => request('page')])) }}" method="POST" id="questions-form">
            @csrf

            <div class="glass-panel rounded-2xl overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Interview questions</h3>
                        <p class="text-xs text-slate-500 mt-0.5">You can edit wording or remove questions before activating the link.</p>
                    </div>
                    <span id="question-count" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ count($interview->approved_questions ?? []) }} questions
                    </span>
                </div>

                <div class="p-4 sm:p-6 space-y-3" id="questions-container">
                    @forelse(($interview->approved_questions ?? []) as $index => $q)
                        <div class="question-row flex items-start gap-3 sm:gap-4 p-4 rounded-xl border border-slate-200 bg-white hover:border-slate-300 transition-colors group">
                            <span class="question-number shrink-0 w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700 font-black text-sm flex items-center justify-center mt-0.5">
                                {{ $index + 1 }}
                            </span>

                            <textarea
                                name="questions[]"
                                class="flex-1 min-w-0 resize-y min-h-[88px] border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 p-3.5 text-sm sm:text-base text-slate-800 leading-relaxed outline-none transition-colors"
                                rows="3"
                                required
                            >{{ $q }}</textarea>

                            <button
                                type="button"
                                onclick="removeQuestion(this)"
                                class="shrink-0 mt-0.5 w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 rounded-xl transition-all"
                                title="Remove this question"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="py-12 text-center text-sm text-slate-500">
                            No questions available to review.
                        </div>
                    @endforelse
                </div>

                <div class="px-5 sm:px-6 py-4 border-t border-slate-200 bg-slate-50 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
                    <a href="{{ route('recruitment.index', array_filter(['page' => request('page')])) }}"
                       class="inline-flex justify-center px-5 py-2.5 border border-slate-200 bg-white text-slate-700 font-semibold text-sm rounded-xl hover:bg-slate-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm py-2.5 px-6 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Approve & Activate Link
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function updateQuestionCount() {
            const count = document.querySelectorAll('#questions-container .question-row').length;
            const badge = document.getElementById('question-count');
            if (badge) {
                badge.textContent = count + ' question' + (count === 1 ? '' : 's');
            }
        }

        function removeQuestion(button) {
            const row = button.closest('.question-row');
            const container = document.getElementById('questions-container');

            if (container.querySelectorAll('.question-row').length <= 1) {
                alert('You must have at least one question for the interview.');
                return;
            }

            row.remove();

            container.querySelectorAll('.question-row').forEach((item, index) => {
                item.querySelector('.question-number').textContent = index + 1;
            });

            updateQuestionCount();
        }
    </script>
</x-dark-layout>
