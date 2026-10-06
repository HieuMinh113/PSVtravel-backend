<?php

namespace App\Filament\Resources\AllianceSources\Pages;

use App\Filament\Resources\AllianceSources\AllianceSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllianceSources extends ListRecords
{
    protected static string $resource = AllianceSourceResource::class;

    protected static ?string $title = 'Sheet liên minh';

    public function getSubheading(): ?string
    {
        return 'Dán link sheet của đối tác — máy tự đọc lại mỗi 10–15 phút. Xem kết quả ở trang “Tra chỗ liên minh”.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm sheet')];
    }
}
