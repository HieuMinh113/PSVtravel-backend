<?php

namespace App\Filament\Resources\VisaCases\Actions;

use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Visa\Models\VisaCase;

/**
 * Nhân viên visa bấm nhận hồ sơ khách nộp trên web (chưa ai phụ trách). Ai bấm
 * trước được; người bấm sau thấy báo "đã có người nhận".
 */
class NhanHoSoAction
{
    public static function make(): Action
    {
        return Action::make('nhanHoSo')
            ->label('Nhận hồ sơ')
            ->icon(Heroicon::OutlinedHandRaised)
            ->color('success')
            ->button()
            ->visible(fn (VisaCase $record) => auth()->user()?->can('nhan', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (VisaCase $record) => 'Nhận hồ sơ '.$record->full_name.'?')
            ->modalDescription('Hồ sơ sẽ thuộc về bạn. Chỉ quản trị viên mới chuyển được sang người khác.')
            ->modalSubmitActionLabel('Nhận')
            ->action(function (VisaCase $record, Action $action) {
                if (! $record->nhanBoi(auth()->user())) {
                    Notification::make()->title('Hồ sơ này vừa có người khác nhận.')->warning()->send();
                    $action->cancel();
                }

                Notification::make()->title('Đã nhận hồ sơ '.$record->code)->success()->send();
                $action->redirect(VisaCaseResource::getUrl('edit', ['record' => $record]));
            });
    }
}
