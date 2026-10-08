@php
    $thu = ['CN', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
    $daVao = $homNay?->in_at && $homNay->in_source === 'cham';
    $daRa = $homNay?->out_at && $homNay->out_source === 'cham';
@endphp

<div
    x-load
    x-load-src="{{ $js }}"
    x-data="chamCong({ thuVien: @js($thuVien), moHinh: @js($moHinh) })"
    wire:ignore.self
    style="display:grid;gap:16px"
>
    @if (! $coToaDo && $laQuanLy)
        <x-filament::section>
            <div style="color:#b45309">
                Chưa đặt toạ độ công ty nên máy chưa kiểm tra vị trí.
                <a href="{{ \App\Filament\Pages\CauHinhChamCong::getUrl() }}" style="text-decoration:underline;font-weight:600">Mở Cấu hình chấm công</a>
                — đứng tại công ty bấm "Dùng vị trí hiện tại".
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">
            Hôm nay: {{ $thu[today()->dayOfWeek] }}, {{ today()->format('d/m/Y') }}
        </x-slot>
        <x-slot name="description">
            @if ($lich)
                Giờ làm {{ $lich[0]->format('H:i') }} – {{ $lich[1]->format('H:i') }}
            @else
                Chủ nhật — ngày nghỉ
            @endif
        </x-slot>

        <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px">
            @foreach (['in' => 'Vào', 'out' => 'Ra'] as $p => $nhan)
                <div>
                    <div style="font-size:.8rem;opacity:.7">{{ $nhan }}</div>
                    <div style="font-size:1.6rem;font-weight:700">
                        {{ $homNay?->{"{$p}_at"}?->format('H:i') ?? '--:--' }}
                    </div>
                    @if ($homNay?->{"{$p}_at"})
                        <div style="font-size:.8rem;display:flex;gap:6px;flex-wrap:wrap">
                            @if ($p === 'in' && $homNay->late_minutes)
                                <x-filament::badge color="warning">Trễ {{ $homNay->late_minutes }} phút</x-filament::badge>
                            @endif
                            @if ($p === 'out' && $homNay->early_minutes)
                                <x-filament::badge color="warning">Sớm {{ $homNay->early_minutes }} phút</x-filament::badge>
                            @endif
                            @if ($homNay->ngoaiCongTy($p))
                                <x-filament::badge color="danger">{{ \App\Models\Attendance::VI_TRI[$homNay->{"{$p}_location"}] }}</x-filament::badge>
                            @endif
                            @if ($homNay->{"{$p}_source"} === 'bo_sung')
                                <x-filament::badge color="gray">Bổ sung</x-filament::badge>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Lần đầu: đồng ý + đăng ký khuôn mặt --}}
        @if (! $daDangKy)
            <div x-show="buoc === 'cho'" style="display:grid;gap:10px;max-width:560px">
                <div style="font-weight:600">Đăng ký khuôn mặt (một lần)</div>
                <div style="font-size:.875rem;opacity:.8">
                    Công ty dùng ảnh khuôn mặt của bạn chỉ để xác nhận chấm công. Ảnh chụp mỗi lần chấm được giữ 3 tháng rồi tự xoá;
                    giờ chấm, vị trí và lý do được lưu lâu dài. Việc so khuôn mặt chạy ngay trên máy của bạn, không gửi ảnh cho dịch vụ bên ngoài.
                </div>
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:.9rem">
                    <input type="checkbox" x-model="dongY" style="margin-top:3px">
                    <span>Tôi đồng ý cho công ty dùng ảnh khuôn mặt để chấm công.</span>
                </label>
                <div>
                    <x-filament::button icon="heroicon-o-camera" x-on:click="batDau('dang_ky')">Đăng ký khuôn mặt</x-filament::button>
                </div>
            </div>
        @else
            <div x-show="buoc === 'cho'" style="display:flex;gap:12px;flex-wrap:wrap">
                <x-filament::button size="xl" icon="heroicon-o-arrow-right-end-on-rectangle" color="success"
                    x-on:click="batDau('vao')" :disabled="$daVao">
                    {{ $daVao ? 'Đã chấm vào' : 'Chấm công vào' }}
                </x-filament::button>
                <x-filament::button size="xl" icon="heroicon-o-arrow-right-start-on-rectangle" color="primary"
                    x-on:click="batDau('ra')">
                    {{ $daRa ? 'Chấm ra lại' : 'Chấm công ra' }}
                </x-filament::button>
            </div>
        @endif

        {{-- Camera --}}
        <div x-show="buoc === 'camera'" x-cloak style="display:grid;gap:10px;max-width:420px">
            <div style="position:relative;border-radius:16px;overflow:hidden;background:#000;aspect-ratio:3/4">
                <video x-ref="video" playsinline muted
                    style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1)"></video>
                <div style="position:absolute;inset:12% 15%;border:3px solid;border-radius:50%;pointer-events:none"
                    :style="{ borderColor: thayMat ? '#22c55e' : 'rgba(255,255,255,.6)' }"></div>
            </div>
            <div style="font-size:.9rem" x-text="trangThai"></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <x-filament::button icon="heroicon-o-camera" x-on:click="chup()" x-bind:disabled="!thayMat && !choChupKhongMat">
                    <span x-text="loai === 'dang_ky' ? 'Chụp đăng ký' : (thayMat ? 'Chụp & chấm công' : 'Chụp dù chưa nhận ra mặt')"></span>
                </x-filament::button>
                <x-filament::button color="gray" x-on:click="datLai()">Huỷ</x-filament::button>
            </div>
        </div>

        {{-- Cần lý do --}}
        <div x-show="buoc === 'ly_do'" x-cloak style="display:grid;gap:10px;max-width:560px">
            <div style="font-weight:600">Cần ghi lý do</div>
            <ul style="list-style:disc;padding-left:20px;font-size:.9rem">
                <template x-for="v in viSao"><li x-text="v"></li></template>
            </ul>
            <textarea x-model="lyDo" rows="3" placeholder="VD: đi gặp khách tại khách sạn…, kẹt xe…"
                style="width:100%;border-radius:8px;border:1px solid rgba(127,127,127,.4);padding:8px;background:transparent"></textarea>
            <div style="display:flex;gap:10px">
                <x-filament::button x-on:click="guiLyDo()" x-bind:disabled="dangGui">Gửi chấm công</x-filament::button>
                <x-filament::button color="gray" x-on:click="datLai()">Huỷ</x-filament::button>
            </div>
        </div>

        {{-- Xong --}}
        <div x-show="buoc === 'xong'" x-cloak style="display:grid;gap:8px">
            <div style="color:#16a34a;font-weight:600" x-text="thongBao"></div>
            <template x-for="c in canhBao"><div style="color:#b45309;font-size:.9rem" x-text="c"></div></template>
            <div><x-filament::button color="gray" x-on:click="datLai()">Đóng</x-filament::button></div>
        </div>

        <div x-show="dangGui" x-cloak style="font-size:.9rem;opacity:.8">Đang gửi…</div>
        <div x-show="loi" x-cloak style="color:#dc2626;font-size:.9rem;margin-top:8px" x-text="loi"></div>
    </x-filament::section>
</div>
