<?php

namespace App\Filament\Resources\AllianceDepartures\Pages;

use App\Filament\Resources\AllianceDepartures\AllianceDepartureResource;
use App\Filament\Resources\AllianceSources\Actions\DocNgayAction;
use App\Filament\Resources\AllianceSources\AllianceSourceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Modules\Alliance\Models\AllianceSource;

class ListAllianceDepartures extends ListRecords
{
    protected static string $resource = AllianceDepartureResource::class;

    protected static ?string $title = 'Tra chỗ liên minh';

    /** Mỗi đối tác: đọc được lúc nào / đang lỗi — để biết số liệu có mới không. */
    public function getSubheading(): string|Htmlable|null
    {
        $nguon = AllianceSource::where('is_active', true)->orderBy('name')->get();
        if ($nguon->isEmpty()) {
            return null;
        }

        return new HtmlString($nguon->map(function (AllianceSource $n) {
            $ten = e($n->name);
            if ($n->last_error) {
                return "<span style=\"color:rgb(220 38 38)\">{$ten}: lỗi đọc sheet</span>";
            }

            return $n->last_success_at
                ? "{$ten}: ".e($n->last_success_at->diffForHumans())
                : "{$ten}: chưa đọc";
        })->implode(' &nbsp;·&nbsp; '));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('docLaiTatCa')
                ->label('Đọc lại tất cả sheet')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('Update:AllianceSource'))
                ->action(function () {
                    foreach (AllianceSource::where('is_active', true)->get() as $nguon) {
                        DocNgayAction::chay($nguon);
                    }
                }),
            Action::make('quanLySheet')
                ->label('Quản lý sheet')
                ->icon(Heroicon::OutlinedTableCells)
                ->url(AllianceSourceResource::getUrl('index'))
                ->visible(fn () => AllianceSourceResource::canViewAny()),
        ];
    }
}
