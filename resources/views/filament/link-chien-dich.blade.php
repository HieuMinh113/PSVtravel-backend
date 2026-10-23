{{-- Kết quả trang "Tạo link chiến dịch" (App\Filament\Pages\TaoLinkChienDich) --}}
@if ($link)
    <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start">
        <div style="flex:1 1 320px;min-width:0;display:flex;flex-direction:column;gap:.75rem">
            <div x-data="{ daChep: false }" style="display:flex;flex-direction:column;gap:.5rem">
                <code style="display:block;padding:.75rem;border-radius:10px;border:1px solid rgba(0,0,0,.1);word-break:break-all;font-size:.85rem">{{ $link }}</code>
                <div>
                    <x-filament::button icon="heroicon-o-clipboard-document"
                        x-on:click="navigator.clipboard.writeText(@js($link)); daChep = true; setTimeout(() => daChep = false, 2000)">
                        <span x-show="!daChep">Chép link</span>
                        <span x-show="daChep" x-cloak>Đã chép</span>
                    </x-filament::button>
                </div>
            </div>
            <div style="font-size:.8rem;opacity:.75;line-height:1.6">
                Dán link này vào bài đăng, mô tả clip, nút quảng cáo hoặc email. Khách bấm vào rồi đặt tour / gửi liên hệ trong
                30 ngày thì đơn ghi đúng kênh và chiến dịch: xem ở <strong>Bán hàng → Nguồn khách</strong> và trong Google Analytics
                (Báo cáo → Thu nạp → Chiến dịch).
            </div>
        </div>
        <div style="flex:0 0 auto;display:flex;flex-direction:column;gap:.5rem;align-items:center">
            <img src="{{ $qrSvg }}" alt="Mã QR của link" style="width:180px;height:180px;border-radius:12px;border:1px solid rgba(0,0,0,.08)">
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center">
                <x-filament::button tag="a" :href="$qrPng" download="{{ $tenTep }}.png" size="sm" icon="heroicon-o-arrow-down-tray">PNG</x-filament::button>
                <x-filament::button tag="a" :href="$qrSvg" download="{{ $tenTep }}.svg" size="sm" color="gray" icon="heroicon-o-arrow-down-tray">SVG (nhà in)</x-filament::button>
            </div>
            @if ($canhBaoQr)
                <div style="font-size:.75rem;opacity:.7;max-width:220px;text-align:center">{{ $canhBaoQr }}</div>
            @endif
        </div>
    </div>
@else
    <div style="font-size:.9rem;opacity:.7">{{ $loi }}</div>
@endif
