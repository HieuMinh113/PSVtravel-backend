<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mã xác thực PSV Travel</title>
</head>
<body style="margin:0; padding:0; background:#eef6fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef6fb; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(10,42,69,0.10);">

                    <tr>
                        <td style="background:linear-gradient(90deg,#0169A9,#0FA98D); padding:24px 28px; color:#ffffff;">
                            <p style="margin:0; font-size:20px; font-weight:700;">PSV Travel</p>
                            <p style="margin:4px 0 0; font-size:13px; opacity:.85;">Xác thực tài khoản của bạn</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 12px; font-size:15px; color:#0b2440;">
                                Xin chào{{ $tenNguoiNhan ? ' '.$tenNguoiNhan : '' }},
                            </p>
                            <p style="margin:0 0 20px; font-size:14px; line-height:1.7; color:rgba(15,42,66,.75);">
                                Đây là mã xác thực để hoàn tất đăng ký tài khoản PSV Travel.
                                Mã có hiệu lực trong <strong>{{ $soPhut }} phút</strong>.
                            </p>

                            <div style="text-align:center; margin:24px 0;">
                                <div style="display:inline-block; background:#eef6fb; border:1px dashed #0169A9; border-radius:12px; padding:16px 28px;">
                                    <span style="font-size:32px; font-weight:700; letter-spacing:10px; color:#0169A9;">{{ $ma }}</span>
                                </div>
                            </div>

                            <p style="margin:0 0 8px; font-size:13px; line-height:1.7; color:rgba(15,42,66,.65);">
                                Vui lòng <strong>không chia sẻ mã này</strong> cho bất kỳ ai, kể cả người tự xưng là nhân viên PSV Travel.
                            </p>
                            <p style="margin:0; font-size:13px; line-height:1.7; color:rgba(15,42,66,.65);">
                                Nếu bạn không thực hiện yêu cầu này, hãy bỏ qua email — tài khoản sẽ không được kích hoạt.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f7fbfd; padding:18px 28px; border-top:1px solid #e3eef5;">
                            <p style="margin:0; font-size:12px; color:rgba(15,42,66,.5);">
                                Email được gửi tự động, vui lòng không trả lời.<br>
                                &copy; {{ date('Y') }} PSV Travel.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
