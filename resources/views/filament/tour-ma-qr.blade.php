{{-- Nội dung modal "Mã QR" của tour (App\Filament\Resources\Tours\Actions\MaQrAction) --}}
<div style="display:flex;flex-direction:column;gap:1rem;align-items:center;text-align:center">
    <img src="{{ $anhXemTruoc }}" alt="Mã QR tour {{ $tour->name }}"
         style="width:240px;height:240px;border-radius:12px;border:1px solid rgba(0,0,0,.08)">

    <div style="font-size:.8rem;opacity:.75;word-break:break-all">
        Quét mã sẽ mở: <strong>{{ $duongDan }}</strong><br>
        (tự chuyển tới trang tour — đổi tên tour thì mã đã in vẫn dùng được)
    </div>

    @if ($tour->status !== 'published')
        <div style="font-size:.8rem;color:#b45309">
            Tour chưa ở trạng thái "Đang bán": khách quét mã sẽ được đưa tới trang danh sách tour.
        </div>
    @endif

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center">
        <x-filament::button tag="a" :href="$linkPng" icon="heroicon-o-arrow-down-tray">
            Tải PNG (đăng mạng xã hội)
        </x-filament::button>
        <x-filament::button tag="a" :href="$linkSvg" color="gray" icon="heroicon-o-arrow-down-tray">
            Tải SVG (gửi nhà in)
        </x-filament::button>
    </div>

    <div style="width:100%;border-top:1px solid rgba(0,0,0,.08);padding-top:.75rem">
        <div style="font-weight:600;margin-bottom:.5rem">Lượt quét</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem">
            @foreach ([['Hôm nay', $thongKe['hom_nay']], ['7 ngày', $thongKe['bay_ngay']], ['30 ngày', $thongKe['ba_muoi_ngay']], ['Tất cả', $thongKe['tong']]] as [$nhan, $so])
                <div style="border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:.5rem">
                    <div style="font-size:1.25rem;font-weight:700">{{ number_format($so, 0, ',', '.') }}</div>
                    <div style="font-size:.75rem;opacity:.7">{{ $nhan }}</div>
                </div>
            @endforeach
        </div>
        <div style="font-size:.75rem;opacity:.7;margin-top:.5rem">
            @if ($thongKe['tong'] > 0)
                Quét bằng điện thoại: {{ round($thongKe['dien_thoai'] * 100 / $thongKe['tong']) }}% ·
                Lần gần nhất: {{ $thongKe['gan_nhat']?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
            @else
                Chưa có lượt quét nào.
            @endif
        </div>
    </div>
</div>
