<x-mail-layout
    tieuDe="Đã nhận hồ sơ ứng tuyển"
    phuDe="Cảm ơn bạn đã ứng tuyển"
    nhan="Chờ bộ phận nhân sự liên hệ"
    xemTruoc="PSV Travel đã nhận hồ sơ ứng tuyển vị trí {{ $hoSo->job_title }}."
    :hotline="$hotline"
    :tenCongTy="$tenCongTy"
>

<p style="margin:0 0 14px; font-size:16px; font-weight:600; color:#0b2440;">
    Chào {{ $hoSo->full_name }},
</p>
<p style="margin:0 0 20px; font-size:14px; line-height:1.75; color:rgba(15,42,66,.75);">
    PSV Travel đã nhận hồ sơ ứng tuyển vị trí <strong style="color:#0b2440;">{{ $hoSo->job_title }}</strong> của bạn.
    Bộ phận nhân sự sẽ xem CV và liên hệ qua số điện thoại <strong style="color:#0b2440;">{{ $hoSo->phone }}</strong>
    hoặc email này nếu hồ sơ phù hợp.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background:#eef6fb; border-radius:14px; margin:0 0 24px;">
    <tr><td style="padding:16px 18px; font-size:13.5px; line-height:1.8; color:rgba(15,42,66,.8);">
        <strong style="color:#0b2440;">Hồ sơ đã gửi</strong><br>
        CV: {{ $hoSo->cv_name ?: 'CV.pdf' }}<br>
        @if ($hoSo->portfolio_path)
            Portfolio: {{ $hoSo->portfolio_name ?: 'Portfolio.pdf' }}<br>
        @endif
        @if ($hoSo->portfolio_url)
            Link portfolio: {{ $hoSo->portfolio_url }}<br>
        @endif
    </td></tr>
</table>

<p style="margin:0; font-size:12.5px; line-height:1.7; color:rgba(15,42,66,.6);">
    Thông tin và CV của bạn chỉ dùng cho việc tuyển dụng tại PSV Travel. Muốn rút hồ sơ hoặc cập nhật CV,
    bạn gọi hotline bên dưới (thư này gửi tự động, không nhận thư trả lời).
</p>

</x-mail-layout>
