{{-- Ô chọn vị trí: xem public/js/psv/chon-vi-tri.js. Gắn với hai ô lat / lng (và zoom nếu có) cùng form. --}}
@php
    $goc = \Illuminate\Support\Str::beforeLast($getStatePath(), '.');
    $js = asset('js/psv/chon-vi-tri.js').'?v='.@filemtime(public_path('js/psv/chon-vi-tri.js'));
@endphp

<div
    x-load
    x-load-src="{{ $js }}"
    x-data="chonViTri({
        lat: $wire.$entangle(@js($goc.'.lat')),
        lng: $wire.$entangle(@js($goc.'.lng')),
        @if ($coZoom) zoom: $wire.$entangle(@js($goc.'.zoom')), @endif
        tam: @js($tam),
        diemKhac: @js($diemKhac),
        docLink: @js(route('filament.admin.psv.doc-link-ban-do')),
    })"
    wire:ignore
    style="display:grid;gap:10px"
>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <div style="flex:1 1 260px">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input type="search" x-model="timKiem" x-on:keydown.enter.prevent="tim()"
                    placeholder="Tìm địa điểm, VD: Bến Thượng Hải, Gyeongbokgung…" aria-label="Tìm địa điểm trên bản đồ" />
            </x-filament::input.wrapper>
        </div>
        <x-filament::button color="gray" x-on:click="tim()" x-bind:disabled="dangTim">
            <span x-text="dangTim ? 'Đang tìm…' : 'Tìm'">Tìm</span>
        </x-filament::button>
    </div>

    <ul x-show="ketQua.length" x-cloak role="listbox" aria-label="Kết quả tìm địa điểm"
        style="border:1px solid rgba(127,127,127,.25);border-radius:10px;overflow:hidden;max-height:220px;overflow-y:auto">
        <template x-for="k in ketQua" :key="k.ten">
            <li role="option">
                <button type="button" x-on:click="chonKetQua(k)" x-text="k.ten"
                    style="display:block;width:100%;text-align:left;padding:8px 12px;font-size:.85rem;border-bottom:1px solid rgba(127,127,127,.15)"></button>
            </li>
        </template>
    </ul>

    <div x-ref="khung" x-show="!khongCoBanDo" style="height:400px;border-radius:12px;overflow:hidden;border:1px solid rgba(127,127,127,.25)"
        role="application" aria-label="Bản đồ chọn vị trí — bấm để đặt ghim"></div>
    <div x-show="khongCoBanDo" x-cloak role="status"
        style="padding:14px 16px;border-radius:12px;border:1px dashed rgba(180,83,9,.5);background:rgba(251,191,36,.08);font-size:.875rem;color:#b45309">
        Trình duyệt này không hiện được bản đồ (thường do tắt "tăng tốc phần cứng"). Vẫn đặt được vị trí:
        dán link Google Maps, tìm theo tên, hoặc gõ toạ độ vào ô bên dưới.
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <div style="flex:1 1 300px">
            <x-filament::input.wrapper prefix-icon="heroicon-o-link">
                <x-filament::input type="text" x-model="dan" x-on:paste="$nextTick(() => docDan())" x-on:keydown.enter.prevent="docDan()"
                    x-bind:disabled="dangDocLink"
                    placeholder="Dán link Google Maps (kể cả maps.app.goo.gl) hoặc toạ độ 31.2304, 121.4737" aria-label="Dán link Google Maps hoặc toạ độ" />
            </x-filament::input.wrapper>
        </div>
        @if ($coZoom)
            <x-filament::button color="gray" icon="heroicon-o-viewfinder-circle" x-on:click="layZoom()" x-show="!khongCoBanDo">
                Dùng khung đang thấy<span x-show="zoomHienTai" x-cloak> (zoom <span x-text="zoomHienTai"></span>)</span>
            </x-filament::button>
        @endif
    </div>

    <div x-show="dangDocLink" x-cloak role="status" style="font-size:.85rem;color:#0169a9">Đang đọc link Google Maps…</div>
    <div style="font-size:.85rem;opacity:.75">
        Bấm vào bản đồ để đặt ghim, kéo ghim để chỉnh cho chính xác — ô Vĩ độ / Kinh độ tự điền, không cần gõ tay.
        <span x-show="nenDuPhong" x-cloak style="color:#b45309">Không tải được nền bản đồ chi tiết — vẫn bấm chọn được, hoặc dán link Google Maps.</span>
    </div>
    <div x-show="thongBao && !loi" x-cloak x-text="thongBao" style="font-size:.85rem;color:#15803d"></div>
    <div x-show="loi" x-cloak x-text="loi" role="alert" style="font-size:.85rem;color:#dc2626"></div>
</div>
