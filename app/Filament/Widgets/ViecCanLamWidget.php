<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AllianceSources\AllianceSourceResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\EventReviews\EventReviewResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Redirects\RedirectResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\VisaCases\VisaCaseResource;
use Filament\Widgets\Widget;

/**
 * "Việc cần làm" đầu trang Tổng quan: các số đỏ / vàng đang nằm rải rác trên
 * menu, gom về một chỗ, mỗi ô bấm vào là tới đúng danh sách. Dùng lại đúng con
 * số của menu (getNavigationBadge) nên hai nơi không bao giờ lệch nhau. Mỗi
 * người chỉ thấy việc thuộc mục mình được phép mở.
 */
class ViecCanLamWidget extends Widget
{
    protected string $view = 'filament.widgets.viec-can-lam';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    /** Thứ tự = mức gấp: khách đang chờ trước, việc nội bộ sau */
    public const NGUON = [
        BookingResource::class,
        ContactMessageResource::class,
        VisaCaseResource::class,
        PaymentResource::class,
        ReviewResource::class,
        EventReviewResource::class,
        AttendanceResource::class,
        JobApplicationResource::class,
        AllianceSourceResource::class,
        RedirectResource::class,
    ];

    /**
     * Mô tả ngắn trong ô (chú thích trên menu dài, ô hẹp). Hồ sơ visa dùng chú
     * thích của menu vì nó đổi theo tình huống (chưa ai nhận / có lịch hẹn).
     */
    private const MO_TA = [
        BookingResource::class => 'Tới hạn nhắn khách đóng tiền',
        ContactMessageResource::class => 'Chưa ai xử lý',
        PaymentResource::class => 'Chờ kế toán duyệt',
        ReviewResource::class => 'Chờ duyệt trước khi hiện',
        EventReviewResource::class => 'Chờ duyệt trước khi hiện',
        AttendanceResource::class => 'Lý do / bổ sung công chờ duyệt',
        JobApplicationResource::class => 'CV mới chưa xử lý',
        AllianceSourceResource::class => 'Sheet đang không đọc được',
        RedirectResource::class => 'Cần chọn trang thay thế',
    ];

    /** @return list<array{nhan: string, so: string, mo_ta: ?string, mau: string, url: string}> */
    public function viec(): array
    {
        $ds = [];
        foreach (self::NGUON as $r) {
            if (! $r::canViewAny()) {
                continue;
            }
            $so = $r::getNavigationBadge();
            if ($so === null || $so === '' || $so === '0') {
                continue;
            }
            $mau = $r::getNavigationBadgeColor();
            $ds[] = [
                'nhan' => $r::getNavigationLabel(),
                'so' => $so,
                'mo_ta' => self::MO_TA[$r] ?? $r::getNavigationBadgeTooltip(),
                'mau' => is_string($mau) && in_array($mau, ['danger', 'warning', 'success', 'info'], true) ? $mau : 'warning',
                'url' => $r::getUrl('index'),
            ];
        }

        return $ds;
    }
}
