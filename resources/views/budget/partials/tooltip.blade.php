{{-- Hover tooltip for any element with data-tip (charts). Include once per page. --}}
@once
    @push('scripts')
        <div class="chart-tip" id="chartTip" role="tooltip"></div>
        <script>
            (function () {
                var tip = document.getElementById('chartTip');
                document.addEventListener('mousemove', function (e) {
                    var t = e.target.closest ? e.target.closest('[data-tip]') : null;
                    if (!t) { tip.style.display = 'none'; return; }
                    tip.textContent = t.getAttribute('data-tip');
                    tip.style.display = 'block';
                    var x = e.clientX + 14, y = e.clientY + 14;
                    if (x + tip.offsetWidth > window.innerWidth - 8) x = e.clientX - tip.offsetWidth - 14;
                    if (y + tip.offsetHeight > window.innerHeight - 8) y = e.clientY - tip.offsetHeight - 14;
                    tip.style.left = x + 'px'; tip.style.top = y + 'px';
                });
            })();
        </script>
    @endpush
@endonce
