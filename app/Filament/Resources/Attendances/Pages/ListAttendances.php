<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    public function getTabs(): array
    {
        $choDuyet = Attendance::where('review_status', 'cho_duyet')->count();
        $nghiVan = Attendance::query()->where(fn ($q) => self::nghiVan($q))->where('work_date', '>=', today()->subDays(31))->count();

        return [
            'tat_ca' => Tab::make('Tất cả'),
            'cho_duyet' => Tab::make('Chờ duyệt')
                ->badge($choDuyet ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('review_status', 'cho_duyet')),
            'nghi_van' => Tab::make('Nghi vấn khuôn mặt')
                ->badge($nghiVan ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn ($q) => self::nghiVan($q))),
        ];
    }

    /** Chấm bằng khuôn mặt mà mặt không khớp / không thấy mặt. */
    private static function nghiVan($q)
    {
        return $q->where(fn ($w) => $w->where('in_source', 'cham')->where(fn ($x) => $x->where('in_face_ok', false)->orWhereNull('in_face_ok')))
            ->orWhere(fn ($w) => $w->where('out_source', 'cham')->where(fn ($x) => $x->where('out_face_ok', false)->orWhereNull('out_face_ok')));
    }
}
