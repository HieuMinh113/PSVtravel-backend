<?php

namespace App\Filament\Resources\VoucherBatches\Tables;

use App\Filament\Resources\VoucherBatches\Actions\VoucherActions;
use App\Models\VoucherBatch;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoucherBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'vouchers',
                'vouchers as da_dung_count' => fn (Builder $query) => $query->where('status', 'used'),
                'vouchers as da_huy_count' => fn (Builder $query) => $query->where('status', 'cancelled'),
            ]))
            ->columns([
                ImageColumn::make('front_image')->label('Mẫu')->disk('public')->imageHeight(40),
                TextColumn::make('name')->label('Đợt')->searchable()->weight('semibold')
                    ->description(fn (VoucherBatch $r) => $r->applies_to),
                TextColumn::make('value')->label('Mệnh giá')->sortable()
                    ->formatStateUsing(fn ($state) => VoucherActions::tien((int) $state)),
                TextColumn::make('expires_at')->label('Hạn dùng')->date('d/m/Y')->sortable()
                    ->color(fn (VoucherBatch $r) => $r->hetHan() ? 'danger' : null)
                    ->description(fn (VoucherBatch $r) => $r->hetHan() ? 'Đã hết hạn' : null),
                TextColumn::make('vouchers_count')->label('Số mã')->badge()->color('gray'),
                TextColumn::make('da_dung_count')->label('Đã dùng')->badge()->color(fn (int $state) => $state ? 'success' : 'gray')
                    ->description(fn (VoucherBatch $r) => $r->vouchers_count ? round($r->da_dung_count / $r->vouchers_count * 100).'%' : null),
                TextColumn::make('da_huy_count')->label('Đã huỷ')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => VoucherBatch::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('nguoiTao.name')->label('Người tạo')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Ngày tạo')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(VoucherBatch::TRANG_THAI),
                TrashedFilter::make()->label('Đã xoá'),
            ])
            ->recordActions([
                ActionGroup::make([VoucherActions::taoMa(), VoucherActions::xuatFile(), EditAction::make()]),
            ])
            ->emptyStateHeading('Chưa có đợt voucher')
            ->emptyStateDescription('Bấm "Tạo đợt voucher": nhập mệnh giá, hạn dùng, tải ảnh 2 mặt, rồi tạo mã và tải file in.');
    }
}
