<?php

namespace App\Filament\Resources\VisaCases\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Modules\Visa\Models\VisaCase;

/**
 * Soạn sẵn tin nhắn "còn thiếu giấy tờ gì" để chép gửi khách qua Zalo / SMS.
 */
class TinNhanGiayThieuAction
{
    public static function make(): Action
    {
        return Action::make('tinNhanGiayThieu')
            ->label('Tin nhắn giấy thiếu')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->modalHeading(fn (VisaCase $record) => 'Giấy tờ còn thiếu — '.$record->full_name)
            ->modalDescription('Bôi đen, chép rồi dán gửi khách.')
            ->fillForm(fn (VisaCase $record) => ['tin' => $record->tinNhanGiayThieu()])
            ->schema([
                Textarea::make('tin')
                    ->hiddenLabel()
                    ->rows(12)
                    ->readOnly(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng');
    }
}
