<x-mail-layout
    tieuDe="Đã nhận hồ sơ visa {{ $hoSo->code }}"
    phuDe="Đã nhận hồ sơ visa của bạn"
    nhan="Chờ chuyên viên liên hệ"
    xemTruoc="Mã hồ sơ {{ $hoSo->code }} — visa {{ $hoSo->country }}."
    :hotline="$hotline"
    :tenCongTy="$tenCongTy"
>

<p style="margin:0 0 14px; font-size:16px; font-weight:600; color:#0b2440;">
    Cảm ơn {{ $hoSo->full_name }}!
</p>
<p style="margin:0 0 24px; font-size:14px; line-height:1.75; color:rgba(15,42,66,.75);">
    PSV Travel đã nhận hồ sơ xin visa <strong style="color:#0b2440;">{{ $hoSo->country }}</strong> của bạn.
    Chuyên viên visa sẽ gọi lại trong giờ làm việc để kiểm tra giấy tờ và hướng dẫn các bước tiếp theo.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#eef6fb; border:1px dashed #0169A9; border-radius:14px; margin:0 0 24px;">
    <tr><td align="center" style="padding:20px 16px;">
        <p style="margin:0 0 8px; font-size:11.5px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:rgba(1,105,169,.75);">
            Mã hồ sơ của bạn
        </p>
        <p style="margin:0; font-size:26px; font-weight:700; letter-spacing:2px; color:#0169A9; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">
            {{ $hoSo->code }}
        </p>
        <p style="margin:10px 0 0; font-size:12px; line-height:1.6; color:rgba(15,42,66,.6);">
            Đọc mã này khi gọi hotline để được hỗ trợ nhanh.
        </p>
    </td></tr>
</table>

@if (count($giayTo))
<p style="margin:0 0 10px; font-size:14px; font-weight:700; color:#0b2440;">Giấy tờ cần chuẩn bị</p>
<ol style="margin:0 0 8px; padding-left:20px; font-size:13.5px; line-height:1.8; color:rgba(15,42,66,.8);">
    @foreach ($giayTo as $g)
        <li>{{ $g }}</li>
    @endforeach
</ol>
<p style="margin:0 0 24px; font-size:12.5px; line-height:1.7; color:rgba(15,42,66,.6);">
    Danh sách tham khảo theo loại visa bạn chọn — chuyên viên sẽ xác nhận lại cho đúng trường hợp của bạn.
</p>
@endif

@if ($linkTaiKhoan)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 0;">
    <tr><td align="center">
        <a href="{{ $linkTaiKhoan }}"
           style="display:inline-block; background:#EA580C; color:#ffffff; font-size:14.5px; font-weight:700;
                  text-decoration:none; padding:14px 32px; border-radius:999px;">
            Theo dõi hồ sơ của tôi
        </a>
    </td></tr>
</table>
@endif

</x-mail-layout>
