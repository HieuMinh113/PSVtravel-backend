<?php

namespace Modules\Alliance\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tải sheet đối tác về dạng file .xlsx để bộ đọc xử lý.
 *
 * Đối tác gửi 2 kiểu link:
 *  - Google Sheet thật:   docs.google.com/spreadsheets/d/{id}/edit
 *  - File Excel trên Drive (mở bằng Sheets vẫn ra link y hệt kiểu trên, hoặc
 *    drive.google.com/file/d/{id}/view)
 * Thử xuất Google Sheet trước, không được thì tải thẳng file Drive.
 *
 * Chỉ đọc được sheet để chế độ "Bất kỳ ai có đường liên kết đều xem được"
 * (cả 3 sheet mẫu đều vậy). Sheet bị khoá thì Google trả trang đăng nhập
 * thay vì file → báo lỗi rõ ràng cho điều hành.
 */
class TaiSheetLienMinh
{
    /** File lớn hơn mức này thì dừng — sheet chỗ trống không bao giờ to vậy. */
    private const DUNG_LUONG_TOI_DA = 30 * 1024 * 1024;

    public static function maFile(string $url): ?string
    {
        foreach (['#/spreadsheets/d/([a-zA-Z0-9_-]{20,})#', '#/file/d/([a-zA-Z0-9_-]{20,})#', '#[?&]id=([a-zA-Z0-9_-]{20,})#'] as $mau) {
            if (preg_match($mau, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** @return string đường dẫn file .xlsx tạm (bên gọi tự xoá) */
    public function tai(string $url): string
    {
        $ma = self::maFile($url);
        if (! $ma) {
            throw new RuntimeException('Link không phải link Google Sheet / Google Drive.');
        }

        $cacDiaChi = [
            "https://docs.google.com/spreadsheets/d/{$ma}/export?format=xlsx",
            "https://drive.google.com/uc?export=download&id={$ma}",
        ];

        $loiCuoi = null;
        foreach ($cacDiaChi as $diaChi) {
            try {
                $tl = Http::timeout(60)->connectTimeout(15)
                    ->withHeaders(['User-Agent' => 'PSVTravel-LienMinh/1.0'])
                    ->get($diaChi);
            } catch (\Throwable $e) {
                $loiCuoi = 'Không kết nối được tới Google: '.$e->getMessage();
                continue;
            }

            $than = $tl->body();
            if ($tl->successful() && str_starts_with($than, 'PK')) {
                if (strlen($than) > self::DUNG_LUONG_TOI_DA) {
                    throw new RuntimeException('File quá lớn (trên 30MB).');
                }
                $duong = tempnam(sys_get_temp_dir(), 'lien-minh-');
                file_put_contents($duong, $than);

                return $duong;
            }

            $loiCuoi = match (true) {
                in_array($tl->status(), [401, 403], true),
                str_contains($than, 'accounts.google.com'),
                str_contains($than, 'ServiceLogin') => 'Sheet chưa mở quyền xem: nhờ đối tác chọn Chia sẻ → “Bất kỳ ai có đường liên kết” → Người xem.',
                $tl->status() === 404 => 'Không tìm thấy sheet (link sai hoặc đối tác đã xoá).',
                default => "Google trả về mã {$tl->status()}, không phải file Excel.",
            };
        }

        throw new RuntimeException($loiCuoi ?? 'Không tải được sheet.');
    }
}
