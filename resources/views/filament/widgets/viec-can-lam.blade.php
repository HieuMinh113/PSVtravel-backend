{{-- Trang Tổng quan: việc cần làm (App\Filament\Widgets\ViecCanLamWidget) --}}
@php($viec = $this->viec())
<x-filament-widgets::widget>
    <x-filament::section heading="Việc cần làm" icon="heroicon-o-clipboard-document-check"
        :description="$viec ? 'Bấm vào một ô để mở đúng danh sách' : null">
        @if ($viec)
            <div class="psv-viec-luoi">
                @foreach ($viec as $v)
                    <a href="{{ $v['url'] }}" class="psv-viec psv-viec-{{ $v['mau'] }}">
                        <span class="psv-viec-so">{{ $v['so'] }}</span>
                        <span class="psv-viec-chu">
                            <span class="psv-viec-nhan">{{ $v['nhan'] }}</span>
                            @if ($v['mo_ta'])
                                <span class="psv-viec-mo-ta">{{ $v['mo_ta'] }}</span>
                            @endif
                        </span>
                        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronRight, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'psv-viec-mui'])) }}
                    </a>
                @endforeach
            </div>
        @else
            <div class="psv-viec-trong">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedCheckCircle) }}
                <span>Không còn việc tồn. Đơn mới, tin liên hệ mới sẽ hiện ở đây.</span>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
