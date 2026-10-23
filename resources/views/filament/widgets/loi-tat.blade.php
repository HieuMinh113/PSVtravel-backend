{{-- Trang Tổng quan: lối tắt (App\Filament\Widgets\LoiTatWidget) --}}
<x-filament-widgets::widget>
    <div class="psv-loi-tat" role="navigation" aria-label="Lối tắt">
        @foreach ($this->loiTat() as $l)
            <x-filament::button tag="a" :href="$l['url']" :icon="$l['icon']" color="gray" outlined>
                {{ $l['nhan'] }}
            </x-filament::button>
        @endforeach
    </div>
</x-filament-widgets::widget>
