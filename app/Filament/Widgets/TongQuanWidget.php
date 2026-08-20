<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Booking\Models\Booking;
use Modules\Review\Models\Review;

/**
 * Dải số liệu đầu trang quản trị.
 *
 * Chọn đúng 4 con số mà người trực cần biết ngay khi mở máy sáng nay,
 * không phải mọi con số có thể đếm được.
 */
class TongQuanWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Đếm lại mỗi 60 giây để người trực không phải tải lại trang
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $homNay = now()->startOfDay();
        $dauThang = now()->startOfMonth();

        $donChoXuLy = Booking::query()->where('status', 'pending')->count();
        $donHomNay = Booking::query()->where('created_at', '>=', $homNay)->count();

        // Chỉ tính đơn đã xác nhận và đã hoàn thành — đơn chờ xử lý chưa chắc thành tiền
        $doanhThuThang = (int) Booking::query()
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('created_at', '>=', $dauThang)
            ->sum('total_price');

        $danhGiaChoDuyet = Review::query()->where('status', 'pending')->count();
        $tinChuaXuLy = ContactMessage::query()->where('status', 'new')->count();

        return [
            Stat::make('Đơn chờ xử lý', (string) $donChoXuLy)
                ->description($donChoXuLy > 0 ? 'Cần gọi khách xác nhận' : 'Đã xử lý hết')
                ->descriptionIcon($donChoXuLy > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($donChoXuLy > 0 ? 'danger' : 'success'),

            Stat::make('Đơn mới hôm nay', (string) $donHomNay)
                ->description('Tính từ 00:00 hôm nay')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('Doanh thu tháng này', number_format($doanhThuThang, 0, ',', '.').'đ')
                ->description('Đơn đã xác nhận và hoàn thành')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Chờ bạn duyệt', $danhGiaChoDuyet + $tinChuaXuLy)
                ->description($danhGiaChoDuyet.' đánh giá · '.$tinChuaXuLy.' tin liên hệ')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color(($danhGiaChoDuyet + $tinChuaXuLy) > 0 ? 'warning' : 'gray'),
        ];
    }
}
