<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Danh sách giấy tờ xin visa {{ $mau->country }}</title>
<style>
    /* DejaVu Sans có sẵn trong dompdf và đủ dấu tiếng Việt */
    * { font-family: "DejaVu Sans", sans-serif; }
    @page { margin: 26px 36px 34px; }
    body { font-size: 11px; color: #0b2440; margin: 0; }
    .dau-anh img { width: 100%; }
    .cong-ty { font-size: 15px; font-weight: bold; color: #0169A9; border-bottom: 2px solid #0169A9; padding-bottom: 8px; }
    h1 { text-align: center; font-size: 16px; margin: 12px 0 2px; letter-spacing: .4px; }
    .mo-ta { text-align: center; font-size: 10.5px; color: #4a5b6b; margin-bottom: 10px; }
    .dien { margin: 6px 0 10px; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0169A9; color: #fff; font-size: 10.5px; padding: 5px 6px; border: 1px solid #0169A9; }
    td { border: 1px solid #c9dbe7; padding: 4px 6px; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .nhom td { background: #dcebf5; font-weight: bold; text-transform: uppercase; font-size: 10.5px; }
    .stt { width: 28px; text-align: center; }
    .giay { font-weight: bold; width: 44%; }
    .ghi-chu { font-size: 10px; color: #33475b; white-space: pre-line; }
    .o { width: 34px; text-align: center; }
    .o span { display: inline-block; width: 11px; height: 11px; border: 1px solid #33475b; }
    .luu-y { margin-top: 12px; border: 1px dashed #c9dbe7; padding: 6px 10px; white-space: pre-line; line-height: 1.5; }
    .lien-he { margin-top: 10px; font-size: 9.5px; color: #4a5b6b; font-style: italic; }
</style>
</head>
<body>
@if (!empty($anhDau))
    <div class="dau-anh"><img src="{{ $anhDau }}" alt="{{ $tenCongTy }}"></div>
@else
    <div class="cong-ty">{{ $tenCongTy }}</div>
@endif

<h1>DANH SÁCH GIẤY TỜ XIN VISA {{ mb_strtoupper($mau->country) }}</h1>
@if ($moTa)<div class="mo-ta">{{ $moTa }}</div>@endif
<div class="dien">Họ tên khách: ……………………………………………… &nbsp;&nbsp; Ngày nhận: ……/……/………</div>

<table>
    <thead><tr><th class="stt">STT</th><th>Giấy tờ cần chuẩn bị</th><th>Ghi chú</th><th class="o">Đã có</th></tr></thead>
    <tbody>
    @php $stt = 0; @endphp
    @foreach ($nhom as $tenNhom => $dsGiay)
        @if ($tenNhom !== '')
            <tr class="nhom"><td colspan="4">{{ $tenNhom }}</td></tr>
        @endif
        @foreach ($dsGiay as $g)
            <tr>
                <td class="stt">{{ ++$stt }}</td>
                <td class="giay">{{ $g['ten'] }}</td>
                <td class="ghi-chu">{{ $g['ghi_chu'] }}</td>
                <td class="o"><span></span></td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

@if (filled($mau->note))
    <div class="luu-y"><strong>Lưu ý:</strong>
{{ trim($mau->note) }}</div>
@endif
<div class="lien-he">{{ $lienHe }}</div>
</body>
</html>
