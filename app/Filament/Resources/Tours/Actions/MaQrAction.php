<?php

namespace App\Filament\Resources\Tours\Actions;

use App\Services\MaQrTour;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Modules\Tour\Models\Tour;

/**
 * Nút "Mã QR" ở trang xem / sửa tour: xem trước mã, tải PNG / SVG và xem số
 * lượt quét. Chỉ hiển thị thông tin nên không có nút "Lưu".
 */
class MaQrAction
{
    public static function make(): Action
    {
        return Action::make('maQr')
            ->label('Mã QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->modalHeading(fn (Tour $record): string => 'Mã QR — '.$record->name)
            ->modalWidth('lg')
            ->modalContent(fn (Tour $record): View => view('filament.tour-ma-qr', [
                'tour' => $record,
                'duongDan' => MaQrTour::duongDan($record),
                'anhXemTruoc' => 'data:image/svg+xml;base64,'.base64_encode(app(MaQrTour::class)->svg($record)),
                'thongKe' => MaQrTour::thongKe($record),
                'linkPng' => route('filament.admin.tours.ma-qr', ['tour' => $record, 'dinhDang' => 'png']),
                'linkSvg' => route('filament.admin.tours.ma-qr', ['tour' => $record, 'dinhDang' => 'svg']),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Đóng');
    }
}
