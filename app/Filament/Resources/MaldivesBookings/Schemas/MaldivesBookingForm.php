<?php

namespace App\Filament\Resources\MaldivesBookings\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Maldives\Models\MaldivesBooking;
use Modules\Maldives\Models\MaldivesCombo;
use Modules\Maldives\Models\MaldivesComboDate;
use Modules\Maldives\Models\MaldivesRoomType;
use Modules\Maldives\Services\BaoGiaMaldives;

class MaldivesBookingForm
{
    public static function configure(Schema $schema): Schema
    {
        $combo = fn (Get $get): ?MaldivesCombo => $get('combo_id') ? MaldivesCombo::withTrashed()->with(['dates', 'resort.roomTypes'])->find($get('combo_id')) : null;

        return $schema->columns(1)->components([
            Section::make('Combo khách chọn')->columns(2)->schema([
                Select::make('combo_id')->label('Combo')->relationship('combo', 'name')->searchable()->preload()->required()->live(),
                Select::make('combo_date_id')->label('Ngày khởi hành')
                    ->options(fn (Get $get) => $combo($get)?->dates->mapWithKeys(fn (MaldivesComboDate $d) => [$d->id => $d->start_date->format('d/m/Y').($d->label ? " ({$d->label})" : '')]) ?? [])
                    ->live(),
                Select::make('room_type_id')->label('Hạng phòng')
                    ->options(fn (Get $get) => $combo($get)?->resort?->roomTypes->pluck('name', 'id') ?? []),
                Select::make('departure_city')->label('Khởi hành từ')
                    ->options(fn (Get $get) => collect($combo($get)?->departure_cities ?? [])->pluck('city', 'city')->filter()),
                Grid::make(4)->columnSpanFull()->schema([
                    TextInput::make('adults')->label('Người lớn')->numeric()->minValue(1)->default(2)->required(),
                    TextInput::make('children')->label('Trẻ em')->numeric()->minValue(0)->default(0),
                    TextInput::make('infants')->label('Em bé dưới 2 tuổi')->numeric()->minValue(0)->default(0),
                    TextInput::make('rooms')->label('Số phòng')->numeric()->minValue(1),
                ]),
            ]),
            Section::make('Khách hàng')->columns(2)->schema([
                TextInput::make('customer_name')->label('Họ tên')->required()->maxLength(255),
                TextInput::make('customer_phone')->label('Số điện thoại')->required()->tel()->maxLength(30),
                TextInput::make('customer_email')->label('Email')->email()->maxLength(255),
                Textarea::make('note')->label('Khách ghi chú')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Giá & xử lý')->columns(2)
                ->description('Để trống "Tạm tính (USD)" thì khi lưu tự tính lại theo bảng giá combo. Số chốt cuối cùng ghi vào "Tổng VNĐ".')
                ->schema([
                    TextInput::make('unit_price_usd')->label('Giá 1 người lớn (USD)')->numeric()->prefix('$'),
                    TextInput::make('total_usd')->label('Tạm tính (USD)')->numeric()->prefix('$'),
                    TextInput::make('exchange_rate')->label('Tỷ giá (VNĐ / USD)')->numeric(),
                    TextInput::make('total_vnd')->label('Tổng VNĐ')->numeric()->suffix('đ'),
                    TextInput::make('paid_vnd')->label('Đã thu (VNĐ)')->numeric()->default(0)->suffix('đ'),
                    Select::make('status')->label('Trạng thái')->options(MaldivesBooking::TRANG_THAI)->default('contacting')->required(),
                    Select::make('assigned_to')->label('Người phụ trách')
                        ->options(fn () => User::query()->permission('Update:MaldivesBooking')->orderBy('name')->pluck('name', 'id'))
                        ->searchable(),
                    Textarea::make('admin_note')->label('Ghi chú nội bộ')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    /** Trước khi lưu: chốt tên combo / ngày / hạng phòng; chưa có giá thì tính theo bảng giá */
    public static function chuanBi(array $d): array
    {
        $combo = MaldivesCombo::withTrashed()->find($d['combo_id'] ?? null);
        $ngay = filled($d['combo_date_id'] ?? null) ? MaldivesComboDate::find($d['combo_date_id']) : null;
        $phong = filled($d['room_type_id'] ?? null) ? MaldivesRoomType::find($d['room_type_id']) : null;
        $d['combo_name'] = $combo?->name;
        $d['start_date'] = $ngay?->start_date;
        $d['room_type_name'] = $phong?->name;
        if ($combo && blank($d['total_usd'] ?? null)) {
            $gia = BaoGiaMaldives::tinh($combo, $ngay, $phong?->id, $d['departure_city'] ?? null,
                (int) ($d['adults'] ?? 1), (int) ($d['children'] ?? 0), (int) ($d['infants'] ?? 0));
            $d['unit_price_usd'] = filled($d['unit_price_usd'] ?? null) ? $d['unit_price_usd'] : $gia['don_gia_usd'];
            $d['surcharge_usd'] = $gia['phu_thu_usd'];
            $d['child_percent'] = $combo->child_percent;
            $d['total_usd'] = $gia['tong_usd'];
            $d['exchange_rate'] = filled($d['exchange_rate'] ?? null) ? $d['exchange_rate'] : $gia['ty_gia'];
            $d['total_vnd'] = filled($d['total_vnd'] ?? null) ? $d['total_vnd']
                : ($d['total_usd'] && $d['exchange_rate'] ? (int) $d['total_usd'] * (int) $d['exchange_rate'] : null);
        }

        return $d;
    }
}
