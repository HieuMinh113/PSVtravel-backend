<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Payments\Pages\ListPayments;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Models\Payment;

/**
 * Trang của kế toán: mọi khoản thu nhân viên ghi nhận, xem ảnh chuyển khoản,
 * bấm "Đã nhận tiền" / "Từ chối". Không tạo khoản thu ở đây (tạo trong đơn).
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $modelLabel = 'khoản thu';

    protected static ?string $pluralModelLabel = 'Duyệt khoản thu';

    protected static string|\UnitEnum|null $navigationGroup = 'Bán hàng';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'khoan-thu';

    // Nhân viên xem / ghi khoản thu ngay trong đơn; trang này của kế toán
    public static function canViewAny(): bool
    {
        return KhoanThu::laKeToan();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $so = Payment::where('status', 'pending')->whereNotNull('received_by')->count();

        return $so ? (string) $so : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Khoản thu chờ kế toán duyệt';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['booking:id,booking_code,customer_name,assigned_to', 'booking.nguoiPhuTrach:id,name', 'receivedBy:id,name', 'approvedBy:id,name']))
            ->columns([
                TextColumn::make('paid_at')->label('Thời điểm thu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('booking.booking_code')
                    ->label('Đơn')
                    ->description(fn (Payment $r) => $r->booking?->customer_name)
                    ->searchable()
                    ->url(fn (Payment $r) => $r->booking ? BookingResource::getUrl('view', ['record' => $r->booking]) : null),
                TextColumn::make('amount')->label('Số tiền')->money('VND')->weight('bold')->sortable(),
                TextColumn::make('method')->label('Hình thức')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cash' => 'Tiền mặt', 'card' => 'Quẹt thẻ', default => 'Chuyển khoản',
                    }),
                TextColumn::make('transaction_ref')->label('Mã GD')->placeholder('—')->searchable(),
                KhoanThu::cotChungTu(),
                TextColumn::make('receivedBy.name')->label('Người nhập')->placeholder('Cổng thanh toán')
                    ->description(fn (Payment $r) => $r->booking?->nguoiPhuTrach ? 'Phụ trách: '.$r->booking->nguoiPhuTrach->name : null),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => Payment::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'success' => 'success', 'failed' => 'danger', default => 'warning',
                    })
                    ->description(fn (Payment $r) => $r->approvedBy ? $r->approvedBy->name.' · '.$r->approved_at?->format('d/m H:i') : null),
                TextColumn::make('note')->label('Ghi chú')->placeholder('—')->limit(40)
                    ->tooltip(fn (Payment $r) => $r->note)->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                SelectFilter::make('method')->label('Hình thức')
                    ->options(['bank_transfer' => 'Chuyển khoản', 'cash' => 'Tiền mặt', 'card' => 'Quẹt thẻ']),
            ])
            ->recordActions([
                KhoanThu::nutDuyet(),
                KhoanThu::nutTuChoi(),
                KhoanThu::xemChungTu()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
