<div style="display:grid;gap:12px">
    @foreach ($anh as $i => $url)
        <a href="{{ $url }}" target="_blank" rel="noopener">
            <img src="{{ $url }}" alt="Chứng từ {{ $i + 1 }}" style="width:100%;border-radius:8px;border:1px solid rgba(127,127,127,.3)">
        </a>
    @endforeach
</div>
