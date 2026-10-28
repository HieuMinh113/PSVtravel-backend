{{-- File in voucher — dựng bởi App\Services\Voucher\InVoucher. Đơn vị mm, gốc toạ độ = góc trên trái trang --}}
@php
    $mm = fn ($v) => round($v, 2).'mm';
    $t = $tran; // tràn lề (0 khi in thành phẩm / A4)
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>{{ $ten }} — PSV Travel</title>
<style>
    @page { margin: 0; size: {{ $kho[0] }}mm {{ $kho[1] }}mm; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; }
    .trang { position: relative; width: {{ $kho[0] }}mm; height: {{ $kho[1] }}mm; overflow: hidden; page-break-after: always; }
    .trang.cuoi { page-break-after: auto; }
    .the { position: absolute; overflow: hidden; }
    .nen { position: absolute; }
    .o { position: absolute; background: #fff; border-radius: 3mm; }
    .o div { position: absolute; left: 0; width: 100%; text-align: center; white-space: nowrap; }
    .ma { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; color: #111827; }
    .chan { font-weight: bold; color: #4b5563; }
    .cat { position: absolute; background: #000; }
    .ghi { position: absolute; left: 0; width: 100%; text-align: center; font-size: 7pt; color: #9ca3af; }
</style>
</head>
<body>
@foreach ($trang as $p)
<div class="trang{{ $loop->last ? ' cuoi' : '' }}">
    @foreach ($p['the'] as $th)
        @php
            $nen = $mat[$th['mat']];
            $rong = 155 + 2 * $t;
            $cao = 100 + 2 * $t;
        @endphp
        <div class="the" style="left: {{ $mm($th['x'] - $t) }}; top: {{ $mm($th['y'] - $t) }}; width: {{ $mm($rong) }}; height: {{ $mm($cao) }};">
            <img class="nen" src="{{ $nen['duong'] }}" style="left: {{ $mm($nen['boc']['trai'] + $t) }}; top: {{ $mm($nen['boc']['tren'] + $t) }}; width: {{ $mm($nen['boc']['rong']) }}; height: {{ $mm($nen['boc']['cao']) }};">
            @if ($v = $th['v'])
                <div class="o" style="left: {{ $mm($o['trai'] + $t) }}; top: {{ $mm($o['tren'] + $t) }}; width: {{ $mm($o['rong']) }}; height: {{ $mm($o['cao']) }};">
                    <img src="{{ $v['qr'] }}" style="position: absolute; left: {{ $mm($o['qrTrai']) }}; top: {{ $mm($o['qrTren']) }}; width: {{ $mm($o['qr']) }}; height: {{ $mm($o['qr']) }};">
                    <div class="ma" style="top: {{ $mm($o['maTren']) }}; height: 3.4mm; line-height: 3.4mm; font-size: {{ round(\App\Services\Voucher\InVoucher::coChuMa($o, $v['ma']) * 2.835, 2) }}pt;">{{ $v['ma'] }}</div>
                    @if ($v['vach'])
                        <img src="{{ $v['vach'] }}" style="position: absolute; left: 2mm; top: {{ $mm($o['vachTren']) }}; width: {{ $mm($o['rong'] - 4) }}; height: 6.4mm;">
                    @endif
                    <div class="chan" style="top: {{ $mm($o['chanTren']) }}; height: 2.6mm; line-height: 2.6mm; font-size: 4.5pt;">Quét để kiểm tra · Số {{ str_pad((string) $v['seri'], 4, '0', STR_PAD_LEFT) }}</div>
                </div>
            @endif
        </div>
        @if ($kieu === 'a4')
            {{-- Vạch cắt: cách mép cắt 2 mm, dài 5 mm, ở 4 góc --}}
            @foreach ([[$th['x'], $th['y']], [$th['x'] + 155, $th['y']], [$th['x'], $th['y'] + 100], [$th['x'] + 155, $th['y'] + 100]] as [$gx, $gy])
                <div class="cat" style="left: {{ $mm($gx < $th['x'] + 1 ? $gx - 7 : $gx + 2) }}; top: {{ $mm($gy - 0.1) }}; width: 5mm; height: 0.2mm;"></div>
                <div class="cat" style="left: {{ $mm($gx - 0.1) }}; top: {{ $mm($gy < $th['y'] + 1 ? $gy - 7 : $gy + 2) }}; width: 0.2mm; height: 5mm;"></div>
            @endforeach
        @endif
    @endforeach
    @if ($p['ghi'])
        <div class="ghi" style="top: 284mm;">PSV Travel · {{ $ten }} · {{ $p['ghi'] }} · In 2 mặt, lật cạnh dài, tỉ lệ 100% (không “vừa trang”)</div>
    @endif
</div>
@endforeach
</body>
</html>
