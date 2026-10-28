{{-- Inline approve/reject forms (data-reject-prompt): ask for the reason before rejecting. --}}
@once
    @push('scripts')
        <script>
            document.querySelectorAll('form[data-reject-prompt]').forEach(function (f) {
                f.addEventListener('submit', function (e) {
                    var btn = e.submitter, remarks = f.querySelector('input[name=remarks]');
                    if (!btn || btn.value !== 'reject') return;
                    var reason = prompt('Reason for rejection:');
                    if (!reason || !reason.trim()) { e.preventDefault(); return; }
                    remarks.value = reason.trim();
                });
            });
        </script>
    @endpush
@endonce
