<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Booking\Models\Booking;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('booking_code')
                    ->label('Mã đơn')
                    ->copyable(),
                TextEntry::make('tour.name')
                    ->label('Tour'),
                TextEntry::make('departure.start_date')
                    ->label('Đợt khởi hành')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextEntry::make('user.name')
                    ->label('Tài khoản khách')
                    ->placeholder('Khách vãng lai'),

                TextEntry::make('customer_name')
                    ->label('Tên khách'),
                TextEntry::make('customer_phone')
                    ->label('Số điện thoại')
                    ->copyable(),
                TextEntry::make('customer_email')
                    ->label('Email')
                    ->placeholder('—'),

                TextEntry::make('adults')
                    ->label('Số người lớn')
                    ->numeric(),
                TextEntry::make('children')
                    ->label('Số trẻ em')
                    ->numeric(),
                TextEntry::make('unit_price_adult')
                    ->label('Đơn giá người lớn')
                    ->money('VND'),
                TextEntry::make('unit_price_child')
                    ->label('Đơn giá trẻ em')
                    ->money('VND'),
                TextEntry::make('total_price')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->weight('bold'),

                TextEntry::make('status')
                    ->label('Trạng thái đơn')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => 'Đã xác nhận',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã huỷ',
                        default => 'Chờ xử lý',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'warning',
                    }),
                TextEntry::make('payment_status')
                    ->label('Thanh toán')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'Đã trả',
                        'partial' => 'Trả một phần',
                        default => 'Chưa trả',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        default => 'gray',
                    }),

                TextEntry::make('note')
                    ->label('Ghi chú của khách')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('admin_note')
                    ->label('Ghi chú nội bộ')
                    ->placeholder('—')
                    ->columnSpanFull(),

                TextEntry::make('cancelledBy.name')
                    ->label('Người huỷ')
                    ->placeholder('—')
                    ->visible(fn (Booking $record): bool => $record->status === 'cancelled'),
                TextEntry::make('cancelled_at')
                    ->label('Thời điểm huỷ')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->visible(fn (Booking $record): bool => $record->status === 'cancelled'),
                TextEntry::make('cancel_reason')
                    ->label('Lý do huỷ')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->visible(fn (Booking $record): bool => $record->status === 'cancelled'),

                TextEntry::make('created_at')
                    ->label('Ngày đặt')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Cập nhật lần cuối')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('deleted_at')
                    ->label('Đã xoá lúc')
                    ->dateTime('d/m/Y H:i')
                    ->visible(fn (Booking $record): bool => $record->trashed()),
            ]);
    }
}