<x-mail-layout
    tieuDe="Đã nhận đơn đặt tour {{ $booking->booking_code }}"
    phuDe="Đã nhận đơn đặt tour của bạn"
    nhan="Chờ xác nhận"
    xemTruoc="Mã đơn {{ $booking->booking_code }} — giữ lại để tra cứu hành trình."
    :hotline="$hotline"
    :tenCongTy="$tenCongTy"
>

<p style="margin:0 0 14px; font-size:16px; font-weight:600; color:#0b2440;">
    Cảm ơn {{ $booking->customer_name }}!
</p>
<p style="margin:0 0 24px; font-size:14px; line-height:1.75; color:rgba(15,42,66,.75);">
    Chúng tôi đã nhận được đơn đặt tour của bạn. Nhân viên tư vấn sẽ gọi lại
    trong vòng <strong style="color:#0b2440;">15 phút</strong> (trong giờ làm việc)
    để xác nhận chỗ và hướng dẫn thanh toán.
</p>

{{-- Mã đơn: thứ khách cần giữ lại, cho nổi bật nhất trong thư --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#eef6fb; border:1px dashed #0169A9; border-radius:14px; margin:0 0 24px;">
    <tr><td align="center" style="padding:20px 16px;">
        <p style="margin:0 0 8px; font-size:11.5px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:rgba(1,105,169,.75);">
            Mã đơn của bạn
        </p>
        <p style="margin:0; font-size:26px; font-weight:700; letter-spacing:2px; color:#0169A9; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">
            {{ $booking->booking_code }}
        </p>
        <p style="margin:10px 0 0; font-size:12px; line-height:1.6; color:rgba(15,42,66,.6);">
            Giữ lại mã này để tra cứu đơn bất cứ lúc nào,<br>không cần tạo tài khoản.
        </p>
    </td></tr>
</table>

@include('emails.partials.booking-detail', ['booking' => $booking])

{{-- Nút tra cứu --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
    <tr><td align="center">
        <a href="{{ $linkTraCuu }}"
           style="display:inline-block; background:#EA580C; color:#ffffff; font-size:14.5px; font-weight:700;
                  text-decoration:none; padding:14px 32px; border-radius:999px;">
            Tra cứu đơn của tôi
        </a>
        <p style="margin:12px 0 0; font-size:12px; color:rgba(15,42,66,.55);">
            Cần mã đơn và số điện thoại bạn vừa dùng để đặt.
        </p>
    </td></tr>
</table>

<p style="margin:26px 0 0; padding-top:18px; border-top:1px solid #eaf2f7; font-size:12.5px; line-height:1.7; color:rgba(15,42,66,.6);">
    Đơn này <strong style="color:#0b2440;">chưa được xác nhận chỗ</strong>. Chỗ chỉ được giữ
    sau khi nhân viên gọi xác nhận với bạn. Muốn thay đổi hoặc huỷ, vui lòng gọi hotline
    và đọc mã đơn ở trên.
</p>

</x-mail-layout>
