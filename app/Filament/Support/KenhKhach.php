<?php

namespace App\Filament\Support;

use App\Services\NguonKhach;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cột + bộ lọc "Kênh" (khách đến từ TikTok, Google, ChatGPT…) dùng chung cho
 * bảng Đơn đặt tour và Tin liên hệ. Dòng phụ là chiến dịch / mã clip.
 */
class KenhKhach
{
    public static function cot(): TextColumn
    {
        return TextColumn::make('kenh')
            ->label('Kênh')
            ->badge()
            ->placeholder('Không rõ')
            ->formatStateUsing(fn (?string $state): string => NguonKhach::nhan($state))
            ->color(fn (?string $state): string => match (true) {
                NguonKhach::laAI($state) => 'success',
                in_array($state, ['google_ads', 'facebook_ads'], true) => 'warning',
                in_array($state, ['tiktok', 'facebook', 'instagram', 'youtube', 'zalo'], true) => 'info',
                default => 'gray',
            })
            ->description(fn (Model $record): ?string => self::chienDich($record->utm))
            ->toggleable();
    }

    public static function boLoc(): SelectFilter
    {
        return SelectFilter::make('kenh')
            ->label('Kênh')
            ->options(['ai' => 'Trợ lý AI (ChatGPT, Perplexity…)', ...NguonKhach::KENH, 'khong_ro' => 'Không rõ'])
            ->query(fn (Builder $q, array $data): Builder => match ($data['value'] ?? null) {
                null, '' => $q,
                'ai' => $q->whereIn('kenh', NguonKhach::AI),
                'khong_ro' => $q->whereNull('kenh'),
                default => $q->where('kenh', $data['value']),
            });
    }

    /** "Chiến dịch · mã clip" để biết clip nào ra đơn */
    public static function chienDich(?array $utm): ?string
    {
        $phan = array_filter([$utm['campaign'] ?? null, $utm['content'] ?? null]);

        return $phan ? implode(' · ', $phan) : null;
    }
}
