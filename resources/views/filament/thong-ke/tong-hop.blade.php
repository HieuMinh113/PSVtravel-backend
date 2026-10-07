@php
    $tien = fn ($v) => number_format($v, 0, ',', '.') . 'đ';
    $o = 'padding:8px 10px;border-bottom:1px solid rgba(127,127,127,.2);white-space:nowrap';
@endphp
<section class="fi-section" style="padding:16px;border-radius:12px;border:1px solid rgba(127,127,127,.25)">
    <div style="font-weight:600;font-size:1rem;margin-bottom:4px">
        Tổng hợp {{ $thang ? 'tháng ' . $thang : 'mọi tháng' }}{{ $tatCa ? '' : ' — đơn của bạn' }}
    </div>
    <div style="font-size:.8rem;opacity:.7;margin-bottom:12px">
        Tính theo ngày xác nhận đơn và người phụ trách. Tiền chỉ tính khoản kế toán đã duyệt. Đơn huỷ không tính doanh số.
    </div>
    @if ($dong->isEmpty())
        <div style="opacity:.7">Chưa có đơn nào được chốt trong tháng này.</div>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                <thead>
                    <tr style="text-align:right;opacity:.75">
                        @foreach (\App\Filament\Pages\ThongKeDoanhSo::COT_TONG_HOP as $k => $nhan)
                            <th style="{{ $o }};{{ $k === 'ten' ? 'text-align:left' : '' }}">{{ $nhan }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dong as $d)
                        <tr style="text-align:right;{{ $d['tong'] ? 'font-weight:700' : '' }}">
                            <td style="{{ $o }};text-align:left">{{ $d['ten'] }}</td>
                            <td style="{{ $o }}">{{ $d['so_don'] }}</td>
                            <td style="{{ $o }}">{{ $d['so_khach'] }}</td>
                            <td style="{{ $o }}">{{ $tien($d['doanh_so']) }}</td>
                            <td style="{{ $o }}">{{ $tien($d['da_thu']) }}</td>
                            <td style="{{ $o }};{{ $d['con_no'] ? 'color:#dc2626' : '' }}">{{ $tien($d['con_no']) }}</td>
                            <td style="{{ $o }}">{{ $d['chua_coc'] }}</td>
                            <td style="{{ $o }}">{{ $d['dang_coc'] }}</td>
                            <td style="{{ $o }};color:#16a34a">{{ $d['da_thu_du'] }}</td>
                            <td style="{{ $o }}">{{ $d['hoan_thanh'] }}</td>
                            <td style="{{ $o }}">{{ $d['huy'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
