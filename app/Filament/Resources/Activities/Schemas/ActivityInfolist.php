<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

class ActivityInfolist
{
    // Tên tiếng Việt cho các cột hay xuất hiện trong nhật ký
    protected const NHAN_TRUONG = [
        'name' => 'Tên',
        'slug' => 'Đường dẫn',
        'status' => 'Trạng thái',
        'adult_price' => 'Giá người lớn',
        'child_price' => 'Giá trẻ em',
        'old_price' => 'Giá gốc',
        'is_featured' => 'Nổi bật',
        'type' => 'Loại tour',
        'start_date' => 'Ngày khởi hành',
        'seats_total' => 'Tổng số chỗ',
        'seats_left' => 'Số chỗ còn',
        'price_override' => 'Giá riêng',
        'payment_status' => 'Thanh toán',
        'total_price' => 'Tổng tiền',
        'tour_departure_id' => 'Đợt khởi hành',
        'adults' => 'Số người lớn',
        'children' => 'Số trẻ em',
        'customer_name' => 'Tên khách',
        'customer_phone' => 'Điện thoại khách',
        'cancel_reason' => 'Lý do huỷ',
        'email' => 'Email',
        'phone' => 'Số điện thoại',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Thời điểm')
                    ->dateTime('d/m/Y H:i:s'),
                TextEntry::make('causer.name')
                    ->label('Người thực hiện')
                    ->placeholder('Hệ thống'),
                TextEntry::make('event')
                    ->label('Hành động')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ActivityResource::nhanHanhDong($state)),
                TextEntry::make('subject_type')
                    ->label('Đối tượng')
                    ->formatStateUsing(fn (?string $state): string => ActivityResource::nhanDoiTuong($state)),
                TextEntry::make('subject_id')
                    ->label('Mã bản ghi')
                    ->placeholder('—'),

                TextEntry::make('chi_tiet')
                    ->label('Chi tiết thay đổi')
                    ->state(fn (Activity $record): array => self::dungDanhSach($record))
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('Không có thay đổi thuộc tính')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Dựng danh sách dòng "Tên trường: giá trị cũ → giá trị mới"
     */
    protected static function dungDanhSach(Activity $record): array
    {
        $duLieu = $record->attribute_changes;

        if ($duLieu instanceof \Illuminate\Support\Collection) {
            $duLieu = $duLieu->toArray();
        }

        if (! is_array($duLieu)) {
            return [];
        }

        $moi = $duLieu['attributes'] ?? [];
        $cu = $duLieu['old'] ?? [];

        if (empty($moi)) {
            return [];
        }

        $dong = [];

        foreach ($moi as $truong => $giaTriMoi) {
            $nhan = self::NHAN_TRUONG[$truong] ?? $truong;

            // Bản ghi mới tạo thì không có giá trị cũ
            if (empty($cu)) {
                $dong[] = $nhan.': '.self::hienThi($giaTriMoi);

                continue;
            }

            $dong[] = $nhan.': '.self::hienThi($cu[$truong] ?? null).' → '.self::hienThi($giaTriMoi);
        }

        return $dong;
    }

    protected static function hienThi($giaTri): string
    {
        if (is_null($giaTri) || $giaTri === '') {
            return '(trống)';
        }

        if (is_bool($giaTri)) {
            return $giaTri ? 'có' : 'không';
        }

        if (is_array($giaTri)) {
            return json_encode($giaTri, JSON_UNESCAPED_UNICODE);
        }

        if (is_numeric($giaTri) && $giaTri >= 1000) {
            return number_format((float) $giaTri, 0, ',', '.');
        }

        return (string) $giaTri;
    }
}