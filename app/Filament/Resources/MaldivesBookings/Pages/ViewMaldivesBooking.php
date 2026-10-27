<?php

namespace App\Filament\Resources\MaldivesBookings\Pages;

use App\Filament\Resources\MaldivesBookings\MaldivesBookingResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMaldivesBooking extends ViewRecord
{
    protected static string $resource = MaldivesBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Đơn khách tự đặt web chưa ai phụ trách: ai bấm trước người đó nhận
            Action::make('nhanDon')->label('Nhận đơn')->icon('heroicon-o-hand-raised')->color('success')
                ->visible(fn () => $this->record->assigned_to === null && auth()->user()?->can('update', $this->record))
                ->action(function () {
                    $this->record->update([
                        'assigned_to' => auth()->id(),
                        'status' => $this->record->status === 'pending' ? 'contacting' : $this->record->status,
                    ]);
                    Notification::make()->title('Đã nhận đơn '.$this->record->code)->success()->send();
                }),
            Action::make('goi')->label('Gọi khách')->icon('heroicon-o-phone')->color('gray')
                ->url(fn () => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $this->record->customer_phone)),
            EditAction::make(),
        ];
    }
}
