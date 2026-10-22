@php
    $tien = fn ($v) => number_format($v, 0, ',', '.') . 'đ';
    $o = 'padding:8px 10px;border-bottom:1px solid rgba(127,127,127,.2);white-space:nowrap';
    $khung = 'padding:16px;border-radius:12px;border:1px solid rgba(127,127,127,.25)';
@endphp
<div style="display:flex;flex-direction:column;gap:16px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <label for="thang" style="font-weight:600">Tháng</label>
        <select id="thang" wire:model.live="thang" style="padding:6px 10px;border-radius:8px;border:1px solid rgba(127,127,127,.35);background:transparent">
            @foreach ($cacThang as $k => $nhan)
                <option value="{{ $k }}">{{ $nhan }}</option>
            @endforeach
        </select>
        <span style="font-size:.8rem;opacity:.7">
            Tính theo ngày khách gửi đơn / yêu cầu. Doanh số chỉ cộng đơn đã xác nhận hoặc hoàn thành.
            "Không rõ" = đơn trước khi có ghi nguồn, hoặc trình duyệt chặn lưu trữ.
        </span>
    </div>

    <section class="fi-section" style="{{ $khung }}">
        <div style="font-weight:600;margin-bottom:10px">Theo kênh</div>
        @if ($theoKenh->count() <= 1)
            <div style="opacity:.7">Chưa có đơn hay yêu cầu nào trong tháng này.</div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                    <thead>
                        <tr style="text-align:right;opacity:.75">
                            <th style="{{ $o }};text-align:left">Kênh</th>
                            <th style="{{ $o }}">Yêu cầu liên hệ / team building</th>
                            <th style="{{ $o }}">Đơn đặt tour</th>
                            <th style="{{ $o }}">Đơn đã chốt</th>
                            <th style="{{ $o }}">Khách đã chốt</th>
                            <th style="{{ $o }}">Doanh số đã chốt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($theoKenh as $d)
                            <tr style="text-align:right;{{ ! empty($d['tong']) ? 'font-weight:700' : '' }}{{ ! empty($d['phu']) ? ';font-style:italic' : '' }}{{ ! empty($d['ai']) ? ';color:#16a34a' : '' }}">
                                <td style="{{ $o }};text-align:left">{{ $d['ten'] }}</td>
                                <td style="{{ $o }}">{{ $d['yeu_cau'] }}</td>
                                <td style="{{ $o }}">{{ $d['don'] }}</td>
                                <td style="{{ $o }}">{{ $d['chot'] }}</td>
                                <td style="{{ $o }}">{{ $d['khach'] }}</td>
                                <td style="{{ $o }}">{{ $tien($d['doanh_so']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="fi-section" style="{{ $khung }}">
        <div style="font-weight:600;margin-bottom:4px">Theo chiến dịch · clip</div>
        <div style="font-size:.8rem;opacity:.7;margin-bottom:10px">
            Chỉ đếm lượt có gắn mã khi dán link: utm_campaign = tên chiến dịch, utm_content = mã clip.
            VD: https://psvtravel.com/tour-nuoc-ngoai?utm_source=tiktok&amp;utm_campaign=tet-2027&amp;utm_content=clip-bac-kinh-01
        </div>
        @if ($theoChienDich->isEmpty())
            <div style="opacity:.7">Tháng này chưa có đơn / yêu cầu nào đến từ link có gắn mã chiến dịch.</div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                    <thead>
                        <tr style="text-align:right;opacity:.75">
                            <th style="{{ $o }};text-align:left">Kênh</th>
                            <th style="{{ $o }};text-align:left">Chiến dịch</th>
                            <th style="{{ $o }};text-align:left">Clip / nội dung</th>
                            <th style="{{ $o }}">Yêu cầu</th>
                            <th style="{{ $o }}">Đơn</th>
                            <th style="{{ $o }}">Đã chốt</th>
                            <th style="{{ $o }}">Doanh số đã chốt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($theoChienDich as $d)
                            <tr style="text-align:right">
                                <td style="{{ $o }};text-align:left">{{ $d['kenh'] }}</td>
                                <td style="{{ $o }};text-align:left">{{ $d['chien_dich'] }}</td>
                                <td style="{{ $o }};text-align:left">{{ $d['clip'] }}</td>
                                <td style="{{ $o }}">{{ $d['yeu_cau'] }}</td>
                                <td style="{{ $o }}">{{ $d['don'] }}</td>
                                <td style="{{ $o }}">{{ $d['chot'] }}</td>
                                <td style="{{ $o }}">{{ $tien($d['doanh_so']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
