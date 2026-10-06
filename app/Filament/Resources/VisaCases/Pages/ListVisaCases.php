<?php

namespace App\Filament\Resources\VisaCases\Pages;

use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Visa\Models\VisaCase;

class ListVisaCases extends ListRecords
{
    protected static string $resource = VisaCaseResource::class;

    protected static ?string $title = 'Hồ sơ visa';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm hồ sơ')];
    }

    public function getTabs(): array
    {
        $dem = fn (array $trangThai) => VisaCase::whereIn('status', $trangThai)->count();

        return [
            'dang_xu_ly' => Tab::make('Đang xử lý')
                ->badge($dem(['moi', 'dang_gom', 'cho_nop']) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['moi', 'dang_gom', 'cho_nop'])),
            'cho_ket_qua' => Tab::make('Chờ kết quả')
                ->badge($dem(['da_nop']) ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'da_nop')),
            'xong' => Tab::make('Đã xong')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['dau', 'truot', 'huy'])),
            'tat_ca' => Tab::make('Tất cả'),
        ];
    }
}
