<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Models\Booking;

/**
 * Bảng đơn cần xử lý ngay trên trang chủ quản trị.
 *
 * Xếp đơn "Chờ xử lý" lên trước bất kể ngày đặt: đơn cũ chưa gọi khách là
 * việc gấp hơn đơn mới vừa vào. Bấm vào dòng nào mở thẳng đơn đó.
 */
class DonMoiNhatWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // Đặt tiêu đề qua builder thay vì thuộc tính $heading — thuộc tính
            // đó đổi kiểu giữa các bản Filament, còn ->heading() thì không.
            ->heading('Đơn cần xử lý')
            ->query(
                Booking::query()
                    ->with(['tour:id,name'])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                    ->latest('created_at')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('booking_code')
                    ->label('Mã đơn')
                    ->fontFamily('mono')
                    ->weight('semibold'),
                TextColumn::make('customer_name')
                    ->label('Khách'),
                TextColumn::make('customer_phone')
                    ->label('Điện thoại')
                    ->copyable()
                    ->copyMessage('Đã chép số điện thoại'),
                TextColumn::make('tour.name')
                    ->label('Tour')
                    ->limit(34)
                    ->wrap(),
                TextColumn::make('total_price')
                    ->label('Tổng tiền')
                    ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.').'đ')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'pending' ? 'Chờ xử lý' : 'Đã xác nhận')
                    ->color(fn (string $state): string => $state === 'pending' ? 'danger' : 'info'),
                TextColumn::make('created_at')
                    ->label('Đặt lúc')
                    ->dateTime('d/m H:i'),
            ])
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Không còn đơn nào chờ xử lý')
            ->emptyStateDescription('Mọi đơn đã được xác nhận hoặc đóng lại.')
            ->paginated(false);
    }

    // Giấu bảng với người không được xem đơn
    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Booking::class) ?? false;
    }
}
