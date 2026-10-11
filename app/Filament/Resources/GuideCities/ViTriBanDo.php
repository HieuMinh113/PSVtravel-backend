<?php

namespace App\Filament\Resources\GuideCities;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;

/**
 * Khối "Vị trí trên bản đồ" dùng chung cho thành phố và điểm: bản đồ chọn
 * ghim (public/js/psv/chon-vi-tri.js) + ô Vĩ độ / Kinh độ (+ mức zoom).
 */
class ViTriBanDo
{
    /**
     * @param  Closure|null  $tam  trả [lat, lng, zoom] — nơi bản đồ mở ra khi chưa có toạ độ
     * @param  Closure|null  $diemKhac  trả [['ten','lat','lng'], …] — chấm xám các điểm đã có
     */
    public static function khoi(bool $coZoom = false, ?Closure $tam = null, ?Closure $diemKhac = null): Section
    {
        return Section::make('Vị trí trên bản đồ')
            ->description($coZoom
                ? 'Đặt ghim vào trung tâm thành phố, phóng to vừa khung muốn khách thấy rồi bấm "Dùng khung đang thấy".'
                : 'Đặt ghim đúng chỗ — khách bấm "Chỉ đường" sẽ đi tới toạ độ này.')
            ->columns($coZoom ? 3 : 2)
            ->columnSpanFull()
            ->schema(array_values(array_filter([
                ViewField::make('ban_do')
                    ->hiddenLabel()
                    ->view('filament.ban-do.chon-vi-tri')
                    ->viewData(fn ($livewire, $get) => [
                        'coZoom' => $coZoom,
                        'tam' => $tam ? $tam($livewire, $get) : null,
                        'diemKhac' => $diemKhac ? $diemKhac($livewire, $get) : [],
                    ])
                    ->dehydrated(false)
                    ->columnSpanFull(),
                TextInput::make('lat')
                    ->label('Vĩ độ (lat)')
                    ->numeric()
                    ->required()
                    ->minValue(-90)
                    ->maxValue(90)
                    ->step('any'),
                TextInput::make('lng')
                    ->label('Kinh độ (lng)')
                    ->numeric()
                    ->required()
                    ->minValue(-180)
                    ->maxValue(180)
                    ->step('any'),
                $coZoom ? TextInput::make('zoom')
                    ->label('Mức phóng to khi mở')
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(18)
                    ->step(0.1)
                    ->default(11.5)
                    ->required()
                    ->helperText('11–12: cả thành phố · 13–14: một quận') : null,
            ])));
    }
}
