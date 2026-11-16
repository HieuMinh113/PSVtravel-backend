<?php

namespace App\Mcp;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mã khoá cá nhân để trợ lý AI trên máy nhân viên (Claude Code, Claude Desktop…)
 * vào máy chủ MCP nội bộ /mcp/noi-bo. Tạo / thu hồi ở trang admin "Kết nối AI".
 *
 * Mã mang đúng một quyền QUYEN. Mã đăng nhập website (quyền "*") KHÔNG dùng được
 * ở đây: kiểm tra quyền bằng so khớp chính xác, không dùng tokenCan() vì
 * tokenCan() coi "*" là có mọi quyền.
 */
class MaKhoaMcp
{
    public const QUYEN = 'mcp-noi-bo';

    public const TIEN_TO_TEN = 'MCP: ';

    /** Hết hạn sau 180 ngày — lộ mã thì tối đa nửa năm, thu hồi được ngay trong admin */
    public const SO_NGAY_HIEU_LUC = 180;

    /** Tạo mã mới, trả về chuỗi mã (chỉ hiện một lần) */
    public static function tao(User $user, string $tenThietBi): string
    {
        return $user->createToken(
            self::TIEN_TO_TEN.trim($tenThietBi),
            [self::QUYEN],
            now()->addDays(self::SO_NGAY_HIEU_LUC),
        )->plainTextToken;
    }

    public static function cuaNguoi(User $user): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('name', 'like', self::TIEN_TO_TEN.'%');
    }

    public static function hopLe(?object $token): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::QUYEN, (array) $token->abilities, true)
            && (! $token->expires_at || $token->expires_at->isFuture());
    }
}
