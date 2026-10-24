{{-- Khung "Gợi ý SEO" đầu form sửa tour / bài cẩm nang (App\Services\KiemTraSeoTour, KiemTraSeoBai) --}}
<div style="display:flex;flex-direction:column;gap:.6rem">
    <div style="font-size:.85rem">
        <span style="opacity:.7">Tiêu đề trên Google:</span>
        <strong>{{ $tieuDe }}</strong>
        <span style="opacity:.6">({{ mb_strlen($tieuDe) }}/70 ký tự)</span>
    </div>
    @forelse ($goiY as $g)
        <div style="display:flex;gap:.5rem;align-items:flex-start;font-size:.875rem;line-height:1.5">
            <x-filament::badge :color="$g['muc'] === 'loi' ? 'danger' : 'warning'" size="sm" style="flex:none;margin-top:.1rem">
                {{ $g['muc'] === 'loi' ? 'Nên sửa' : 'Nên có' }}
            </x-filament::badge>
            <span>{{ $g['noi_dung'] }}</span>
        </div>
    @empty
        <div style="display:flex;gap:.5rem;align-items:center;font-size:.875rem">
            <x-filament::badge color="success" size="sm">Đạt</x-filament::badge>
            <span>{{ $dat ?? 'Tour đủ các mục SEO cơ bản.' }}</span>
        </div>
    @endforelse
</div>
