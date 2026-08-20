<x-mail-layout
    tieuDe="Đã xác nhận chỗ — đơn {{ $booking->booking_code }}"
    phuDe="Chỗ của bạn đã được giữ"
    nhan="Đã xác nhận"
    xemTruoc="Đơn {{ $booking->booking_code }} đã được xác nhận. Hẹn gặp bạn trong hành trình sắp tới."
    :hotline="$hotline"
    :tenCongTy="$tenCongTy"
>

{{-- Dải xác nhận màu xanh ngọc — khác hẳn mail "đã nhận đơn" để khách phân biệt ngay --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#e8f7f3; border-left:4px solid #0FA98D; border-radius:12px; margin:0 0 24px;">
    <tr><td style="padding:16px 18px;">
        <p style="margin:0 0 4px; font-size:15px; font-weight:700; color:#0a6b5c;">
            Chỗ của bạn đã được giữ
        </p>
        <p style="margin:0; font-size:13.5px; line-height:1.7; color:rgba(10,90,78,.85);">
            Đơn <strong>{{ $booking->booking_code }}</strong> đã được xác nhận.
            Hẹn gặp {{ $booking->customer_name }} trong hành trình sắp tới.
        </p>
    </td></tr>
</table>

@include('emails.partials.booking-detail', ['booking' => $booking])

<p style="margin:24px 0 10px; font-size:11.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:rgba(15,42,66,.45);">
    Trước ngày đi cần chuẩn bị
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    @foreach ($luuY as $y)
    <tr>
        <td width="20" style="padding:5px 0; font-size:14px; color:#0FA98D; vertical-align:top;">&bull;</td>
        <td style="padding:5px 0; font-size:13.5px; line-height:1.7; color:rgba(15,42,66,.75);">{{ $y }}</td>
    </tr>
    @endforeach
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">
    <tr><td align="center">
        <a href="{{ $linkTraCuu }}"
           style="display:inline-block; background:#0169A9; color:#ffffff; font-size:14.5px; font-weight:700;
                  text-decoration:none; padding:14px 32px; border-radius:999px;">
            Xem lại đơn của tôi
        </a>
    </td></tr>
</table>

</x-mail-layout>
