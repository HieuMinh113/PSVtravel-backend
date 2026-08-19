<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Sinh và xác thực mã OTP gửi qua email.
 *
 * Nguyên tắc bảo mật áp dụng ở đây:
 *  - Mã KHÔNG bao giờ lưu dạng gốc, chỉ lưu bản băm (lộ CSDL cũng vô dụng).
 *  - So khớp bằng Hash::check — an toàn trước tấn công đo thời gian.
 *  - Sai quá số lần cho phép thì huỷ mã, bắt gửi lại từ đầu.
 *  - Có thời gian chờ giữa 2 lần gửi và trần số lần gửi mỗi giờ.
 *  - Mỗi lần gửi mã mới sẽ vô hiệu hoá toàn bộ mã cũ cùng mục đích.
 */
class OtpService
{
    public const MUC_DICH_DANG_KY = 'register';
    public const MUC_DICH_QUEN_MK = 'reset_password';

    private const HET_HAN_PHUT = 10;      // mã sống 10 phút
    private const TOI_DA_NHAP_SAI = 5;    // sai 5 lần là huỷ mã
    private const CHO_GUI_LAI_GIAY = 60;  // phải chờ 60 giây mới xin mã mới
    private const TRAN_MOI_GIO = 5;       // tối đa 5 mã / giờ / email

    /**
     * Sinh mã mới và gửi email. Ném ValidationException nếu vi phạm giới hạn chống spam.
     */
    public function gui(string $email, string $purpose, ?string $ip = null, ?string $tenNguoiNhan = null): void
    {
        $this->kiemTraGioiHan($email, $purpose);

        // Vô hiệu hoá mã cũ — mỗi thời điểm chỉ có duy nhất 1 mã còn hiệu lực
        OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // random_int dùng nguồn ngẫu nhiên an toàn cho mật mã (không dùng rand/mt_rand)
        $ma = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($ma),
            'attempts' => 0,
            'ip_address' => $ip,
            'expires_at' => now()->addMinutes(self::HET_HAN_PHUT),
        ]);

        Mail::to($email)->send(new OtpMail($ma, self::HET_HAN_PHUT, $tenNguoiNhan));
    }

    /**
     * Kiểm tra mã người dùng nhập. Ném ValidationException kèm thông báo tiếng Việt nếu sai.
     */
    public function xacThuc(string $email, string $purpose, string $ma): void
    {
        $otp = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->conHieuLuc()
            ->latest('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không tồn tại hoặc đã hết hạn. Vui lòng bấm gửi lại mã.',
            ]);
        }

        if ($otp->attempts >= self::TOI_DA_NHAP_SAI) {
            $otp->update(['consumed_at' => now()]); // huỷ luôn, bắt xin mã mới
            throw ValidationException::withMessages([
                'code' => 'Bạn đã nhập sai quá nhiều lần. Mã này đã bị huỷ, vui lòng gửi lại mã mới.',
            ]);
        }

        if (! Hash::check($ma, $otp->code_hash)) {
            $otp->increment('attempts');
            $conLai = self::TOI_DA_NHAP_SAI - $otp->attempts;

            throw ValidationException::withMessages([
                'code' => "Mã xác thực không đúng. Bạn còn {$conLai} lần thử.",
            ]);
        }

        // Đúng — đánh dấu đã dùng để không thể dùng lại lần thứ hai
        $otp->update(['consumed_at' => now()]);
    }

    private function kiemTraGioiHan(string $email, string $purpose): void
    {
        $ganNhat = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($ganNhat && $ganNhat->created_at->diffInSeconds(now()) < self::CHO_GUI_LAI_GIAY) {
            $conLai = self::CHO_GUI_LAI_GIAY - (int) $ganNhat->created_at->diffInSeconds(now());

            throw ValidationException::withMessages([
                'email' => "Vui lòng chờ {$conLai} giây nữa rồi gửi lại mã.",
            ]);
        }

        $soLanTrongGio = OtpCode::where('email', $email)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($soLanTrongGio >= self::TRAN_MOI_GIO) {
            throw ValidationException::withMessages([
                'email' => 'Bạn đã yêu cầu mã quá nhiều lần. Vui lòng thử lại sau 1 giờ.',
            ]);
        }
    }
}
