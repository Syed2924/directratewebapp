{{-- @include('activity.partials.dropzone', ['name' => 'attachments[]', 'id' => 'attachments', 'multiple' => true, 'hint' => '...']) --}}
<label class="dropzone d-block" for="{{ $id }}" data-dropzone>
    <i class="bi bi-cloud-arrow-up fs-3 d-block mb-1 text-primary"></i>
    <span class="fw-medium text-reset">Drag &amp; drop {{ ($multiple ?? false) ? 'files' : 'a file' }} here or <span class="text-primary text-decoration-underline">click to browse</span></span>
    <span class="d-block small-2 mt-1">{{ $hint ?? 'PDF, DOC, DOCX, JPG, PNG · Max ' . (config('activity.upload_max_kb') / 1024) . ' MB each' }}</span>
    <input type="file" id="{{ $id }}" name="{{ $name }}" @if ($multiple ?? false) multiple @endif accept="{{ $accept ?? '.' . implode(',.', config('activity.upload_mimes')) }}">
    <ul class="file-list text-start" data-file-list></ul>
</label>
@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-dropzone]').forEach(function (zone) {
                var input = zone.querySelector('input[type=file]'), list = zone.querySelector('[data-file-list]');
                function render() {
                    list.innerHTML = '';
                    Array.prototype.forEach.call(input.files, function (f) {
                        var li = document.createElement('li');
                        li.innerHTML = '<i class="bi bi-paperclip"></i>';
                        li.appendChild(document.createTextNode(f.name + ' (' + Math.max(1, Math.round(f.size / 1024)) + ' KB)'));
                        list.appendChild(li);
                    });
                }
                input.addEventListener('change', render);
                ['dragenter', 'dragover'].forEach(function (e) { zone.addEventListener(e, function (ev) { ev.preventDefault(); zone.classList.add('drag'); }); });
                ['dragleave', 'drop'].forEach(function (e) { zone.addEventListener(e, function (ev) { ev.preventDefault(); zone.classList.remove('drag'); }); });
                zone.addEventListener('drop', function (ev) {
                    if (!ev.dataTransfer.files.length) return;
                    var dt = new DataTransfer();
                    Array.prototype.slice.call(ev.dataTransfer.files, 0, input.multiple ? 10 : 1).forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                    render();
                });
            });
        </script>
    @endpush
@endonce
