<x-mail-layout
    tieuDe="Đơn mới {{ $booking->booking_code }}"
    phuDe="Thông báo nội bộ"
    nhan="Đơn mới"
    xemTruoc="{{ $booking->customer_name }} — {{ $booking->tour?->name }} — {{ number_format((int) $booking->total_price, 0, ',', '.') }}đ"
    :hotline="$hotline"
    :tenCongTy="$tenCongTy"
>

<p style="margin:0 0 6px; font-size:16px; font-weight:600; color:#0b2440;">
    Có đơn đặt tour mới cần xử lý
</p>
<p style="margin:0 0 22px; font-size:13.5px; line-height:1.7; color:rgba(15,42,66,.7);">
    Đơn <strong style="color:#0169A9;">{{ $booking->booking_code }}</strong>
    lúc {{ $booking->created_at?->format('H:i, d/m/Y') }}.
    Chỗ <strong>chưa</strong> bị trừ — cần bấm Xác nhận trong trang quản trị.
</p>

@include('emails.partials.booking-detail', ['booking' => $booking])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
    <tr><td align="center">
        <a href="{{ $linkAdmin }}"
           style="display:inline-block; background:#0169A9; color:#ffffff; font-size:14.5px; font-weight:700;
                  text-decoration:none; padding:14px 32px; border-radius:999px;">
            Mở đơn trong trang quản trị
        </a>
    </td></tr>
</table>

</x-mail-layout>
