<?php

namespace App\Filament\Resources\Bookings\Actions;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Modules\Booking\Models\Booking;

/** Tải phiếu xác nhận đặt tour (PDF) để gửi khách: tổng tiền, cọc, còn lại, hạn đóng. */
class PhieuXacNhanAction
{
    public static function make(): Action
    {
        return Action::make('phieuXacNhan')
            ->label('Phiếu xác nhận (PDF)')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Booking $record) => auth()->user()?->can('view', $record) ?? false)
            ->url(fn (Booking $record) => route('booking.phieu-xac-nhan', $record), shouldOpenInNewTab: true);
    }
}
