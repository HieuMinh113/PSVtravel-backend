<?php

namespace App\Filament\Resources\AllianceSources\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Alliance\Models\AllianceSource;
use Modules\Alliance\Services\DongBoLienMinh;

/** Nút "Đọc ngay": đọc lại sheet không chờ lịch 10 phút, báo kết quả tại chỗ. */
class DocNgayAction
{
    public static function make(): Action
    {
        return Action::make('docNgay')
            ->label('Đọc ngay')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize(fn (AllianceSource $record) => auth()->user()?->can('update', $record))
            ->action(fn (AllianceSource $record) => self::chay($record));
    }

    public static function chay(AllianceSource $nguon): void
    {
        $kq = app(DongBoLienMinh::class)->dongBo($nguon);

        if (! $kq['ok']) {
            Notification::make()->danger()
                ->title("Không đọc được sheet {$nguon->name}")
                ->body($kq['loi'])
                ->persistent()
                ->send();

            return;
        }

        $nguon->refresh();
        $canhBao = $nguon->warnings ? ' · '.count($nguon->warnings).' cảnh báo (xem trong trang sửa)' : '';
        Notification::make()->success()
            ->title("Đã đọc {$nguon->name}")
            ->body("{$kq['so_ngay']} ngày khởi hành sắp tới".
                ($kq['cap_nhat_web'] ? " · cập nhật số chỗ {$kq['cap_nhat_web']} ngày đi trên website" : '').
                $canhBao)
            ->send();
    }
}
