<?php

namespace App\Filament\Resources\MaldivesBookings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Maldives\Models\MaldivesBooking;

class MaldivesBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $usd = fn (?int $v): ?string => $v ? '$'.number_format($v, 0, ',', '.') : null;
        $vnd = fn (?int $v): ?string => $v ? number_format($v, 0, ',', '.').'đ' : null;

        return $schema->components([
            Section::make('Đơn')->columns(3)->schema([
                TextEntry::make('code')->label('Mã đơn')->copyable()->weight('bold'),
                TextEntry::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => MaldivesBooking::TRANG_THAI[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'danger', 'contacting' => 'warning', 'cancelled' => 'gray', default => 'success'
                    }),
                TextEntry::make('nguoiPhuTrach.name')->label('Người phụ trách')->placeholder('Chưa ai nhận'),
                TextEntry::make('created_at')->label('Đặt lúc')->dateTime('d/m/Y H:i'),
                TextEntry::make('source')->label('Nguồn')->formatStateUsing(fn (?string $state) => MaldivesBooking::NGUON[$state] ?? $state),
                TextEntry::make('kenh')->label('Kênh khách đến')->placeholder('—'),
            ]),
            Section::make('Combo')->columns(3)->schema([
                TextEntry::make('combo_name')->label('Combo')->columnSpan(2),
                TextEntry::make('start_date')->label('Khởi hành')->date('d/m/Y')->placeholder('Chưa chọn ngày'),
                TextEntry::make('room_type_name')->label('Hạng phòng')->placeholder('Chưa chọn'),
                TextEntry::make('departure_city')->label('Khởi hành từ')->placeholder('—'),
                TextEntry::make('khach')->label('Số khách')->state(fn (MaldivesBooking $r) => $r->adults.' người lớn'
                    .($r->children ? " · {$r->children} trẻ em" : '').($r->infants ? " · {$r->infants} em bé" : '').($r->rooms ? " · {$r->rooms} phòng" : '')),
            ]),
            Section::make('Khách hàng')->columns(3)->schema([
                TextEntry::make('customer_name')->label('Họ tên'),
                TextEntry::make('customer_phone')->label('Điện thoại')->copyable(),
                TextEntry::make('customer_email')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('note')->label('Khách ghi chú')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Giá (tạm tính lúc đặt)')->columns(3)->schema([
                TextEntry::make('unit_price_usd')->label('Giá 1 người lớn')->formatStateUsing(fn ($state) => $usd($state))->placeholder('Chưa có'),
                TextEntry::make('surcharge_usd')->label('Phụ thu / khách')->formatStateUsing(fn ($state) => $usd($state))->placeholder('—'),
                TextEntry::make('child_percent')->label('Trẻ em')->suffix('% giá người lớn')->placeholder('—'),
                TextEntry::make('total_usd')->label('Tạm tính')->formatStateUsing(fn ($state) => $usd($state))->placeholder('Nhân viên báo giá')->weight('bold'),
                TextEntry::make('exchange_rate')->label('Tỷ giá')->formatStateUsing(fn ($state) => $state ? number_format($state, 0, ',', '.').'đ / USD' : null)->placeholder('—'),
                TextEntry::make('total_vnd')->label('Tổng VNĐ')->formatStateUsing(fn ($state) => $vnd($state))->placeholder('—'),
                TextEntry::make('paid_vnd')->label('Đã thu')->formatStateUsing(fn ($state) => $vnd($state) ?? '0đ'),
                TextEntry::make('admin_note')->label('Ghi chú nội bộ')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }
}
