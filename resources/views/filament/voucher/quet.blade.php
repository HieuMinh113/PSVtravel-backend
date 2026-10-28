@php
    use App\Filament\Resources\VoucherBatches\Actions\VoucherActions;
    $mau = [
        'con_dung' => ['#16a34a', 'rgba(22,163,74,.1)', 'CÒN DÙNG ĐƯỢC', 'heroicon-o-check-circle'],
        'da_dung' => ['#dc2626', 'rgba(220,38,38,.08)', 'ĐÃ DÙNG', 'heroicon-o-x-circle'],
        'het_han' => ['#d97706', 'rgba(217,119,6,.1)', 'HẾT HẠN', 'heroicon-o-clock'],
        'tam_ngung' => ['#d97706', 'rgba(217,119,6,.1)', 'ĐỢT TẠM NGƯNG', 'heroicon-o-pause-circle'],
        'da_huy' => ['#dc2626', 'rgba(220,38,38,.08)', 'ĐÃ HUỶ', 'heroicon-o-no-symbol'],
    ];
    $vuaXacNhan = $v && $trangThai === 'da_dung' && (int) $v->used_by === (int) auth()->id() && $v->used_at?->gt(now()->subMinutes(2));
    // Vừa bấm xác nhận: báo xanh "xong" thay vì đỏ "đã dùng"
    $m = $vuaXacNhan ? ['#16a34a', 'rgba(22,163,74,.1)', 'ĐÃ XÁC NHẬN DÙNG', 'heroicon-o-check-badge'] : ($trangThai ? $mau[$trangThai] : null);
@endphp

<div
    x-load
    x-load-src="{{ $js }}"
    x-data="quetVoucher({ thuVien: @js($thuVien) })"
    wire:ignore.self
    style="display:grid;gap:16px;max-width:880px"
