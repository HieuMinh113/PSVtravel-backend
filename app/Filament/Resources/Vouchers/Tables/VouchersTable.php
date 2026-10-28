<?php

namespace App\Filament\Resources\Vouchers\Tables;

use App\Filament\Resources\VoucherBatches\Actions\VoucherActions;
use App\Models\Voucher;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bảng mã voucher — dùng ở mục "Mã voucher" (mọi đợt) và trong trang sửa đợt.
 */
class VouchersTable
{
    public const TRANG_THAI_LUU = ['unused' => 'Chưa dùng', 'used' => 'Đã dùng', 'cancelled' => 'Đã huỷ'];

    public static function configure(Table $table, bool $coCotDot = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['batch', 'nguoiQuet']))
            ->columns(array_values(array_filter([
                TextColumn::make('serial')->label('Số')->sortable()
                    ->formatStateUsing(fn ($state) => VoucherActions::so((int) $state)),
                TextColumn::make('code')->label('Mã')->fontFamily('mono')->weight('semibold')->copyable()->copyMessage('Đã chép mã')
                    // gõ "psv 7kq3", "7KQ3MZ8T" hay dán link QR đều tìm được
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('lookup_key', 'like', '%'.Voucher::khoa($search).'%')),
                $coCotDot ? TextColumn::make('batch.name')->label('Đợt')->limit(24)
                    ->description(fn (Voucher $r) => VoucherActions::tien($r->batch?->value)) : null,
                TextColumn::make('trang_thai')->label('Trạng thái')->badge()
                    ->state(fn (Voucher $r) => $r->trangThai())
                    ->formatStateUsing(fn (string $state) => Voucher::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'con_dung' => 'success',
                        'da_dung' => 'info',
                        'het_han', 'tam_ngung' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('issued_to')->label('Phát cho')->limit(30)->placeholder('—')->toggleable(),
                TextColumn::make('used_at')->label('Dùng lúc')->dateTime('d/m/Y H:i')->sortable()->placeholder('—')
                    ->description(fn (Voucher $r) => $r->nguoiQuet?->name),
                TextColumn::make('used_note')->label('Ghi chú khi dùng')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancel_reason')->label('Lý do huỷ')->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->defaultSort('serial')
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(self::TRANG_THAI_LUU),
                ...($coCotDot ? [SelectFilter::make('batch_id')->label('Đợt')->relationship('batch', 'name')->searchable()->preload()] : []),
            ])
            ->recordActions([
                VoucherActions::daDung(),
                ActionGroup::make([VoucherActions::taiAnh(), VoucherActions::boDanhDau(), VoucherActions::huy()]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([VoucherActions::xuatMaChon(), VoucherActions::ghiPhatCho(), VoucherActions::huyHangLoat()]),
            ])
            ->emptyStateHeading('Chưa có mã')
            ->emptyStateDescription('Mở đợt voucher, bấm "Tạo mã".');
    }
}
