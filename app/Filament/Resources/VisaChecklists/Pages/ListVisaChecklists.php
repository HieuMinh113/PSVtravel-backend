<?php

namespace App\Filament\Resources\VisaChecklists\Pages;

use App\Filament\Resources\VisaChecklists\VisaChecklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaChecklists extends ListRecords
{
    protected static string $resource = VisaChecklistResource::class;

    protected static ?string $title = 'Mẫu checklist visa';

    public function getSubheading(): ?string
    {
        return 'Mỗi nước / mục đích / đối tượng một danh sách giấy tờ. Hồ sơ chọn mẫu sẽ chép danh sách về để sửa riêng.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm mẫu')];
    }
}
