<?php

namespace App\Filament\Resources\Bookings\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Booking\Models\Booking;
use Modules\Page\Models\Setting;

/**
 * Soạn sẵn tin nhắn nhắc khách đóng phần tiền còn lại để chép gửi Zalo; gửi
 * xong bấm "Đã gửi" để tắt nhắc (ghi lại ai nhắn, lúc nào).
 */
class NhacDongTienAction
{
    public static function make(): Action
    {
        return Action::make('nhacDongTien')
            ->label('Nhắc đóng tiền')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color(fn (Booking $record) => $record->canNhacKhach() ? 'danger' : 'gray')
            ->visible(fn (Booking $record) => in_array($record->status, ['pending', 'confirmed'], true)
                && $record->payment_status !== 'paid'
                && (auth()->user()?->can('update', $record) ?? false))
            ->modalHeading(fn (Booking $record) => 'Nhắc đóng tiền — '.$record->customer_name)
            ->modalDescription(fn (Booking $record) => 'Bôi đen, chép rồi dán gửi khách qua Zalo / SMS. SĐT: '.$record->customer_phone)
            ->fillForm(fn (Booking $record) => [
                'tin' => $record->loadMissing(['tour:id,name', 'departure:id,start_date'])
                    ->tinNhanNhacTien(Setting::query()->where('key', 'bank_transfer')->value('value')),
            ])
            ->schema([
                Textarea::make('tin')->hiddenLabel()->rows(14)->readOnly(),
            ])
            ->modalSubmitActionLabel('Đã gửi — đánh dấu đã nhắn')
            ->modalCancelActionLabel('Đóng')
            ->action(function (Booking $record) {
                $record->forceFill(['reminded_at' => now(), 'reminded_by' => auth()->id()])->save();
                activity('booking')->performedOn($record)->causedBy(auth()->user())->log('Đã nhắn khách đóng tiền');
                Notification::make()->title('Đã ghi nhận: đã nhắn khách')->success()->send();
            });
    }
}
