<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\Actions\NhacDongTienAction;
use App\Filament\Resources\Bookings\Actions\PhieuXacNhanAction;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Booking\Exceptions\KhongDuCho;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    /** Đơn đã xác nhận đổi sang số khách / đợt không đủ chỗ → báo, không lưu. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (KhongDuCho $e) {
            Notification::make()->title('Không lưu được')->body($e->getMessage())->danger()->persistent()->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            NhacDongTienAction::make(),
            PhieuXacNhanAction::make(),
            ViewAction::make(),
            RestoreAction::make(),
        ];
    }
}
