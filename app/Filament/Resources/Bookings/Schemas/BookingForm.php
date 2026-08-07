<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Tour\Models\TourDeparture;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Tour\Models\Tour;
class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('booking_code')
                ->label('Mã đơn')
                ->helperText('Để trống khi tạo mới, hệ thống tự sinh')
                ->disabledOn('edit'),

            Select::make('tour_id')
                ->label('Tour')
                ->relationship('tour', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $tour = Tour::find($state);
                    $set('unit_price_adult', $tour?->adult_price ?? 0);
                    $set('unit_price_child', $tour?->child_price ?? 0);
                })
                ->required(),

            Select::make('tour_departure_id')
                ->label('Đợt khởi hành')
                ->helperText('Có thể để trống, nhân viên chọn sau')
                ->options(function (Get $get): array {
                    $tourId = $get('tour_id');
                    if (! $tourId) {
                        return [];
                    }

                    return TourDeparture::where('tour_id', $tourId)
                        ->orderBy('start_date')
                        ->get()
                        ->mapWithKeys(fn ($d) => [
                            $d->id => $d->start_date->format('d/m/Y').' (còn '.$d->seats_left.' chỗ)',
                        ])
                        ->toArray();
                })
                ->searchable(),

            TextInput::make('customer_name')
                ->label('Tên khách')
                ->required(),
            TextInput::make('customer_phone')
                ->label('Số điện thoại')
                ->tel()
                ->required(),
            TextInput::make('customer_email')
                ->label('Email')
                ->email(),

            TextInput::make('adults')
                ->label('Số người lớn')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::tinhTong($get, $set))
                ->required(),
            TextInput::make('children')
                ->label('Số trẻ em')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::tinhTong($get, $set))
                ->required(),

            TextInput::make('unit_price_adult')
                ->label('Đơn giá người lớn')
                ->helperText('Tự lấy từ tour, sửa được nếu cần')
                ->numeric()
                ->default(0)
                ->minValue(1)
                ->suffix('₫')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::tinhTong($get, $set))
                ->required(),
            TextInput::make('unit_price_child')
                ->label('Đơn giá trẻ em')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->suffix('₫')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::tinhTong($get, $set)),
            TextInput::make('total_price')
                ->label('Tổng tiền')
                ->helperText('Tự tính = người lớn × đơn giá + trẻ em × đơn giá')
                ->numeric()
                ->default(0)
                ->minValue(1)
                ->suffix('₫')
                ->required(),

            Select::make('status')
                ->label('Trạng thái đơn')
                ->options([
                    'pending' => 'Chờ xử lý',
                    'confirmed' => 'Đã xác nhận',
                    'completed' => 'Hoàn thành',
                    'cancelled' => 'Đã huỷ',
                ])
                ->default('pending')
                ->required()
                ->disabledOn('edit')
                ->helperText('Đổi trạng thái bằng nút Xác nhận / Huỷ đơn ở danh sách'),
            Select::make('payment_status')
                ->label('Thanh toán')
                ->options([
                    'unpaid' => 'Chưa trả',
                    'partial' => 'Trả một phần',
                    'paid' => 'Đã trả',
                ])
                ->default('unpaid')
                ->required(),

            Textarea::make('note')
                ->label('Ghi chú của khách')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make('admin_note')
                ->label('Ghi chú nội bộ')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }
    protected static function tinhTong(Get $get, Set $set): void
    {
        $tong = ((int) $get('adults') * (int) $get('unit_price_adult'))
            + ((int) $get('children') * (int) $get('unit_price_child'));

        $set('total_price', $tong);
    }
}