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
                    giờ chấm, vị trí và lý do được lưu lâu dài. Việc nhận diện khuôn mặt chạy ngay trên máy của bạn, không gửi ảnh cho dịch vụ bên ngoài.
                </div>
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:.9rem">
                    <input type="checkbox" x-model="dongY" style="margin-top:3px">
                    <span>Tôi đồng ý cho công ty dùng ảnh khuôn mặt để chấm công.</span>
                </label>
                <div>
                    <x-filament::button icon="heroicon-o-face-smile" x-on:click="batDau('dang_ky')">Quét đăng ký khuôn mặt</x-filament::button>
                </div>
            </div>
        @else
            <div x-show="buoc === 'cho'" style="display:grid;gap:8px">
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <x-filament::button size="xl" icon="heroicon-o-arrow-right-end-on-rectangle" color="success"
                        x-on:click="batDau('vao')" :disabled="$daVao">
                        {{ $daVao ? 'Đã chấm vào' : 'Chấm công vào' }}
                    </x-filament::button>
                    <x-filament::button size="xl" icon="heroicon-o-arrow-right-start-on-rectangle" color="primary"
                        x-on:click="batDau('ra')">
                        {{ $daRa ? 'Chấm ra lại' : 'Chấm công ra' }}
                    </x-filament::button>
                </div>
                <div style="font-size:.85rem;opacity:.7">Bấm rồi đưa mặt vào khung — máy tự nhận ra bạn và chấm công, không cần chụp.</div>
            </div>
        @endif

        {{-- Quét khuôn mặt --}}
        <div x-show="buoc === 'quet'" x-cloak style="display:grid;gap:12px;max-width:420px">
            <div class="psv-quet" :class="'psv-quet--' + pha">
                <video x-ref="video" playsinline muted></video>
                <div class="psv-quet__nhan" x-text="nhanLoai"></div>
                <div class="psv-quet__khung">
                    <div class="psv-quet__tia"></div>
                </div>
                <div class="psv-quet__tich">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </div>
                <div class="psv-quet__goi-y" x-text="goiY" aria-live="polite"></div>
            </div>
            <div x-show="loai === 'dang_ky'" class="psv-quet__tien-do"><div :style="{ width: (tienDo * 100) + '%' }"></div></div>
            <div><x-filament::button color="gray" x-on:click="datLai()">Huỷ</x-filament::button></div>
        </div>

        {{-- Quét hết giờ mà chưa khớp / không tải được bộ nhận diện --}}
        <div x-show="buoc === 'khong_nhan'" x-cloak style="display:grid;gap:10px;max-width:560px">
            <div style="font-weight:600;color:#b45309" x-text="tieuDeKhongNhan"></div>
            <div style="font-size:.9rem;opacity:.85">
                Thử lại ở chỗ đủ sáng, bỏ khẩu trang / kính râm, nhìn thẳng camera.
                <span x-show="loai !== 'dang_ky'">Vẫn không được (ví dụ ảnh đăng ký đã cũ) thì gửi ảnh để quản lý duyệt, và nhờ quản lý mở đăng ký lại khuôn mặt.</span>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <x-filament::button icon="heroicon-o-arrow-path" x-on:click="batDau(loai)" x-show="! khongMoHinh">Quét lại</x-filament::button>
                <x-filament::button color="warning" icon="heroicon-o-paper-airplane" x-on:click="guiChoQuanLy()"
                    x-show="loai !== 'dang_ky' && anh" x-bind:disabled="dangGui">Gửi ảnh cho quản lý duyệt</x-filament::button>
                <x-filament::button color="gray" x-on:click="datLai()">Huỷ</x-filament::button>
            </div>
        </div>

        {{-- Cần lý do --}}
        <div x-show="buoc === 'ly_do'" x-cloak style="display:grid;gap:10px;max-width:560px">
            <div x-show="daKhop" style="color:#16a34a;font-weight:600">✓ Đã nhận ra khuôn mặt của bạn</div>
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
    <style>
        .psv-quet { position: relative; border-radius: 20px; overflow: hidden; background: #000; aspect-ratio: 3 / 4; }
        .psv-quet video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
        .psv-quet__nhan { position: absolute; top: 12px; left: 50%; transform: translateX(-50%); padding: 4px 12px; border-radius: 999px;
            background: rgba(0,0,0,.55); color: #fff; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        /* Khung oval: phần ngoài khung tối đi, viền đổi màu theo trạng thái */
        .psv-quet__khung { position: absolute; inset: 13% 16% 15%; border-radius: 50%; overflow: hidden;
            border: 3px solid rgba(255,255,255,.75); box-shadow: 0 0 0 100vmax rgba(0,0,0,.5);
            transition: border-color .2s, border-width .2s, background-color .3s; }
        .psv-quet__tia { position: absolute; left: 0; right: 0; height: 22%; top: -22%;
            background: linear-gradient(to bottom, transparent, rgba(255,255,255,.35) 85%, rgba(255,255,255,.9));
            animation: psv-quet-tia 2.2s ease-in-out infinite; }
        .psv-quet__tich { position: absolute; left: 50%; top: 46%; width: 84px; height: 84px; margin: -42px 0 0 -42px; border-radius: 50%;
            background: #22c55e; color: #fff; display: flex; align-items: center; justify-content: center;
            transform: scale(0); opacity: 0; transition: transform .25s cubic-bezier(.3,1.6,.6,1), opacity .2s; }
        .psv-quet__tich svg { width: 48px; height: 48px; }
        .psv-quet__goi-y { position: absolute; left: 12px; right: 12px; bottom: 14px; text-align: center; color: #fff;
            font-size: 1rem; font-weight: 600; text-shadow: 0 1px 3px rgba(0,0,0,.6); }
        .psv-quet__goi-y:empty { display: none; }
        .psv-quet__tien-do { height: 6px; border-radius: 999px; background: rgba(127,127,127,.25); overflow: hidden; }
        .psv-quet__tien-do > div { height: 100%; background: #22c55e; transition: width .25s; }

        .psv-quet--mo .psv-quet__tia { animation: none; opacity: 0; }
        .psv-quet--chinh .psv-quet__khung { border-color: #f59e0b; }
        .psv-quet--chinh .psv-quet__tia { background: linear-gradient(to bottom, transparent, rgba(245,158,11,.35) 85%, rgba(245,158,11,.9)); }
        .psv-quet--giu .psv-quet__khung, .psv-quet--xac_minh .psv-quet__khung { border-color: #22c55e; border-width: 4px; }
        .psv-quet--giu .psv-quet__tia, .psv-quet--xac_minh .psv-quet__tia {
            background: linear-gradient(to bottom, transparent, rgba(34,197,94,.35) 85%, rgba(34,197,94,.95)); animation-duration: 1.1s; }
        .psv-quet--truot .psv-quet__khung { border-color: #ef4444; animation: psv-quet-lac .35s; }
        .psv-quet--truot .psv-quet__tia { opacity: 0; }
        .psv-quet--khop .psv-quet__khung { border-color: #22c55e; border-width: 6px; background-color: rgba(34,197,94,.25); }
        .psv-quet--khop .psv-quet__tia { animation: none; opacity: 0; }
        .psv-quet--khop .psv-quet__tich { transform: scale(1); opacity: 1; }

        @keyframes psv-quet-tia { 0% { top: -22%; } 100% { top: 100%; } }
        @keyframes psv-quet-lac { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
        @media (prefers-reduced-motion: reduce) { .psv-quet__tia { animation: none; opacity: 0; } }
    </style>
</div>
