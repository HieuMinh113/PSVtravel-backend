<?php

namespace App\Filament\Resources\Tours\Tables;

use App\Services\KiemTraSeoTour;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Category\Models\Category;
use Modules\Tour\Models\Tour;

class ToursTable
{
    /** @var array<int, list<array{muc: string, noi_dung: string}>> */
    private static array $goiY = [];

    /** Gợi ý SEO, tính một lần mỗi tour mỗi lượt tải bảng */
    private static function goiY(Tour $t): array
    {
        return self::$goiY[spl_object_id($t)] ??= KiemTraSeoTour::goiY($t);
    }

    public static function configure(Table $table): Table
    {
        return $table
            // Cột SEO cần lịch trình + danh mục: nạp sẵn, khỏi mỗi dòng một truy vấn
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['itineraries:id,tour_id', 'categories:id,name']))
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Ảnh'),
                TextColumn::make('name')
                    ->label('Tên tour')
                    ->searchable()
                    ->sortable(),
                // Số gợi ý SEO (chi tiết khi rê chuột / mở tour): đỏ = nên sửa ngay
                TextColumn::make('seo')
                    ->label('SEO')
                    ->state(fn (Tour $record): int => count(self::goiY($record)))
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Đạt' : "{$state} gợi ý")
                    ->badge()
                    ->color(fn (Tour $record): string => match (true) {
                        collect(self::goiY($record))->contains('muc', 'loi') => 'danger',
                        self::goiY($record) !== [] => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (Tour $record): ?string => self::goiY($record)
                        ? implode("\n", array_map(fn ($g) => '• '.$g['noi_dung'], self::goiY($record))) : null),
                // Danh mục tour (Miền Trung, Hàn Quốc…) — một tour có thể nằm ở nhiều danh mục
                TextColumn::make('categories.name')
                    ->label('Danh mục')
                    ->badge()
                    ->color('info')
                    ->placeholder('Chưa có')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'abroad' ? 'Nước ngoài' : 'Trong nước'),
                TextColumn::make('adult_price')
                    ->label('Giá người lớn')
                    ->money('VND')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                        default => 'Nháp',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'hidden' => 'warning',
                        default => 'gray',
                    }),
                // Lượt quét mã QR (in trên tờ rơi, poster) — bấm "Mã QR" trong
                // tour để xem chi tiết theo ngày và tải mã.
                TextColumn::make('qr_scans_count')
                    ->label('Lượt quét QR')
                    ->counts('qrScans')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->label('Nổi bật')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Thứ tự')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Lọc theo danh mục (chọn nhiều được) — chia nhóm Trong nước / Nước
                // ngoài; "Chưa gán danh mục" để tìm tour chưa hiện ở mục nào ngoài web
                SelectFilter::make('danh_muc')
                    ->label('Danh mục')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => ['Khác' => ['khong' => 'Chưa gán danh mục']]
                        + Category::query()->orderBy('type')->orderBy('sort_order')->orderBy('name')->get()
                            ->groupBy(fn (Category $c): string => $c->type === 'abroad' ? 'Nước ngoài' : 'Trong nước')
                            ->map(fn ($nhom) => $nhom->pluck('name', 'id')->all())
                            ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $chon = array_map('strval', $data['values'] ?? []);
                        if (! $chon) {
                            return $query;
                        }
                        $ids = array_values(array_diff($chon, ['khong']));

                        return $query->where(fn (Builder $q) => $q
                            ->when($ids, fn (Builder $q) => $q->orWhereHas('categories', fn (Builder $c) => $c->whereIn('categories.id', $ids)))
                            ->when(in_array('khong', $chon, true), fn (Builder $q) => $q->orWhereDoesntHave('categories')));
                    }),
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        'draft' => 'Nháp',
                        'published' => 'Đang bán',
                        'hidden' => 'Đã ẩn',
                    ]),
                TrashedFilter::make()
                    ->label('Đã xoá'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    // Không cho xoá vĩnh viễn hàng loạt: chọn nhầm một tour
                    // đang có đơn là mất trắng lịch sử giao dịch. Muốn xoá hẳn
                    // thì mở từng tour, ở đó có kiểm tra đơn trước khi cho xoá.
                    ForceDeleteBulkAction::make()
                        ->visible(false),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
