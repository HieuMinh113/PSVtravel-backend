<?php

namespace App\Filament\Resources\VisaChecklists\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Visa\Models\VisaChecklist;
use Modules\Visa\Services\XuatMauChecklist;
use Modules\Visa\Services\XuatTam;

/**
 * Nút "Xuất file" ở Mẫu checklist: danh sách giấy tờ dạng Word / PDF, đóng
 * ZIP cùng file mẫu đính kèm — để gửi khách qua Zalo / email.
 */
class XuatMauAction
{
    private static function chonDinhDang(): Radio
    {
        return Radio::make('dinh_dang')
            ->label('Định dạng danh sách giấy tờ')
            ->options(XuatMauChecklist::DINH_DANG)
            ->default('word')
            ->required();
    }

    public static function make(): Action
    {
        return Action::make('xuatFile')
            ->label('Xuất file')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading(fn (VisaChecklist $record) => 'Xuất '.$record->name)
            ->modalDescription(fn (VisaChecklist $record) => 'File ZIP gồm danh sách giấy tờ'
                .(count((array) $record->attachments) ? ' và '.count((array) $record->attachments).' file mẫu đính kèm.' : '.'))
            ->schema([self::chonDinhDang()])
            ->modalSubmitActionLabel('Tải ZIP')
            ->action(function (VisaChecklist $record, array $data, Action $action) {
                [$tam, $ten] = app(XuatMauChecklist::class)->zip(collect([$record]), $data['dinh_dang']);
                $action->redirect(XuatTam::cat($tam, $ten));
            });
    }

    public static function hangLoat(): BulkAction
    {
        return BulkAction::make('xuatFileHangLoat')
            ->label('Xuất file (ZIP)')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->modalHeading(fn (Collection $records) => 'Xuất '.$records->count().' mẫu thành một file ZIP')
            ->modalDescription('Mỗi mẫu một thư mục: danh sách giấy tờ + file mẫu đính kèm.')
            ->schema([self::chonDinhDang()])
            ->modalSubmitActionLabel('Tải ZIP')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action) {
                [$tam, $ten] = app(XuatMauChecklist::class)->zip($records->sortBy('sort_order')->values(), $data['dinh_dang']);
                $action->redirect(XuatTam::cat($tam, $ten));
            });
    }
}
