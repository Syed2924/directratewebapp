{{--
    Organizational scope picker.
    $tree: ScopeAssignment::treeFor($actor)   $selected: ['global' => bool, 'district' => [], 'office' => [], ...]
    Selecting a district covers every unit inside it, so its children are shown as included.
--}}
@php
    $sel = fn ($type) => array_map('intval', (array) ($selected[$type] ?? []));
    $groupLabels = ['office' => 'Offices', 'center' => 'Jawan Markaz Centers', 'hostel' => 'Hostels'];
@endphp
<div class="scope-tree" id="scopeTree">
    <input type="search" class="form-control form-control-sm mb-2" placeholder="Search districts, offices, centers, hostels…" id="scopeSearch" aria-label="Search scope">
    <ul>
        @if ($tree['global'])
            <li class="pb-2 mb-2 border-bottom">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="scope[global]" value="1" id="scope-global" @checked(! empty($selected['global']))>
                    <label class="form-check-label fw-semibold" for="scope-global">All districts &amp; units (system-wide)</label>
                </div>
            </li>
        @endif
        @forelse ($tree['districts'] as $d)
            <li class="scope-node" data-name="{{ strtolower($d['name']) }}">
                <div class="form-check">
                    @if ($d['selectable'])
                        <input class="form-check-input scope-district" type="checkbox" name="scope[district][]" value="{{ $d['id'] }}" id="sd-{{ $d['id'] }}" @checked(in_array($d['id'], $sel('district'), true))>
                    @else
                        <input class="form-check-input" type="checkbox" disabled title="Outside your scope">
                    @endif
                    <label class="form-check-label fw-medium" for="sd-{{ $d['id'] }}">{{ $d['name'] }} <span class="text-muted-2 small fw-normal">(whole district)</span></label>
                </div>
                <ul>
                    @foreach ($d['children'] as $type => $units)
                        @if (count($units))
                            <li class="group-label">{{ $groupLabels[$type] }}</li>
                            @foreach ($units as $unit)
                                <li class="scope-node" data-name="{{ strtolower($unit['name']) }}">
                                    <div class="form-check">
                                        <input class="form-check-input scope-child" type="checkbox" name="scope[{{ $type }}][]" value="{{ $unit['id'] }}" id="s{{ $type }}-{{ $unit['id'] }}" @checked(in_array($unit['id'], $sel($type), true))>
                                        <label class="form-check-label" for="s{{ $type }}-{{ $unit['id'] }}">{{ $unit['name'] }}</label>
                                    </div>
                                </li>
                            @endforeach
                        @endif
                    @endforeach
                </ul>
            </li>
        @empty
            <li class="text-muted-2 small py-2">No organizational units are available within your scope. Add them under Organization Units.</li>
        @endforelse
    </ul>
</div>

@push('scripts')
<script>
(function () {
    var tree = document.getElementById('scopeTree');
    if (!tree) return;
    var global = document.getElementById('scope-global');

    function sync() {
        var all = global && global.checked;
        tree.querySelectorAll('.scope-district').forEach(function (d) {
            d.disabled = all;
            var covered = all || d.checked;
            d.closest('li').querySelectorAll('.scope-child').forEach(function (c) {
                c.disabled = covered;
                if (covered) c.checked = false;
                c.closest('.form-check').title = covered ? 'Included through the selected district' : '';
            });
        });
    }
    tree.addEventListener('change', sync);
    sync();

    document.getElementById('scopeSearch').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        tree.querySelectorAll('li.scope-node').forEach(function (li) {
            li.style.display = !q || li.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
        });
    });
})();
</script>
@endpush
