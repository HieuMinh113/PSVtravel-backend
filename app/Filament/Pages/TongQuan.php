<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

/**
 * Trang đầu tiên khi đăng nhập: số liệu nhanh, Việc cần làm, Lối tắt, Đơn cần
 * xử lý (các widget khai báo ở AdminPanelProvider). Đặt tên tiếng Việt thay cho
 * "Dashboard" / "Bảng điều khiển" mặc định.
 */
class TongQuan extends Dashboard
{
    protected static ?string $title = 'Tổng quan';

    protected static ?string $navigationLabel = 'Tổng quan';
}
