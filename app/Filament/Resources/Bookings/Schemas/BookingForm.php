<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Booking\Models\Booking;
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
                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                    $tour = Tour::find($state);
                    $set('tour_departure_id', null);
                    $set('unit_price_adult', $tour?->adult_price ?? 0);
                    // Tour chưa có giá trẻ em → để trống = chờ báo giá (không phải 0đ)
                    $set('unit_price_child', $tour?->child_price);
                    self::tinhTong($get, $set);
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
                ->searchable()
                // Đợt có giá riêng (phụ thu Tết, khuyến mãi…) thì đơn giá người lớn
                // theo giá ĐỢT — khớp với con số website đã hiển thị cho khách.
                ->live()
                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                    $tour = Tour::find($get('tour_id'));
                    $dot = $state ? TourDeparture::find($state) : null;
                    $set('unit_price_adult', $dot?->price_override ?? $tour?->adult_price ?? 0);
                    // Hạn nhắn khách đóng phần còn lại = ngày đi − 7 ngày
                    $set('remind_on', $dot?->start_date?->copy()->subDays(Booking::NHAC_TRUOC_NGAY)->toDateString());
                    self::tinhTong($get, $set);
                }),

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
                ->helperText('Để TRỐNG = chưa báo giá trẻ em (không tính vào tổng). Nhập 0 nếu trẻ em miễn phí.')
                ->numeric()
                ->minValue(0)
                ->suffix('₫')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Get $get, Set $set) => self::tinhTong($get, $set)),
            TextInput::make('total_price')
                ->label('Tổng tiền')
                ->helperText('Tự tính = người lớn × đơn giá + trẻ em × đơn giá (trẻ em chưa có giá thì chưa tính)')
                ->numeric()
                ->default(0)
                ->minValue(1)
                ->suffix('₫')
                ->disabled()
                ->dehydrated()
                ->required(),

            // Trạng thái KHÔNG cho sửa tay ở bất kỳ đâu — kể cả khi tạo đơn mới.
            // Trước đây ô này mở khi tạo đơn, nhân viên chọn thẳng "Đã xác nhận"
            // là đơn được duyệt mà số chỗ của đợt khởi hành KHÔNG bị trừ, dẫn tới
            // bán quá số chỗ. Mọi thay đổi trạng thái phải đi qua nút Xác nhận /
            // Hoàn thành / Huỷ đơn ở danh sách, vì chỉ ở đó mới có logic cộng trừ chỗ.
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
                ->disabled()
                ->dehydrated()
                ->helperText('Đơn mới luôn ở "Chờ xử lý". Đổi trạng thái bằng nút Xác nhận / Hoàn thành / Huỷ đơn ở danh sách.'),
            Select::make('payment_status')
                ->label('Thanh toán')
                ->options([
                    'unpaid' => 'Chưa trả',
                    'partial' => 'Trả một phần',
                    'paid' => 'Đã trả',
                ])
                ->default('unpaid')
                ->required()
                ->disabledOn('edit')
                ->helperText('Tự tính từ các khoản đã thu ở tab Thanh toán'),

            TextInput::make('deposit_percent')
                ->label('Tỷ lệ cọc')
                ->numeric()
                ->minValue(10)
                ->maxValue(100)
                ->suffix('%')
                ->placeholder('VD: 30')
                ->live(onBlur: true)
                ->helperText(fn (Get $get) => self::moTaCoc($get)),
            DatePicker::make('remind_on')
                ->label('Hạn nhắn khách đóng tiền')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->helperText(fn (?Booking $record) => self::moTaNhac($record)),

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
    /** "Tiền cọc: 3.000.000đ" — tính theo tổng tiền đang có trên form. */
    protected static function moTaCoc(Get $get): string
    {
        $tyLe = (float) ($get('deposit_percent') ?: 0);
        if ($tyLe <= 0) {
            return 'Gõ từ 10 đến 100. Để trống nếu đơn không thu cọc.';
        }
        $coc = (int) (round((int) $get('total_price') * $tyLe / 100 / 1000) * 1000);

        return 'Tiền cọc: '.number_format($coc, 0, ',', '.').'đ (làm tròn nghìn đồng)';
    }

    protected static function moTaNhac(?Booking $record): string
    {
        if ($record?->reminded_at) {
            return 'Đã nhắn khách lúc '.$record->reminded_at->format('H:i d/m/Y')
                .($record->nguoiNhan ? ' — '.$record->nguoiNhan->name : '').'.';
        }

        return 'Tự điền = ngày khởi hành − '.Booking::NHAC_TRUOC_NGAY.' ngày, sửa được. Đến hạn sẽ báo chuông cho người tạo đơn.';
    }

    protected static function tinhTong(Get $get, Set $set): void
    {
        $nguoiLon = (int) ($get('adults') ?: 0);
        $treEm = (int) ($get('children') ?: 0);
        $giaNguoiLon = (int) ($get('unit_price_adult') ?: 0);
        $giaTreEm = (int) ($get('unit_price_child') ?: 0);

        $set('total_price', ($nguoiLon * $giaNguoiLon) + ($treEm * $giaTreEm));
    }
} 