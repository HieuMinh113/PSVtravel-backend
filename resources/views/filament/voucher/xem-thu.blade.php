{{-- Xem thử vị trí ô mã: ảnh thật (đã lưu) hoặc khung mô phỏng 155×100 mm --}}
@php
    $pt = fn ($v, $tong) => round($v / $tong * 100, 3).'%';
@endphp
<div style="display:grid;gap:8px;max-width:760px">
    <div style="font-size:.875rem;font-weight:600">Xem thử (mã mẫu)</div>
    @if ($url)
        <img src="{{ $url }}" alt="Xem thử voucher" loading="lazy"
            style="width:100%;aspect-ratio:155/100;object-fit:cover;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.25);background:#f3f4f6">
        <div style="font-size:.8rem;opacity:.7">
            Đúng như bản in. Ảnh vừa chọn lại / đổi mặt sau hiện ở đây sau khi bấm <b>Lưu</b>.
        </div>
    @else
        <div style="position:relative;width:100%;aspect-ratio:155/100;border-radius:10px;border:2px dashed rgba(127,127,127,.45);background:repeating-linear-gradient(135deg,rgba(127,127,127,.06) 0 12px,transparent 12px 24px)">
            <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.85rem;opacity:.6;text-align:center;padding:0 30%">
                Khổ 155 × 100 mm
            </div>
            <div style="position:absolute;left:{{ $pt($o['trai'], 155) }};top:{{ $pt($o['tren'], 100) }};width:{{ $pt($o['rong'], 155) }};height:{{ $pt($o['cao'], 100) }};background:#fff;border-radius:6px;box-shadow:0 0 0 2px #1d4ed8;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4%;color:#111827">
                <div style="width:{{ round($o['qr'] / $o['rong'] * 100) }}%;aspect-ratio:1;background:repeating-conic-gradient(#111827 0 25%,#fff 0 50%) 0 0/20% 20%"></div>
                <div style="font:700 clamp(6px,1.3vw,11px) ui-monospace,monospace">PSV-ABCD-2345</div>
            </div>
        </div>
        <div style="font-size:.8rem;opacity:.7">
            {{ $chuaLuu ? 'Tải ảnh lên rồi bấm Tạo — trang sửa đợt sẽ hiện ảnh thật kèm ô mã.' : 'Chưa có ảnh mặt này — tải ảnh lên rồi bấm Lưu.' }}
            Ô mã: {{ rtrim(rtrim(number_format($o['rong'], 1, ',', ''), '0'), ',') }} × {{ rtrim(rtrim(number_format($o['cao'], 1, ',', ''), '0'), ',') }} mm.
        </div>
    @endif
</div>
