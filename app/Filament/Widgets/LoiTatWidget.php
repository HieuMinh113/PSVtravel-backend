<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ChamCong;
use App\Filament\Pages\HuongDan;
use App\Filament\Pages\TaoLinkChienDich;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Guides\GuideResource;
use App\Filament\Resources\Tours\TourResource;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Lối tắt các việc hay làm nhất, chỉ hiện nút người xem có quyền — đỡ phải mở
 * menu tìm "Đơn đặt tour → Tạo".
 */
class LoiTatWidget extends Widget
{
    protected string $view = 'filament.widgets.loi-tat';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** @return list<array{nhan: string, url: string, icon: Heroicon}> */
    public function loiTat(): array
    {
        $ds = [];
        $them = function (bool $duoc, string $nhan, callable $url, Heroicon $icon) use (&$ds) {
            if ($duoc) {
                $ds[] = ['nhan' => $nhan, 'url' => $url(), 'icon' => $icon];
            }
        };

        $them(BookingResource::canCreate(), 'Tạo đơn đặt tour', fn () => BookingResource::getUrl('create'), Heroicon::OutlinedShoppingCart);
        $them(TourResource::canCreate(), 'Thêm tour', fn () => TourResource::getUrl('create'), Heroicon::OutlinedMapPin);
        $them(VisaCaseResource::canCreate(), 'Hồ sơ visa mới', fn () => VisaCaseResource::getUrl('create'), Heroicon::OutlinedIdentification);
        $them(GuideResource::canCreate(), 'Viết bài cẩm nang', fn () => GuideResource::getUrl('create'), Heroicon::OutlinedPencilSquare);
        $them(TaoLinkChienDich::canAccess(), 'Tạo link chiến dịch', fn () => TaoLinkChienDich::getUrl(), Heroicon::OutlinedLink);
        $them(ChamCong::canAccess(), 'Chấm công', fn () => ChamCong::getUrl(), Heroicon::OutlinedFingerPrint);
        $them(true, 'Hướng dẫn sử dụng', fn () => HuongDan::getUrl(), Heroicon::OutlinedQuestionMarkCircle);

        return $ds;
    }
}
