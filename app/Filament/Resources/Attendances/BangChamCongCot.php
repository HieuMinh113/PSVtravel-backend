<?php

namespace App\Filament\Resources\Attendances;

use App\Models\Attendance;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

/** Cột bảng chấm công — dùng chung trang nhân viên và bảng của quản lý. */
class BangChamCongCot
{
    private const THU = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

    /** @return list<Column> */
    public static function cot(bool $quanLy): array
    {
        $cot = [
            TextColumn::make('work_date')
                ->label('Ngày')
                ->date('d/m/Y')
                ->description(fn (Attendance $r) => self::THU[$r->work_date->dayOfWeek].($r->cong() ? ' · '.str_replace('.', ',', (string) $r->cong()).' công' : ''))
                ->sortable(),
        ];
        if ($quanLy) {
            $cot[] = TextColumn::make('user.name')->label('Nhân viên')->searchable()->sortable();
        }
        foreach (['in' => 'Vào', 'out' => 'Ra'] as $p => $nhan) {
            if ($quanLy) {
                $cot[] = ImageColumn::make("{$p}_photo")->label('')->disk(Attendance::DIA)->visibility('private')
                    ->circular()->imageSize(36)->placeholder('');
            }
            $cot[] = TextColumn::make("{$p}_at")
                ->label($nhan)
                ->time('H:i')
                ->placeholder('—')
                ->weight('bold')
                ->color(fn (Attendance $r) => ($p === 'in' && $r->late_minutes) || ($p === 'out' && $r->early_minutes) ? 'warning' : null)
                ->description(fn (Attendance $r) => self::moTa($r, $p));
        }
        $cot[] = TextColumn::make('ly_do')
            ->label('Lý do')
            ->state(fn (Attendance $r) => collect([$r->in_reason ? 'Vào: '.$r->in_reason : null, $r->out_reason ? 'Ra: '.$r->out_reason : null])->filter()->implode("\n") ?: null)
            ->placeholder('—')
            ->wrap()
            ->lineClamp(3);
        $cot[] = TextColumn::make('review_status')
            ->label('Duyệt')
            ->badge()
            ->formatStateUsing(fn (?string $state) => Attendance::DUYET[$state] ?? '')
            ->color(fn (?string $state) => match ($state) {
                'chap_nhan' => 'success', 'tu_choi' => 'danger', default => 'warning',
            })
            ->description(fn (Attendance $r) => $r->review_note)
            ->placeholder('—');

        return $cot;
    }

    /** "Trễ 12' · Ngoài công ty 2,3 km · Khuôn mặt chưa khớp · Bổ sung" */
    public static function moTa(Attendance $r, string $p): ?string
    {
        if (! $r->{"{$p}_at"}) {
            return null;
        }
        $cach = $r->{"{$p}_distance"};

        return collect([
            $p === 'in' && $r->late_minutes ? 'Trễ '.$r->late_minutes."'" : null,
            $p === 'out' && $r->early_minutes ? 'Sớm '.$r->early_minutes."'" : null,
            $r->{"{$p}_location"} === 'ngoai' ? 'Ngoài CT'.($cach !== null ? ' '.($cach >= 1000 ? number_format($cach / 1000, 1, ',', '.').' km' : $cach.' m') : '') : null,
            $r->{"{$p}_location"} === 'khong_ro' ? 'Không rõ vị trí' : null,
            $r->{"{$p}_source"} === 'bo_sung' ? 'Bổ sung' : null,
            $r->nghiVan($p) ? ($r->{"{$p}_face_ok"} === false ? 'Mặt chưa khớp' : 'Không thấy mặt') : null,
        ])->filter()->implode(' · ') ?: null;
    }
}
