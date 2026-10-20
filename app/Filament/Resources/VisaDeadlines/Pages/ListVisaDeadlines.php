<?php

namespace App\Filament\Resources\VisaDeadlines\Pages;

use App\Filament\Resources\VisaDeadlines\VisaDeadlineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVisaDeadlines extends ListRecords
{
    protected static string $resource = VisaDeadlineResource::class;

    protected static ?string $title = 'Hạn nộp visa theo nước';

    public function getSubheading(): ?string
    {
        return 'Tour nước ngoài có ngày khởi hành mà hôm nay đã quá hạn nộp hồ sơ visa thì ngày đó tự ẩn trên website (khách không thấy, không đặt được). Ví dụ Trung Quốc 7 ngày: bay ngày 22 thì từ ngày 15 ẩn.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thêm nước')];
    }
}
