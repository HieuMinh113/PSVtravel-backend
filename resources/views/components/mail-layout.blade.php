@props([
    'tieuDe' => 'PSV Travel',
    'phuDe' => 'Đồng hành cùng mọi hành trình',
    'nhan' => null,
    'xemTruoc' => '',
    'hotline' => '1900 1177',
    'tenCongTy' => 'PSV Travel',
])
{{-- Khung chung cho mọi email của PSV Travel.
     Dùng bảng + style nội tuyến vì Gmail/Outlook bỏ hết CSS ngoài.
     Màu chủ đạo lấy đúng từ website: xanh biển #0169A9 → xanh ngọc #0FA98D --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $tieuDe }}</title>
</head>
<body style="margin:0; padding:0; background:#eef6fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; color:#0b2440;">

{{-- Dòng xem trước hiện trong danh sách hộp thư, ẩn khỏi nội dung mail --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
    {{ $xemTruoc }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef6fb; padding:32px 16px;">
<tr><td align="center">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width:560px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 10px 34px rgba(10,42,69,0.12);">

        {{-- Đầu thư --}}
        <tr>
            <td style="background:#0169A9; background-image:linear-gradient(120deg,#0169A9 0%,#0B87C4 55%,#0FA98D 100%); padding:26px 30px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>
                            <p style="margin:0; font-size:21px; font-weight:700; color:#ffffff; letter-spacing:-.2px;">
                                PSV<span style="color:#8FE6D2;">Travel</span>
                            </p>
                            <p style="margin:5px 0 0; font-size:13px; color:rgba(255,255,255,.82);">
                                {{ $phuDe }}
                            </p>
                        </td>
                        @isset($nhan)
                        <td align="right" style="vertical-align:top;">
                            <span style="display:inline-block; background:rgba(255,255,255,.18); color:#ffffff; font-size:11px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; padding:6px 12px; border-radius:999px;">
                                {{ $nhan }}
                            </span>
                        </td>
                        @endisset
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Nội dung riêng của từng loại mail --}}
        <tr>
            <td style="padding:30px;">
                {{ $slot }}
            </td>
        </tr>

        {{-- Chân thư --}}
        <tr>
            <td style="background:#f7fbfd; padding:20px 30px; border-top:1px solid #e3eef5;">
                <p style="margin:0 0 6px; font-size:12px; line-height:1.7; color:rgba(15,42,66,.62);">
                    Cần hỗ trợ? Gọi <a href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}"
                       style="color:#0169A9; font-weight:600; text-decoration:none;">{{ $hotline }}</a>
                    — trực 24/7, kể cả ngày lễ.
                </p>
                <p style="margin:0; font-size:11.5px; line-height:1.7; color:rgba(15,42,66,.45);">
                    Email được gửi tự động, vui lòng không trả lời thư này.<br>
                    &copy; {{ date('Y') }} {{ $tenCongTy }}.
                </p>
            </td>
        </tr>

    </table>

</td></tr>
</table>
</body>
</html>