>
    <x-filament::section>
        <x-slot name="heading">Quét hoặc nhập mã voucher</x-slot>
        <x-slot name="description">Máy quét cầm tay: bấm vào ô rồi quét. Điện thoại / laptop: bấm "Quét bằng camera".</x-slot>

        <form wire:submit="kiemTra()" style="display:flex;gap:10px;flex-wrap:wrap;align-items:stretch">
            <input x-ref="o" type="text" wire:model="nhap" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="search"
                placeholder="PSV-XXXX-XXXX" aria-label="Mã voucher"
                style="flex:1 1 260px;min-width:0;font:600 1.25rem/1.2 ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.04em;text-transform:uppercase;padding:12px 14px;border-radius:10px;border:1px solid rgba(127,127,127,.45);background:transparent">
            <x-filament::button type="submit" size="xl" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled" wire:target="kiemTra">Kiểm tra</x-filament::button>
            <x-filament::button type="button" size="xl" color="gray" icon="heroicon-o-camera" x-show="! camera" x-on:click="moCamera()" x-bind:disabled="dangMo">
                <span x-text="dangMo ? 'Đang mở camera…' : 'Quét bằng camera'"></span>
            </x-filament::button>
            <x-filament::button type="button" size="xl" color="gray" icon="heroicon-o-x-mark" x-show="camera" x-cloak x-on:click="tatCamera()">Tắt camera</x-filament::button>
        </form>

        <div wire:ignore x-show="camera" x-cloak style="margin-top:14px;max-width:480px">
            <div id="psv-voucher-camera" style="border-radius:14px;overflow:hidden;background:#000"></div>
            <div style="font-size:.85rem;opacity:.75;margin-top:6px">Đưa mã QR (hoặc mã vạch) vào khung — máy tự đọc, không cần bấm chụp.</div>
        </div>
        <div x-show="loi" x-cloak x-text="loi" style="color:#dc2626;font-size:.9rem;margin-top:10px"></div>
    </x-filament::section>

    @if ($khongThay)
        <div style="border:2px solid #dc2626;background:rgba(220,38,38,.08);border-radius:16px;padding:18px 20px;display:grid;gap:6px">
            <div style="display:flex;align-items:center;gap:10px;color:#dc2626;font-weight:800;font-size:1.4rem">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width:30px;height:30px" /> KHÔNG CÓ MÃ NÀY
            </div>
            <div style="font-size:.95rem">Kiểm tra lại từng ký tự (mã không có số 0, 1 và chữ O, I, L). Voucher giả hoặc không phải của PSV Travel thì từ chối.</div>
        </div>
    @elseif ($v)
        <div style="border:2px solid {{ $m[0] }};background:{{ $m[1] }};border-radius:16px;padding:18px 20px;display:grid;gap:14px">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:10px;color:{{ $m[0] }};font-weight:800;font-size:1.5rem;letter-spacing:.02em">
                    <x-filament::icon :icon="$m[3]" style="width:34px;height:34px" />
                    {{ $m[2] }}
                </div>
                <div style="font:700 1.2rem ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.05em">{{ $v->code }}</div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px 20px">
                <div>
                    <div style="font-size:.78rem;opacity:.7">Mệnh giá</div>
                    <div style="font-size:1.6rem;font-weight:800">{{ VoucherActions::tien($v->batch?->value) }}</div>
                </div>
                <div>
                    <div style="font-size:.78rem;opacity:.7">Hạn dùng</div>
                    <div style="font-size:1.05rem;font-weight:600">{{ $v->batch?->expires_at?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:.78rem;opacity:.7">Đợt</div>
                    <div style="font-size:1.05rem;font-weight:600">{{ $v->batch?->name }}</div>
                    <div style="font-size:.8rem;opacity:.7">Số {{ VoucherActions::so($v->serial) }}{{ $v->issued_to ? ' · phát cho '.$v->issued_to : '' }}</div>
                </div>
            </div>
            @if ($v->batch?->applies_to || $v->batch?->conditions)
                <div style="font-size:.9rem;display:grid;gap:4px">
                    @if ($v->batch->applies_to)<div><b>Áp dụng:</b> {{ $v->batch->applies_to }}</div>@endif
                    @if ($v->batch->conditions)<div style="white-space:pre-line"><b>Điều kiện:</b> {{ $v->batch->conditions }}</div>@endif
                </div>
            @endif

            @if ($trangThai === 'con_dung')
                <div style="display:grid;gap:8px">
                    <textarea wire:model="ghiChu" rows="2" maxlength="500" placeholder="Ghi chú (không bắt buộc): mã đơn, tên khách, bàn số…"
                        style="width:100%;border-radius:10px;border:1px solid rgba(127,127,127,.45);padding:10px;background:transparent"></textarea>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <x-filament::button size="xl" color="success" icon="heroicon-o-check" wire:click="xacNhan" wire:loading.attr="disabled" wire:target="xacNhan">
                            Xác nhận đã dùng
                        </x-filament::button>
                        <x-filament::button size="xl" color="gray" x-on:click="quetTiep()">Quét mã khác</x-filament::button>
                    </div>
                    <div style="font-size:.8rem;opacity:.7">Hệ thống chỉ ghi mã đã dùng — trừ {{ VoucherActions::tien($v->batch?->value) }} vào hoá đơn / đơn tour như bình thường.</div>
                </div>
            @else
                @if ($trangThai === 'da_dung')
                    <div style="font-size:.95rem">
                        Dùng lúc <b>{{ $v->used_at?->format('H:i d/m/Y') }}</b>{{ $v->nguoiQuet ? ' — '.$v->nguoiQuet->name.' xác nhận' : '' }}.
                        @if ($v->used_note)<br>Ghi chú: {{ $v->used_note }}@endif
                    </div>
                @elseif ($trangThai === 'da_huy')
                    <div style="font-size:.95rem">Mã đã huỷ{{ $v->cancel_reason ? ': '.$v->cancel_reason : '' }}{{ $v->cancelled_at ? ' ('.$v->cancelled_at->format('d/m/Y').')' : '' }}. Không nhận voucher này.</div>
                @elseif ($trangThai === 'het_han')
                    <div style="font-size:.95rem">Voucher hết hạn từ {{ $v->batch?->expires_at?->format('d/m/Y') }}. Không nhận, trừ khi quản lý đồng ý gia hạn (sửa hạn của đợt).</div>
                @else
                    <div style="font-size:.95rem">Đợt voucher đang tạm ngưng — hỏi Marketing / quản lý trước khi nhận.</div>
                @endif
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <x-filament::button size="lg" x-on:click="quetTiep()" icon="heroicon-o-qr-code">Quét mã khác</x-filament::button>
                    @if ($hoanTacDuoc)
                        <x-filament::button size="lg" color="warning" icon="heroicon-o-arrow-uturn-left" wire:click="hoanTac" wire:confirm="Trả mã này về chưa dùng? Chỉ dùng khi quét nhầm.">
                            Hoàn tác (quét nhầm)
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @if ($ganDay->isNotEmpty())
        <x-filament::section collapsible>
            <x-slot name="heading">Mã bạn xác nhận gần đây</x-slot>
            <div style="display:grid;gap:6px">
                @foreach ($ganDay as $g)
                    <div style="display:grid;grid-template-columns:minmax(130px,1fr) minmax(90px,auto) minmax(0,2fr);gap:12px;font-size:.9rem;border-bottom:1px solid rgba(127,127,127,.18);padding-bottom:6px">
                        <span style="font-family:ui-monospace,monospace;font-weight:600">{{ $g->code }}</span>
                        <span style="text-align:right">{{ VoucherActions::tien($g->batch?->value) }}</span>
                        <span style="opacity:.7;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $g->used_at?->format('H:i d/m') }}{{ $g->used_note ? ' · '.$g->used_note : '' }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</div>
