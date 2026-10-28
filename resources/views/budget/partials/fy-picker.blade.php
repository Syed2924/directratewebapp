{{-- Financial-year switcher that keeps the other query parameters. --}}
@if ($years->isNotEmpty())
    <form method="GET" class="d-flex align-items-center gap-2">
        @foreach (request()->except('fy', 'page') as $k => $v)
            @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label for="fy" class="small text-muted-2 text-nowrap">Financial Year</label>
        <select id="fy" name="fy" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @foreach ($years as $y)
                <option value="{{ $y->id }}" @selected($fy && $fy->id === $y->id)>{{ $y->label }}</option>
            @endforeach
        </select>
    </form>
@endif
