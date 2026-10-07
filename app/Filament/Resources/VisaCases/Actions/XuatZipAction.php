<?php

namespace App\Filament\Resources\VisaCases\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Modules\Visa\Models\VisaCase;
use Modules\Visa\Services\XuatHoSoVisa;
use Modules\Visa\Services\XuatTam;

/**
 * Nút "Xuất hồ sơ (ZIP)" — một người, hoặc cả đoàn (chọn nhiều hồ sơ trong
 * bảng). Xuất lúc nào cũng được; còn thiếu giấy tờ thì báo trước, và trong ZIP
 * có file "GIẤY TỜ CÒN THIẾU.txt".
 */
class XuatZipAction
{
    public static function make(): Action
    {
        return Action::make('xuatZip')
            ->label('Xuất hồ sơ (ZIP)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(fn (VisaCase $record) => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArchiveBox)
            ->modalHeading(fn (VisaCase $record) => 'Xuất hồ sơ '.$record->full_name)
            ->modalDescription(fn (VisaCase $record) => self::moTaThieu(collect([$record])))
            ->modalSubmitActionLabel('Tải ZIP')
            ->action(function (VisaCase $record, Action $action) {
                [$tam, $ten] = app(XuatHoSoVisa::class)->motNguoi($record);
                self::ghiNhatKy($record);
                $action->redirect(XuatTam::cat($tam, $ten));
            });
    }

    public static function caDoan(): BulkAction
    {
        return BulkAction::make('xuatZipDoan')
            ->label('Xuất ZIP đoàn')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(fn (Collection $records) => 'Xuất '.$records->count().' hồ sơ thành một file ZIP')
            ->modalDescription(fn (Collection $records) => self::moTaThieu($records))
            ->modalSubmitActionLabel('Tải ZIP')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, BulkAction $action) {
                // Hồ sơ không được sửa (của người khác / chưa ai nhận) thì bỏ ra
                $duoc = $records->filter(fn (VisaCase $hs) => auth()->user()->can('update', $hs));
                if ($duoc->isEmpty()) {
                    Notification::make()->title('Không có hồ sơ nào bạn được xuất.')->warning()->send();

                    return;
                }
                if ($duoc->count() < $records->count()) {
                    Notification::make()->title('Bỏ qua '.($records->count() - $duoc->count()).' hồ sơ không thuộc quyền của bạn.')->warning()->send();
                }

                $dsHoSo = $duoc->sortBy('full_name')->values();
                [$tam, $ten] = app(XuatHoSoVisa::class)->caDoan($dsHoSo);
                $dsHoSo->each(fn ($hs) => self::ghiNhatKy($hs));
                $action->redirect(XuatTam::cat($tam, $ten));
            });
    }

    private static function moTaThieu($dsHoSo): HtmlString
    {
        $dong = $dsHoSo->map(function (VisaCase $hs) {
            $thieu = $hs->giayConThieu();

            return $thieu ? '<li><strong>'.e($hs->full_name).'</strong>: '.e(implode('; ', $thieu)).'</li>' : null;
        })->filter();

        if ($dong->isEmpty()) {
            return new HtmlString('Đã đủ giấy tờ. File ZIP gồm giấy tờ đặt tên theo từng loại và phiếu thông tin.');
        }

        return new HtmlString('<div style="text-align:left">Còn thiếu giấy tờ — vẫn xuất được, trong ZIP sẽ có file <em>GIẤY TỜ CÒN THIẾU.txt</em>:'
            .'<ul style="list-style:disc;padding-left:1.25rem;margin-top:.5rem">'.$dong->implode('').'</ul></div>');
    }

    /** File chứa hộ chiếu, CCCD… rời khỏi hệ thống → ghi lại ai xuất, lúc nào. */
    private static function ghiNhatKy(VisaCase $hs): void
    {
        activity('visa_case')->performedOn($hs)->causedBy(auth()->user())->log('Xuất hồ sơ ZIP');
    }
}
