<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Resources\Tours\Actions\MaQrAction;
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
            MaQrAction::make(),
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
    /** Vừa nối (hoặc đổi) tour liên minh → cập nhật số chỗ ngay, không chờ lần đọc sheet sau. */
    protected function afterSave(): void
    {
        if ($this->record->wasChanged('alliance_tour_id') && $this->record->alliance_tour_id) {
            $doi = app(\Modules\Alliance\Services\DongBoLienMinh::class)
                ->capNhatTourPsv([$this->record->alliance_tour_id]);
            if ($doi) {
                \Filament\Notifications\Notification::make()->success()
                    ->title("Đã cập nhật số chỗ {$doi} ngày đi theo sheet liên minh")
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
