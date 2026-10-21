<?php

declare(strict_types=1);

namespace Modules\Guide\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Guide\Models\GuideSpot;

/**
 * Điểm trên bản đồ đi theo quyền của THÀNH PHỐ ("Bản đồ: Thành phố & điểm"):
 * xem được thành phố là xem được điểm, sửa được thành phố là thêm / sửa / xoá
 * được điểm của nó.
 *
 * Điểm không có trang riêng trong menu nên màn hình phân quyền vai trò không
 * hiện quyền "GuideSpot" để tích — trước đây cấp đủ quyền Cẩm nang + Bản đồ cho
 * một vai trò mà mục "Điểm trên bản đồ" vẫn biến mất. Quyền GuideSpot cũ (cấp
 * bằng migration) vẫn dùng được.
 */
class GuideSpotPolicy
{
    use HandlesAuthorization;

    /** Quyền riêng của điểm, hoặc quyền tương ứng của thành phố. */
    private function duoc(AuthUser $authUser, string $quyen, string $quyenThanhPho = 'Update:GuideCity'): bool
    {
        return $authUser->can($quyen.':GuideSpot') || $authUser->can($quyenThanhPho);
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'ViewAny', 'ViewAny:GuideCity');
    }

    public function view(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'View', 'View:GuideCity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'Create');
    }

    public function update(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'Update');
    }

    public function delete(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'Delete');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'DeleteAny');
    }

    public function restore(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'Restore');
    }

    public function forceDelete(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'ForceDelete');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'ForceDeleteAny');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'RestoreAny');
    }

    public function replicate(AuthUser $authUser, GuideSpot $guideSpot): bool
    {
        return $this->duoc($authUser, 'Replicate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->duoc($authUser, 'Reorder');
    }
}
