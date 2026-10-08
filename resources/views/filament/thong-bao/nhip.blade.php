{{-- Hỏi máy chủ mỗi 10 giây: thông báo mới (phát tiếng + popup) và con số trên menu --}}
<script
    src="{{ asset('js/psv/thong-bao.js') }}?v={{ @filemtime(public_path('js/psv/thong-bao.js')) }}"
    data-nhip="{{ route('filament.admin.psv.nhip') }}"
    defer
></script>
