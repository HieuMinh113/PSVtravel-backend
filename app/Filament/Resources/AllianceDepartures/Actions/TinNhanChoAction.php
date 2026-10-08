<?php

namespace App\Filament\Resources\AllianceDepartures\Actions;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Modules\Alliance\Models\AllianceDeparture;
use Modules\Alliance\Services\SoanTinLienMinh;

/**
 * "Tin nhắn" ở Tra chỗ liên minh: soạn sẵn đoạn tình trạng chỗ (nhận / hold /
 * sure, đồng giá, hạn visa) để chép gửi sale / khách qua Zalo. Không có tên
 * đối tác, không có hoa hồng. Sửa được trong ô trước khi chép.
 */
class TinNhanChoAction
{
    /** Cả tour của dòng đang bấm: mọi ngày đi sắp tới. */
    public static function make(): Action
    {
        return Action::make('tinNhanTour')
            ->label('Tin nhắn')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->modalHeading('Tin nhắn tình trạng chỗ')
            ->modalDescription('Mọi ngày đi sắp tới của tour này. Sửa thêm nếu cần rồi bấm “Chép tin nhắn”.')
            ->fillForm(fn (AllianceDeparture $record) => ['tin' => $record->tour ? SoanTinLienMinh::tour($record->tour) : ''])
            ->schema([self::oTin()])
            ->modalSubmitAction(false)
            ->extraModalFooterActions([self::nutChep()])
            ->modalCancelActionLabel('Đóng');
    }

    /** Chỉ những ngày đã tick trong bảng (lọc / chọn trước), mỗi tour một đoạn. */
    public static function daChon(): BulkAction
    {
        return BulkAction::make('tinNhanDaChon')
            ->label('Soạn tin nhắn')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalHeading(fn (Collection $records) => 'Tin nhắn tình trạng chỗ — '.$records->count().' ngày đi')
            ->modalDescription('Chỉ các ngày đã chọn, gom theo tour. Sửa thêm nếu cần rồi bấm “Chép tin nhắn”.')
            ->fillForm(fn (Collection $records) => ['tin' => SoanTinLienMinh::nhieu($records)])
            ->schema([self::oTin()])
            ->modalSubmitAction(false)
            ->extraModalFooterActions([self::nutChep()])
            ->modalCancelActionLabel('Đóng');
    }

    private static function oTin(): Textarea
    {
        return Textarea::make('tin')->hiddenLabel()->rows(10)->autosize();
    }

    private static function nutChep(): Action
    {
        // Lấy chữ đang có trong ô (kể cả phần vừa sửa). Trang chạy http (không
        // phải localhost) thì trình duyệt chặn clipboard API → chép kiểu cũ.
        return Action::make('chepTin')
            ->label('Chép tin nhắn')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('primary')
            ->alpineClickHandler(<<<'JS'
                const o = $el.closest('.fi-modal-window').querySelector('textarea');
                const xong = () => $tooltip('Đã chép', { theme: $store.theme, timeout: 1500 });
                if (window.navigator.clipboard && window.isSecureContext) {
                    window.navigator.clipboard.writeText(o.value).then(xong);
                } else {
                    o.select(); document.execCommand('copy'); xong();
                }
                JS);
    }
}
