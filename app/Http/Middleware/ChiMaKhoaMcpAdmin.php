<?php

namespace App\Http\Middleware;

use App\Mcp\MaKhoaMcp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cửa vào MCP nội bộ (/mcp/noi-bo). Chỉ cho qua khi
 *  - gửi mã khoá MCP qua "Authorization: Bearer …" (không nhận mã đăng nhập
 *    website, không nhận phiên đăng nhập), và
 *  - người giữ mã đang có vai trò Admin (bị hạ quyền thì mã cũ tự hết tác dụng).
 *
 * Tự xác thực thay vì dùng auth:sanctum: thiếu mã thì trả 401 dạng JSON cho
 * trợ lý AI, không chuyển hướng sang trang đăng nhập như trình duyệt.
 */
class ChiMaKhoaMcpAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('sanctum');
        $user = $request->bearerToken() ? Auth::guard('sanctum')->user() : null;

        if (! $user) {
            return $this->loi(401, 'Thiếu hoặc sai mã khoá MCP (gửi "Authorization: Bearer <mã>"; tạo mã ở trang admin → Kết nối AI).')
                ->header('WWW-Authenticate', 'Bearer');
        }
        if (! MaKhoaMcp::hopLe($user->currentAccessToken()) || ! $user->laAdmin()) {
            return $this->loi(403, 'Cần mã khoá MCP của tài khoản Admin (tạo ở trang admin → Kết nối AI).');
        }

        return $next($request);
    }

    private function loi(int $ma, string $thongBao): Response
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => $thongBao]], $ma);
    }
}
