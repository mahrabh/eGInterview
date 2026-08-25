<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Questions | Candidate Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen font-sans text-slate-800 p-10">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow-sm border border-slate-200 rounded-xl p-8">
            <div class="mb-8 border-b border-slate-100 pb-6">
                <h2 class="text-2xl font-black text-slate-900">
                    Review AI Questions for
                    <span class="text-indigo-600">{{ $interview->candidate_name }}</span>
                </h2>
                <p class="text-slate-500 mt-2">Role: {{ $interview->applied_role }}</p>
            </div>

            <form action="{{ route('interviews.approve', $interview->id) }}" method="POST" id="questions-form">
                @csrf

                <div class="space-y-6" id="questions-container">
                    @foreach(($interview->approved_questions ?? []) as $index => $q)
                        <div class="question-row flex items-start gap-4 p-5 bg-slate-50 rounded-lg border border-slate-200 group relative">
                            <span class="font-black text-indigo-600 mt-2 text-lg question-number">
                                {{ $index + 1 }}
                            </span>

                            <textarea
                                name="questions[]"
                                class="flex-grow border border-slate-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-3 text-slate-700"
                                rows="3"
                                required
                            >{{ $q }}</textarea>

                            <button
                                type="button"
                                onclick="removeQuestion(this)"
                                class="mt-2 text-slate-400 hover:text-rose-600 transition-colors p-2 rounded-md hover:bg-rose-50"
                                title="Remove this question"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 flex justify-end gap-4">
                    <a href="{{ route('dashboard') }}" class="px-6 py-3 border border-slate-300 text-slate-600 font-bold rounded-lg hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-sm">
                        Approve & Activate Link
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function removeQuestion(button) {
            const row = button.closest('.question-row');
            const container = document.getElementById('questions-container');

            if (container.querySelectorAll('.question-row').length <= 1) {
                alert("You must have at least one question for the interview.");
                return;
            }

            row.remove();

            const remainingRows = container.querySelectorAll('.question-row');
            remainingRows.forEach((row, index) => {
                row.querySelector('.question-number').textContent = index + 1;
            });
        }
    </script>
</body>
</html>