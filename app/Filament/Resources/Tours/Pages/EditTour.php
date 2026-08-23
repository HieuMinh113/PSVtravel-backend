<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\Tours\TourResource;
use Filament\Actions\DeleteAction;
use Modules\Tour\Models\Tour;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),

            // Xoá vĩnh viễn chỉ cho phép khi tour CHƯA có đơn nào.
            //
            // Cơ sở dữ liệu đã chặn ở tầng dưới, nhưng chặn ở đó thì người dùng
            // nhận một lỗi SQL khó hiểu. Ẩn nút ngay từ đầu và nói rõ lý do.
            ForceDeleteAction::make()
                ->visible(fn (Tour $record): bool => $record->bookings()->count() === 0)
                ->modalDescription('Tour này chưa có đơn đặt nào. Xoá vĩnh viễn sẽ không lấy lại được.'),

            RestoreAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
