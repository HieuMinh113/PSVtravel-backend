{{-- Bảng thông tin đơn, dùng chung cho mail gửi khách và mail báo nội bộ --}}
@php
    $ngayDi = $booking->departure?->start_date;
    $soNgay = (int) ($booking->tour?->duration_days ?? 0);
    $ngayVe = $ngayDi && $soNgay > 0 ? $ngayDi->copy()->addDays($soNgay - 1) : null;

    $dong = function (string $nhan, ?string $giaTri, bool $dam = false) {
        if (! $giaTri) {
            return '';
        }
        $mau = $dam ? '#0169A9' : '#0b2440';
        $co = $dam ? '16px' : '13.5px';
        $canNang = $dam ? '700' : '600';

        return '<tr>
            <td style="padding:9px 0; font-size:13px; color:rgba(15,42,66,.6); white-space:nowrap;">'.$nhan.'</td>
            <td align="right" style="padding:9px 0 9px 14px; font-size:'.$co.'; font-weight:'.$canNang.'; color:'.$mau.';">'.$giaTri.'</td>
        </tr>';
    };
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border:1px solid #e3eef5; border-radius:14px;">
    <tr><td style="padding:6px 20px 14px;">

        <p style="margin:16px 0 4px; font-size:11.5px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:rgba(15,42,66,.45);">
            Hành trình
        </p>
        <p style="margin:0 0 6px; font-size:16px; font-weight:700; line-height:1.45; color:#0b2440;">
            {{ $booking->tour?->name ?? 'Tour đã đặt' }}
        </p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="border-top:1px solid #eaf2f7; margin-top:8px;">
            {!! $dong('Ngày khởi hành', $ngayDi?->format('d/m/Y')) !!}
            {!! $dong('Ngày về (dự kiến)', $ngayVe?->format('d/m/Y')) !!}
            {!! $dong('Số khách', $booking->adults.' người lớn'.($booking->children > 0 ? ', '.$booking->children.' trẻ em' : '')) !!}
            {!! $dong('Người liên hệ', $booking->customer_name.' — '.$booking->customer_phone) !!}
            {!! $dong('Tổng tiền', number_format((int) $booking->total_price, 0, ',', '.').'đ', true) !!}
        </table>

        @if ($booking->note)
            <p style="margin:14px 0 0; padding:12px 14px; background:#f7fbfd; border-radius:10px; font-size:12.5px; line-height:1.7; color:rgba(15,42,66,.7);">
                <strong style="color:#0b2440;">Ghi chú của khách:</strong><br>{{ $booking->note }}
            </p>
        @endif

    </td></tr>
</table>
