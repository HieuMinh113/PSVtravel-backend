@php
    $mat = $a->user?->khuonMat;
    $anhDangKy = $mat?->photo ? \Illuminate\Support\Facades\Storage::disk(\App\Models\Attendance::DIA)->temporaryUrl($mat->photo, now()->addMinutes(30)) : null;
    $o = 'border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:10px;display:grid;gap:6px;align-content:start';
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:.85rem">
    <div style="{{ $o }}">
        <strong>Ảnh đăng ký</strong>
        @if ($anhDangKy)
            <img src="{{ $anhDangKy }}" style="width:100%;border-radius:8px" alt="Ảnh đăng ký">
            <span style="opacity:.7">Đồng ý lúc {{ $mat->consented_at?->format('H:i d/m/Y') }}</span>
        @else
            <span style="opacity:.7">Chưa đăng ký</span>
        @endif
    </div>
    @foreach (\App\Models\Attendance::PHAN as $p => $nhan)
        <div style="{{ $o }}">
            <strong>{{ $nhan }} {{ $a->{"{$p}_at"}?->format('H:i') ?? '—' }}</strong>
            @if ($url = $a->linkAnh($p))
                <img src="{{ $url }}" style="width:100%;border-radius:8px" alt="Ảnh {{ $nhan }}">
            @elseif ($a->{"{$p}_at"})
                <span style="opacity:.7">{{ $a->{"{$p}_source"} === 'bo_sung' ? 'Bổ sung công — không có ảnh' : 'Ảnh đã xoá (quá 3 tháng)' }}</span>
            @endif
            @if ($a->{"{$p}_source"} === 'cham')
                <span>Khuôn mặt:
                    @if ($a->{"{$p}_face_ok"} === true) <b style="color:#16a34a">khớp</b>
                    @elseif ($a->{"{$p}_face_ok"} === false) <b style="color:#dc2626">chưa khớp</b>
                    @else <b style="color:#b45309">không thấy mặt</b> @endif
                    @if ($a->{"{$p}_face_distance"} !== null) <span style="opacity:.6">(độ lệch {{ number_format($a->{"{$p}_face_distance"}, 2) }})</span> @endif
                </span>
                <span>Vị trí: {{ \App\Models\Attendance::VI_TRI[$a->{"{$p}_location"}] ?? 'chưa đặt toạ độ công ty' }}
                    @if ($a->{"{$p}_distance"} !== null) — cách {{ $a->{"{$p}_distance"} }} m @endif
                    @if ($a->{"{$p}_accuracy"} !== null) <span style="opacity:.6">(sai số ±{{ $a->{"{$p}_accuracy"} }} m)</span> @endif
                </span>
                @if ($ban = $a->linkBanDo($p))
                    <a href="{{ $ban }}" target="_blank" rel="noopener" style="text-decoration:underline">Mở bản đồ</a>
                @endif
            @endif
            @if ($a->{"{$p}_reason"})
                <span>Lý do: {{ $a->{"{$p}_reason"} }}</span>
            @endif
        </div>
    @endforeach
</div>
