@php
    $tien = fn ($v) => number_format((int) $v, 0, ',', '.').' đ';
    $tenCongTy = $cauHinh['legal_name'] ?? $cauHinh['company_name'] ?? 'PSV Travel';
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Phiếu xác nhận đặt tour {{ $don->booking_code }}</title>
<style>
    /* DejaVu Sans có sẵn trong dompdf và đủ dấu tiếng Việt */
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11.5px; color: #0b2440; margin: 0; }
    .dau { border-bottom: 2px solid #0169A9; padding-bottom: 10px; margin-bottom: 14px; }
    .cong-ty { font-size: 15px; font-weight: bold; color: #0169A9; }
    .nho { font-size: 10px; color: #4a5b6b; line-height: 1.5; }
    h1 { text-align: center; font-size: 17px; margin: 6px 0 2px; letter-spacing: .5px; }
    .ma { text-align: center; font-size: 11px; color: #4a5b6b; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    .bang td { border: 1px solid #c9dbe7; padding: 6px 8px; vertical-align: top; }
    .bang td.nhan { width: 38%; background: #f1f7fb; color: #33475b; }
    .muc { font-size: 12px; font-weight: bold; color: #0169A9; margin: 14px 0 6px; text-transform: uppercase; }
    .dam { font-weight: bold; }
    .noi-bat td { background: #fff4e8; font-weight: bold; color: #b45309; }
    .ky td { text-align: center; padding-top: 24px; }
    .ghi-chu { border: 1px dashed #c9dbe7; padding: 8px 10px; white-space: pre-line; line-height: 1.6; }
</style>
</head>
<body>

<div class="dau">
    <div class="cong-ty">{{ $tenCongTy }}</div>
    <div class="nho">
        @if (!empty($cauHinh['address'])){{ $cauHinh['address'] }}<br>@endif
        @if (!empty($cauHinh['hotline']))Hotline: {{ $cauHinh['hotline'] }}@endif
        @if (!empty($cauHinh['email'])) · Email: {{ $cauHinh['email'] }}@endif
        @if (!empty($cauHinh['license_number']))<br>Giấy phép lữ hành quốc tế số: {{ $cauHinh['license_number'] }}@endif
        @if (!empty($cauHinh['tax_code'])) · MST: {{ $cauHinh['tax_code'] }}@endif
    </div>
</div>

<h1>PHIẾU XÁC NHẬN ĐẶT TOUR</h1>
<div class="ma">Mã đơn: <strong>{{ $don->booking_code }}</strong> · Ngày lập: {{ now()->format('d/m/Y') }}</div>

<div class="muc">Thông tin khách hàng</div>
<table class="bang">
    <tr><td class="nhan">Họ tên</td><td class="dam">{{ $don->customer_name }}</td></tr>
    <tr><td class="nhan">Điện thoại</td><td>{{ $don->customer_phone }}</td></tr>
    @if ($don->customer_email)<tr><td class="nhan">Email</td><td>{{ $don->customer_email }}</td></tr>@endif
</table>

<div class="muc">Thông tin tour</div>
<table class="bang">
    <tr><td class="nhan">Tour</td><td class="dam">{{ $don->tour?->name ?? '—' }}</td></tr>
    <tr><td class="nhan">Ngày khởi hành</td><td>{{ $don->departure?->start_date?->format('d/m/Y') ?? 'Nhân viên sẽ xác nhận' }}</td></tr>
    @if ($ngayVe)<tr><td class="nhan">Ngày về (dự kiến)</td><td>{{ $ngayVe->format('d/m/Y') }}</td></tr>@endif
    <tr><td class="nhan">Số khách</td><td>{{ $don->adults }} người lớn{{ $don->children > 0 ? ', '.$don->children.' trẻ em' : '' }}</td></tr>
</table>

<div class="muc">Chi phí</div>
<table class="bang">
    <tr><td class="nhan">Đơn giá người lớn</td><td>{{ $tien($don->unit_price_adult) }} × {{ $don->adults }}</td></tr>
    @if ($don->children > 0)
        <tr><td class="nhan">Đơn giá trẻ em</td><td>{{ $don->unit_price_child === null ? 'Nhân viên sẽ báo giá' : $tien($don->unit_price_child).' × '.$don->children }}</td></tr>
    @endif
    <tr><td class="nhan">Tổng tiền</td><td class="dam">{{ $tien($don->total_price) }}{{ $don->choBaoGiaTreEm() ? ' (chưa gồm trẻ em)' : '' }}</td></tr>
    @if ($don->deposit_amount)
        <tr class="noi-bat"><td class="nhan">Tiền cọc ({{ \Modules\Booking\Models\Booking::phanTram($don->deposit_percent) }})</td><td>{{ $tien($don->deposit_amount) }}</td></tr>
    @endif
    <tr><td class="nhan">Đã thanh toán</td><td>{{ $tien($daThu) }}</td></tr>
    <tr class="noi-bat"><td class="nhan">Còn lại cần thanh toán</td><td>{{ $tien($conLai) }}</td></tr>
    @if ($don->remind_on && $conLai > 0)
        <tr><td class="nhan">Hạn thanh toán phần còn lại</td><td class="dam">Trước ngày {{ $don->remind_on->format('d/m/Y') }}</td></tr>
    @endif
</table>

@if (!empty($cauHinh['bank_transfer']))
    <div class="muc">Thông tin chuyển khoản</div>
    <div class="ghi-chu">{{ trim($cauHinh['bank_transfer']) }}
Nội dung chuyển khoản: {{ $don->booking_code }}</div>
@endif

@if ($don->note)
    <div class="muc">Ghi chú</div>
    <div class="ghi-chu">{{ $don->note }}</div>
@endif

<table class="ky">
    <tr>
        <td width="50%"><strong>Khách hàng</strong><br><span class="nho">(ký, ghi rõ họ tên)</span></td>
        <td width="50%"><strong>{{ $tenCongTy }}</strong><br><span class="nho">(ký, đóng dấu)</span></td>
    </tr>
</table>

</body>
</html>
