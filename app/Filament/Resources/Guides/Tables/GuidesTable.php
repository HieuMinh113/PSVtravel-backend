<?php

namespace App\Filament\Resources\Guides\Tables;

use App\Services\KiemTraSeoBai;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Guide\Models\Guide;

class GuidesTable
{
    /** @var array<int, list<array{muc: string, noi_dung: string}>> */
    private static array $goiY = [];

    /** Gợi ý SEO, tính một lần mỗi bài mỗi lượt tải bảng */
    private static function goiY(Guide $g): array
    {
        return self::$goiY[spl_object_id($g)] ??= KiemTraSeoBai::goiY($g);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')->label('Ảnh'),
                TextColumn::make('title')
                    ->label('Tiêu đề')
                    ->searchable()
                    ->wrap(),
                // Số gợi ý SEO (chi tiết khi rê chuột / mở bài): đỏ = nên sửa ngay
                TextColumn::make('seo')
                    ->label('SEO')
                    ->state(fn (Guide $record): int => count(self::goiY($record)))
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Đạt' : "{$state} gợi ý")
                    ->badge()
                    ->color(fn (Guide $record): string => match (true) {
                        collect(self::goiY($record))->contains('muc', 'loi') => 'danger',
                        self::goiY($record) !== [] => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (Guide $record): ?string => self::goiY($record)
                        ? implode("\n", array_map(fn ($g) => '• '.$g['noi_dung'], self::goiY($record))) : null),
                TextColumn::make('category')
                    ->label('Chuyên mục')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'kinh-nghiem' => 'Kinh nghiệm',
                        'am-thuc' => 'Ẩm thực',
                        'thu-tuc' => 'Thủ tục',
                        'diem-den' => 'Điểm đến',
                        'khac' => 'Khác',
                        default => '—',
                    }),
                TextColumn::make('author.name')
                    ->label('Tác giả')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đã đăng',
                        'hidden' => 'Đã ẩn',
                        default => 'Nháp',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('published_at')
                    ->label('Ngày đăng')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label('Lượt xem')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đã đăng',
                        'hidden' => 'Đã ẩn',
                    ]),
                SelectFilter::make('category')
                    ->label('Chuyên mục')
                    ->options([
                        'kinh-nghiem' => 'Kinh nghiệm du lịch',
                        'am-thuc' => 'Ẩm thực',
                        'thu-tuc' => 'Thủ tục giấy tờ',
                        'diem-den' => 'Điểm đến',
                        'khac' => 'Khác',
                    ]),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
