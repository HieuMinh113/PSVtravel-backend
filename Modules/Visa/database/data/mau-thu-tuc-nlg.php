<?php

/**
 * Mẫu checklist 17 nước — chép từ bộ "FILE THỦ TỤC HỒ SƠ NOLOGO" của PSV
 * (thư mục thu-tuc/ cạnh file này là chính các file thủ tục đó, gắn vào mẫu
 * làm "File mẫu" để nhân viên tải gửi khách).
 *
 * Mỗi giấy tờ: [nhóm, tên, ghi chú, chỉ cho đối tượng]. "Chỉ cho" để trống =
 * mọi đối tượng; có ghi thì hồ sơ chọn đối tượng nào chỉ chép giấy của đối
 * tượng đó (vd. nhân viên không nhận dòng "Giấy phép kinh doanh").
 *
 * Nước có phần "Trường hợp công tác / thăm thân" thì tách thành mẫu riêng cho
 * từng mục đích: phần chung + phần riêng của mục đích đó.
 */
$NV = ['nhan_vien', 'nha_nuoc'];
$DN = ['chu_doanh_nghiep'];
$HKD = ['ho_kinh_doanh'];
$HT = ['huu_tri'];
$TD = ['tu_do'];
$HS = ['hoc_sinh'];
$TE = ['tre_em'];

$g = fn (string $nhom, string $ten, ?string $ghiChu = null, array $chiCho = []) => array_filter([
    'nhom' => $nhom, 'ten' => $ten, 'ghi_chu' => $ghiChu, 'chi_cho' => $chiCho ?: null,
], fn ($v) => $v !== null);

$CN = 'Hồ sơ cá nhân';
$CV = 'Chứng minh công việc';
$TC = 'Chứng minh tài chính';
$CT = 'Trường hợp công tác';
$TT = 'Trường hợp thăm thân';
$TK = 'Tờ khai';

/** Phần thăm thân dùng chung (Úc, Anh, Pháp, Đức, Thụy Sĩ, Hà Lan…). */
$thamThan = fn (string $nuoc, string $giayMoi, bool $choO = true) => array_values(array_filter([
    $g($TT, $giayMoi, "người mời mang quốc tịch {$nuoc} hoặc có giấy phép cư trú vô thời hạn tại {$nuoc}"),
    $g($TT, 'Hộ chiếu / thẻ công dân của người mời', "bản sao; kèm giấy phép cư trú nếu người mời không mang quốc tịch {$nuoc}"),
    $choO ? $g($TT, 'Chứng minh chỗ ở của người mời', 'nếu ở nhà người mời: hợp đồng thuê nhà, giấy chứng nhận sở hữu nhà, hóa đơn điện nước gần nhất') : null,
    $g($TT, 'Chứng minh quan hệ hai bên', 'thân nhân: khai sinh, đăng ký kết hôn, hộ khẩu; bạn bè: ảnh chụp chung, email, tin nhắn'),
]));

$congTac = fn (string $nuoc) => [
    $g($CT, 'Quyết định cử đi công tác'),
    $g($CT, 'Thư mời của công ty đối tác', "công ty đối tác tại {$nuoc}"),
];

/** Một nước có phần chung + công tác + thăm thân → 3 mẫu. */
$baMucDich = function (string $nuoc, array $chung, array $congTac, array $thamThan, string $luuY, array $tep) {
    $ra = [];
    foreach (['du_lich' => ['Du lịch', []], 'cong_tac' => ['Công tác', $congTac], 'tham_than' => ['Thăm thân', $thamThan]] as $mucDich => [$nhan, $them]) {
        if ($mucDich !== 'du_lich' && ! $them) {
            continue;
        }
        $ra[] = [
            'name' => "{$nuoc} — {$nhan}",
            'country' => $nuoc, 'purpose' => $mucDich, 'profile' => null,
            'items' => [...$chung, ...$them],
            'note' => $luuY,
            'tep' => $tep,
        ];
    }

    return $ra;
};

$saoY3Thang = 'Tất cả giấy tờ sao y công chứng không quá 3 tháng. Mang toàn bộ bản chính để viên chức lãnh sự đối chiếu khi cần.';

$mau = [];

// ---------------------------------------------------------------- NHẬT BẢN
$mau[] = [
    'name' => 'Nhật Bản — Du lịch', 'country' => 'Nhật Bản', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'bản gốc + hộ chiếu cũ (nếu có); ký tên trên hộ chiếu'),
        $g($CN, 'Ảnh 3.5x4.5', '2 tấm, nền trắng'),
        $g($CN, 'Căn cước', 'photo 2 mặt trên cùng 1 tờ A4, không cắt nhỏ'),
        $g($CN, 'Giấy khai sinh', 'nếu đi chung với con'),
        $g($CN, 'Giấy đăng ký kết hôn', 'nếu đi chung với vợ / chồng'),
        $g($CN, 'Giấy đồng ý ủy quyền cho người dẫn đi', 'trẻ dưới 18 tuổi không đi cùng ba / mẹ', $TE),
        $g($TC, 'Xác nhận số dư sổ tiết kiệm', 'tối thiểu 100 triệu; bản gốc'),
        $g($TK, 'Tờ khai thông tin xin visa', 'theo phiếu thông tin đính kèm'),
        $g($CV, 'Hợp đồng lao động hoặc quyết định bổ nhiệm', null, $NV),
        $g($CV, 'Sao kê lương 6 tháng gần nhất', 'bản gốc', $NV),
        $g($CV, 'Bảo hiểm xã hội', 'chụp app VssID', $NV),
        $g($CV, 'Giấy phép kinh doanh', null, $DN),
        $g($CV, 'Thuế 3 tháng gần nhất', null, [...$DN, ...$HKD]),
        $g($CV, 'Sao kê tài khoản công ty 3 tháng gần nhất', 'bản gốc', $DN),
        $g($CV, 'Thẻ hưu trí hoặc quyết định về hưu', null, $HT),
        $g($CV, 'Sao kê lương hưu 6 tháng gần nhất', 'nhận qua ngân hàng; nhận tiền mặt thì biên lai nhận tiền', $HT),
        $g($CV, 'Hình ảnh chứng minh công việc', 'fanpage, Zalo, nhà, shop…', $TD),
    ],
    'note' => 'Giấy tờ photo không cần công chứng, trừ xác nhận số dư và sao kê phải bản gốc (photo 1 mặt A4, không cắt nhỏ). Xét duyệt 5–7 ngày làm việc kể từ ngày nộp lãnh sự (có thể lâu hơn). Visa hạn 3 tháng, mỗi lần nhập cảnh tối đa 15 ngày.',
    'tep' => ['nhat-ban-thu-tuc.pdf' => 'Thủ tục xin visa Nhật.pdf', 'nhat-ban-phieu-thong-tin.docx' => 'Phiếu thông tin xin visa Nhật Bản.docx'],
];

// ---------------------------------------------------------------- HÀN QUỐC
$mau[] = [
    'name' => 'Hàn Quốc — Du lịch', 'country' => 'Hàn Quốc', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'bản gốc, còn hạn trên 6 tháng; kèm hộ chiếu cũ nếu có'),
        $g($CN, 'Ảnh 3.5x4.5', '1 tấm, nền trắng'),
        $g($CN, 'CT08 + CCCD', 'nếu nơi sinh từ Đà Nẵng trở ra Bắc; bản gốc hoặc online'),
        $g($CN, 'CT07 + CCCD', 'nếu có hộ khẩu TP.HCM trên 1 năm; bản gốc hoặc online'),
        $g($CN, 'Giấy đăng ký kết hôn', 'nếu đi cặp vợ chồng; photo công chứng'),
        $g($CN, 'Giấy khai sinh', 'nếu có con đi cùng; photo công chứng'),
        $g($CN, 'Căn cước', 'photo 2 mặt trên 1 trang A4, không cần sao y'),
        $g($TK, 'Tờ khai thông tin cá nhân xin visa', 'công ty du lịch cung cấp'),
        $g($CV, 'Giấy xác nhận nhân viên', 'ghi rõ chức vụ, thời gian bắt đầu làm việc', ['nhan_vien']),
        $g($CV, 'Sao kê lương 6 tháng gần nhất', 'tài khoản có nội dung nhận lương', $NV),
        $g($CV, 'Bảo hiểm xã hội', 'chụp màn hình VssID quá trình tham gia; không có BHXH thì tờ tường trình có mộc công ty + chữ ký giám đốc', $NV),
        $g($CV, 'Quyết định bổ nhiệm + quyết định nâng lương', 'nếu làm nhà nước, đã vào biên chế', ['nha_nuoc']),
        $g($CV, 'Quyết định về hưu hoặc thẻ hưu trí', null, $HT),
        $g($CV, 'Sao kê lương hưu 6 tháng gần nhất', null, $HT),
        $g($CV, 'Giấy phép đăng ký kinh doanh', null, $DN),
        $g($CV, 'Giấy phép đăng ký hộ kinh doanh', null, $HKD),
        $g($CV, 'Giấy đăng ký mã số thuế', null, $HKD),
        $g($CV, 'Biên lai nộp thuế GTGT 1 năm gần nhất', null, [...$DN, ...$HKD]),
        $g($CV, 'Sao kê tài khoản công ty 6 tháng gần nhất', null, [...$DN, ...$HKD]),
        $g($CV, 'Hình chụp đương đơn tại hộ kinh doanh', 'có bảng hiệu', $HKD),
        $g($TC, 'Xác nhận số dư sổ tiết kiệm', 'kèm sổ gốc; không quá 14 ngày'),
        $g($TC, 'Giấy tờ tài sản khác', 'chứng khoán, bất động sản, ô tô… nếu có'),
    ],
    'note' => 'Giấy tờ sao y công chứng không quá 3 tháng, trên giấy A4 một mặt. CCCD chỉ cần photo 2 mặt trên 1 trang A4, không cần sao y. Xác nhận số dư sổ tiết kiệm không quá 14 ngày.',
    'tep' => ['han-quoc-thu-tuc.pdf' => 'Thủ tục visa Hàn Quốc.pdf'],
];

// ---------------------------------------------------------------- ĐÀI LOAN
$mau[] = [
    'name' => 'Đài Loan — Du lịch', 'country' => 'Đài Loan', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'bản gốc, có ký tên, còn hạn 6 tháng trở lên; kèm hộ chiếu cũ nếu có'),
        $g($CN, 'Ảnh 4x6', '4 tấm, nền trắng, chụp trong vòng 3–6 tháng'),
        $g($CN, 'Căn cước', 'bản sao công chứng'),
        $g($CV, 'Hợp đồng lao động', 'bản sao công chứng', $NV),
        $g($CV, 'Đơn xin nghỉ phép', 'bản gốc, có dấu và chữ ký của chủ quản công ty', $NV),
        $g($CV, 'Sao kê lương 3 tháng', null, $NV),
        $g($CV, 'BHXH, BHYT', 'cung cấp tài khoản + mật khẩu VssID', $NV),
        $g($CV, 'Giấy phép đăng ký kinh doanh', 'bản sao công chứng', $DN),
        $g($CV, 'Giấy nộp tiền vào ngân sách / biên lai thuế 6 tháng gần nhất', 'bản sao công chứng; thuế thu nhập doanh nghiệp hoặc xuất nhập khẩu', $DN),
        $g($CV, 'Sao kê tài khoản công ty / cá nhân', 'thể hiện nội dung thanh toán thuế', $DN),
        $g($CV, 'Giấy tờ hưu trí', 'quyết định nghỉ hưu, sổ lương hưu, thẻ hưu trí, sao kê lương hưu', $HT),
        $g($CV, 'Thẻ học sinh / sinh viên', null, [...$HS, ...$TE]),
        $g($CV, 'Giấy xác nhận học sinh / sinh viên', 'bản gốc', [...$HS, ...$TE]),
        $g($CV, 'Đơn xin nghỉ học', 'nếu thời gian đi không phải ngày nghỉ', [...$HS, ...$TE]),
        $g($CV, 'Giấy đồng ý của cha mẹ', 'nếu không đi cùng cha mẹ', [...$HS, ...$TE]),
        $g($CV, 'Chứng minh thu nhập của cha hoặc mẹ', 'người bảo lãnh', [...$HS, ...$TE]),
        $g($TC, 'Sổ tiết kiệm', 'từ 100 triệu trở lên, kỳ hạn 3 tháng trở lên; bản gốc'),
        $g($TC, 'Xác nhận số dư sổ tiết kiệm', 'bản gốc; làm không quá 10 ngày trước ngày nộp'),
    ],
    'note' => 'Hồ sơ bản photo công chứng + bản gốc để đối chiếu. Xét duyệt 7 ngày làm việc (gấp 4 ngày, đóng thêm phí). Visa hạn 90 ngày, lưu trú tối đa 14 ngày mỗi lần. Lãnh sự có thể yêu cầu bổ sung; tùy hồ sơ công ty có thể yêu cầu ký quỹ. Trường hợp khác: liên hệ.',
    'tep' => ['dai-loan-thu-tuc.pdf' => 'Thủ tục visa Đài Loan.pdf'],
];

// ---------------------------------------------------------------- HONG KONG
$treEmChauAu = [
    $g($CN, 'Giấy đồng ý của bố / mẹ cho trẻ ra nước ngoài', 'đi cùng bố hoặc mẹ: giấy đồng ý của người còn lại (không cần nếu người đi cùng có quyền nuôi dưỡng duy nhất); bố mẹ không đi cùng: đồng ý của cả bố và mẹ', $TE),
    $g($CN, 'Giấy khai sinh của trẻ', null, $TE),
    $g($CN, 'Bản sao CCCD của bố mẹ', null, $TE),
    $g($CN, 'Giấy xác nhận học sinh, kết quả học tập', 'nếu có', $TE),
];
$mau[] = [
    'name' => 'Hong Kong — Du lịch', 'country' => 'Hong Kong', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'bản chính, kèm hộ chiếu cũ nếu có; chụp lại tất cả dấu xuất nhập cảnh'),
        $g($CN, 'Ảnh 4x6', '2 tấm, nền trắng, mặt chiếm 70%, không che tai và trán; lấy file mềm'),
        $g($CN, 'Giấy chứng nhận tình trạng hôn nhân', 'nếu vợ chồng đi cùng nhau'),
        $g($CN, 'Giấy khai sinh', 'nếu đi cùng con'),
        ...$treEmChauAu,
        $g($CV, 'Giấy phép kinh doanh', null, $DN),
        $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
        $g($CV, 'Sao kê tài khoản công ty 3 tháng gần nhất', null, $DN),
        $g($CV, 'Giấy xác nhận việc làm + đồng ý nghỉ phép', 'ghi họ tên, chức danh, ngày bắt đầu làm việc, mức lương; giấy tiêu đề công ty, có ngày, chữ ký, con dấu; kèm phiếu lương', $NV),
        $g($CV, 'Sao kê tài khoản nhận lương 3 tháng gần nhất', 'nhận lương tiền mặt: đơn xác nhận lương có chữ ký giám đốc + dấu mộc', $NV),
        $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y, nếu có', $NV),
        $g($CV, 'Bảo hiểm xã hội', 'nếu có', $NV),
        $g($CV, 'Thẻ hưu trí / sổ hưu trí / thẻ nhận lương hưu bưu điện', null, $HT),
        $g($CV, 'Sao kê lương hưu 3 tháng gần nhất', 'nếu nhận qua ngân hàng', $HT),
        $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
        $g($CV, 'Đơn xin nghỉ học', null, $HS),
        $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
        $g($TC, 'Sổ tiết kiệm + xác nhận số dư', 'tối thiểu 100 triệu; xác nhận tiếng Anh hoặc Việt–Anh'),
        $g($TC, 'Sao kê tài khoản ngân hàng', 'nếu có'),
    ],
    'note' => 'Tất cả hồ sơ công chứng không quá 3 tháng.',
    'tep' => ['hong-kong-thu-tuc.pdf' => 'Thủ tục visa Hong Kong.pdf'],
];

// ---------------------------------------------------------------- MACAO
$mau[] = [
    'name' => 'Macao — Du lịch', 'country' => 'Macao', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'bản gốc, còn hạn 6 tháng trở lên, còn ít nhất 2 trang trống liền kề'),
        $g($CN, 'Hộ chiếu cũ', 'nếu từng được cấp visa Macao'),
        $g($CN, 'Ảnh 4x6', '2 tấm, nền trắng, rõ ngũ quan, tóc vén sau tai, không che trán; chụp trong 6 tháng'),
        $g($CN, 'Căn cước gắn chip', 'sao y công chứng (CMND: sao y + dịch thuật)'),
        $g($CN, 'Hộ khẩu hoặc CT07 / CT08', 'sao y công chứng + dịch thuật; CT07/CT08 nộp bản chính, không trả lại'),
        $g($CN, 'Giấy đăng ký kết hôn', 'nếu vợ chồng cùng đi; sao y trên 1 tờ A4'),
        $g($CN, 'Giấy khai sinh', 'nếu bố mẹ dẫn con đi; sao y + dịch thuật'),
        $g($CN, 'Booking khách sạn', 'nếu có'),
        $g($CN, 'Booking vé máy bay', 'nếu có'),
        $g($TC, 'Xác nhận số dư song ngữ Anh–Việt', 'bản chính, làm trong 7 ngày gần nhất; từ 50 triệu theo hướng dẫn mới (bản cũ ghi 100 triệu); ngân hàng đóng mộc, ký tên, giáp lai'),
        $g($TC, 'Giấy tờ chứng minh tài chính khác', 'nếu có; sao y + dịch thuật'),
        $g($CV, 'Xác nhận việc làm', 'bản gốc song ngữ Anh–Việt hoặc Trung–Việt', $NV),
        $g($CV, 'Đơn nghỉ phép đi du lịch', 'bản gốc song ngữ Anh–Việt hoặc Trung–Việt', $NV),
        $g($CV, 'Hợp đồng lao động', 'nếu có; sao y + dịch thuật', $NV),
        $g($CV, 'Bảo hiểm xã hội', 'nếu có; sao y + dịch thuật', $NV),
        $g($CV, 'Giấy phép kinh doanh', 'sao y + dịch thuật', $DN),
        $g($CN, 'Giấy cam kết dẫn con đi – về đúng thời gian', 'bản tiếng Anh', $TE),
        $g($CN, 'CCCD của ba và mẹ', 'sao y công chứng', $TE),
        $g($CN, 'Giấy ủy quyền dẫn bé đi', 'nếu chỉ đi với ba hoặc mẹ hoặc người thân: phường xác nhận, sao y, dịch thuật', $TE),
        $g($CN, 'CCCD người thân dẫn bé đi', 'nếu người thân dẫn đi; sao y', $TE),
        $g($CN, 'Thẻ học sinh', 'nếu có; sao y + dịch thuật', [...$TE, ...$HS]),
        $g($CN, 'Chứng minh công việc và tài chính của ba hoặc mẹ', 'sao y + dịch thuật', $TE),
    ],
    'note' => 'In / photo trên giấy A4 (không dùng ảnh chụp để in). Giấy tờ không phải tiếng Anh hoặc Trung phải kèm bản dịch công chứng; sao y trong 6 tháng. Xử lý khoảng 1–1,5 tháng; khách lên lăn tay. Trung tâm TP.HCM nhận khách có nơi sinh / hộ khẩu / tạm trú ở miền Nam (xem file hướng dẫn). Khách cung cấp: SĐT, ngày dự kiến đi (cách ngày nộp 1–1,5 tháng), tên công ty / hộ kinh doanh, chức vụ.',
    'tep' => ['macao-huong-dan.pdf' => 'Hướng dẫn hồ sơ visa Macao (mới).pdf', 'macao-thu-tuc.pdf' => 'Hồ sơ nộp visa Macao.pdf'],
];

// ---------------------------------------------------------------- ÚC
$mau = [...$mau, ...$baMucDich('Úc', [
    $g($CN, 'Hộ chiếu', 'bản chính + bản sao, còn hạn trên 6 tháng; kèm hộ chiếu cũ nếu có'),
    $g($CN, 'Giấy khai sinh', 'bản sao công chứng'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang; có thể thay bằng CT07, CT08'),
    $g($CN, 'Căn cước', 'bản sao công chứng'),
    $g($CN, 'Giấy xác nhận tình trạng hôn nhân hoặc đăng ký kết hôn', 'sao y công chứng'),
    $g($CN, 'Giấy chấp thuận của cha hoặc mẹ', 'có chứng thực chữ ký; người nộp hồ sơ là bố mẹ, người giám hộ hoặc người được ủy thác', $TE),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Sao kê tài khoản công ty 3 tháng gần nhất', null, $DN),
    $g($CV, 'Sao kê lương 3 tháng gần nhất', 'hoặc bảng lương', $NV),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y, nếu có', $NV),
    $g($CV, 'Đơn xin nghỉ phép', null, $NV),
    $g($CV, 'Bảo hiểm xã hội', 'nếu có', $NV),
    $g($CV, 'Quyết định về hưu hoặc thẻ hưu trí', null, $HT),
    $g($CV, 'Sao kê lương hưu 3 tháng gần nhất', 'hoặc biên lai nhận lương hưu bưu điện', $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Xác nhận số dư sổ tiết kiệm / tài khoản cá nhân', 'tối thiểu 200 triệu'),
], $congTac('Úc'), $thamThan('Úc', 'Giấy mời'),
    $saoY3Thang.' Nộp tại trung tâm tiếp nhận thị thực 94–96 Nguyễn Du, Phường Bến Nghé, Quận 1.',
    ['uc-thu-tuc.pdf' => 'Thủ tục visa Úc.pdf'])];

// ---------------------------------------------------------------- NEW ZEALAND
$mau = [...$mau, ...$baMucDich('New Zealand', [
    $g($CN, 'Hộ chiếu', 'bản chính, kèm hộ chiếu cũ; phải có nơi sinh hoặc bị chú nơi sinh, còn ít nhất 2 trang trắng, còn hạn trên 6 tháng'),
    $g($CN, 'Ảnh 3.5x4.5', '2 tấm, nền trắng, mặt chiếm 70–80%, không che tai và trán'),
    $g($CN, 'Căn cước', 'sao y công chứng trên 1 mặt A4, không cắt nhỏ'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang; có thể thay bằng CT07'),
    $g($CN, 'Sơ yếu lý lịch', 'có xác nhận của phường, xã'),
    $g($CN, 'Giấy đăng ký kết hôn', 'nếu vợ chồng đi cùng nhau'),
    $g($CN, 'Quyết định ly hôn / xác nhận tình trạng hôn nhân / giấy chứng tử', 'nếu có'),
    $g($CN, 'Giấy khai sinh', 'nếu đi cùng con'),
    ...$treEmChauAu,
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Sao kê tài khoản công ty 6 tháng gần nhất', null, $DN),
    $g($CV, 'Giấy xác nhận việc làm', 'ghi họ tên, chức danh, ngày bắt đầu làm việc, mức lương', $NV),
    $g($CV, 'Đơn xin nghỉ phép', 'giấy tiêu đề công ty, có ngày, chữ ký, con dấu; kèm phiếu lương', $NV),
    $g($CV, 'Sao kê tài khoản nhận lương 3 tháng gần nhất', 'nhận tiền mặt: đơn xác nhận lương có chữ ký giám đốc + dấu mộc', $NV),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y, nếu có', $NV),
    $g($CV, 'Bảo hiểm xã hội', 'nếu có', $NV),
    $g($CV, 'Thẻ hưu trí / sổ hưu trí / thẻ nhận lương hưu bưu điện', null, $HT),
    $g($CV, 'Sao kê lương hưu 3 tháng gần nhất', 'nếu nhận qua ngân hàng', $HT),
    $g($CV, 'Giấy tờ tài sản bổ sung', 'người nghỉ hưu cần chứng minh thêm: xe hơi, nhà cửa, đất đai, thẻ tín dụng…', $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Đơn xin nghỉ học', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Sổ tiết kiệm + xác nhận số dư', 'tối thiểu 200 triệu; xác nhận tiếng Anh hoặc Việt–Anh'),
    $g($TC, 'Sao kê tài khoản ngân hàng', 'nếu có'),
], [
    $g($CT, 'Thư mời của công ty đối tác', 'công ty đối tác tại New Zealand'),
    $g($CT, 'Quyết định cử đi công tác'),
    $g($CT, 'Lịch trình công tác'),
    $g($CT, 'Giấy đăng ký kinh doanh + biên lai thuế 3 tháng gần nhất', 'công ty của đương đơn'),
    $g($CT, 'Giấy tờ chứng minh quan hệ hai công ty'),
], [
    $g($TT, 'Thư mời'),
    $g($TT, 'Form bảo lãnh tài chính INZ 1025', 'nếu người mời bảo lãnh tài chính'),
    $g($TT, 'Hộ chiếu của người mời', 'scan trang thông tin'),
    $g($TT, 'Xác nhận công việc của người mời'),
    $g($TT, 'Sao kê tài khoản người mời 3 tháng gần nhất', 'nếu người mời chi trả'),
    $g($TT, 'Chứng minh chỗ ở của người mời', 'nếu ở nhà người mời: hợp đồng thuê nhà, giấy chứng nhận sở hữu nhà, hóa đơn điện nước gần nhất'),
    $g($TT, 'Chứng minh quan hệ hai bên', 'thân nhân: khai sinh, đăng ký kết hôn, hộ khẩu; bạn bè: ảnh chụp chung, email, tin nhắn'),
], 'Tất cả hồ sơ công chứng không quá 3 tháng.', ['new-zealand-thu-tuc.pdf' => 'Thủ tục visa New Zealand.pdf'])];

// ---------------------------------------------------------------- CANADA
$mau = [...$mau, ...$baMucDich('Canada', [
    $g($CN, 'Hộ chiếu', 'có chữ ký, còn hạn trên 6 tháng; kèm hộ chiếu cũ nếu có'),
    $g($CN, 'Ảnh 4x6', '1 ảnh, chụp không quá 3 tháng, phông trắng'),
    $g($CN, 'Căn cước', 'bản sao công chứng'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang; có thể thay bằng CT07, CT08'),
    $g($CN, 'Giấy xác nhận tình trạng hôn nhân', 'nếu độc thân'),
    $g($CN, 'Giấy khai sinh', 'bản sao; không có / thất lạc thì bản tường trình có xác nhận chính quyền địa phương'),
    $g($CN, 'Đơn xin cấp visa'),
    $g($CN, 'Giấy khai sinh của con', 'nếu đi cùng con'),
    $g($CN, 'Giấy đăng ký kết hôn', 'nếu đi chung vợ chồng'),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Xác nhận số dư tài khoản công ty', 'song ngữ', $DN),
    $g($CV, 'Hợp đồng lao động hoặc quyết định bổ nhiệm', null, $NV),
    $g($CV, 'Đơn xin nghỉ phép', 'của công ty', $NV),
    $g($CV, 'Sao kê lương 3 tháng gần nhất', 'hoặc bảng lương 3 tháng (ưu tiên sao kê)', $NV),
    $g($CV, 'Bảo hiểm xã hội', 'nếu có', $NV),
    $g($CV, 'Thẻ hưu trí / giấy xác nhận hưu trí', null, $HT),
    $g($CV, 'Sao kê lương hưu 3 tháng gần nhất', 'hoặc biên lai nhận lương hưu bưu điện', $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Đơn xin nghỉ học', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe, cổ phần, cổ phiếu… photo sao y công chứng'),
    $g($TC, 'Xác nhận số dư tài khoản ngân hàng', 'sổ tiết kiệm ít nhất 300 triệu mỗi đương đơn'),
], $congTac('Canada'), [
    $g($TT, 'Thẻ công dân / thường trú Canada của người bảo lãnh', 'bản sao'),
    $g($TT, 'Thư mời của người bảo lãnh', 'nêu rõ thời gian, điều kiện, trách nhiệm, mối quan hệ với đương đơn — theo mẫu'),
    $g($TT, 'Notice of Assessment (thuế thu nhập) 1 năm gần nhất', 'của người tài trợ và vợ/chồng; không có thì kèm thư giải thích'),
    $g($TT, 'Xác nhận số dư ngân hàng của người tài trợ', 'và vợ/chồng người tài trợ'),
    $g($TT, 'Xác nhận việc làm của người tài trợ', 'và vợ/chồng người tài trợ'),
    $g($TT, 'Giấy khai sinh của con', 'chứng minh mối quan hệ'),
], 'Thời gian xét duyệt ít nhất 60 ngày làm việc. Tất cả giấy tờ chứng thực không quá 3 tháng.',
    ['canada-thu-tuc.pdf' => 'Thủ tục visa Canada.pdf', 'canada-thong-tin-khai-form.docx' => 'Thông tin cần thiết để khai form visa Canada.docx'])];

// ---------------------------------------------------------------- ANH
$mau = [...$mau, ...$baMucDich('Anh', [
    $g($CN, 'Hộ chiếu', 'sao công chứng trang thông tin + mọi trang có dấu hải quan / visa; còn hạn trên 6 tháng, có chữ ký; kèm hộ chiếu cũ'),
    $g($CN, 'Ảnh 4x6', '2 tấm, nền trắng, mặt chiếm 70%, không che tai và trán; chụp không quá 6 tháng'),
    $g($CN, 'Giấy khai sinh', 'bản sao công chứng'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang (kể cả trang trắng)'),
    $g($CN, 'Căn cước', 'bản sao công chứng'),
    $g($CN, 'Giấy chứng nhận tình trạng hôn nhân', 'sao y công chứng'),
    $g($CV, 'Giấy đăng ký doanh nghiệp', 'bản sao công chứng', $DN),
    $g($CV, 'Thông báo thuế thu nhập cá nhân / doanh nghiệp', 'năm tài chính trước đó', $DN),
    $g($CV, 'Sao kê tài khoản công ty', null, $DN),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y công chứng', $NV),
    $g($CV, 'Sao kê lương hoặc bảng lương 3 tháng gần nhất', null, $NV),
    $g($CV, 'Đơn xin nghỉ phép', 'bản gốc', $NV),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Sao kê / xác nhận số dư ngân hàng / sổ tiết kiệm', 'bắt buộc'),
    $g($TC, 'Sao kê thẻ tín dụng', 'cho thấy số dư hiện tại; nếu có'),
], $congTac('Anh'), $thamThan('Anh', 'Giấy mời'),
    'Tất cả giấy tờ sao y công chứng không quá 3 tháng, trên giấy A4. Nộp tại trung tâm tiếp nhận thị thực tầng 5 tòa nhà Resco, 94–96 Nguyễn Du, Quận 1; mang bản chính để đối chiếu.',
    ['anh-thu-tuc.pdf' => 'Thủ tục visa Anh.pdf'])];

// ---------------------------------------------------------------- PHÁP
$mau = [...$mau, ...$baMucDich('Pháp', [
    $g($CN, 'Hộ chiếu', 'bản chính, còn hạn trên 6 tháng, có chữ ký; kèm hộ chiếu cũ nếu có'),
    $g($CN, 'Căn cước', 'bản sao công chứng, photo 2 mặt trên một mặt A4'),
    $g($CN, 'Ảnh 3.5x4.5', '2 tấm, nền trắng, không đeo kính, không đeo bông tai, thấy rõ tai và trán'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang; có thể thay bằng CT07 / CT08'),
    $g($CN, 'Giấy chứng nhận tình trạng hôn nhân', 'sao y công chứng'),
    $g($CN, 'Giấy chấp thuận của cha và mẹ', 'có chứng thực chữ ký; người nộp là bố mẹ, người giám hộ hoặc người được ủy thác', $TE),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Sao kê tài khoản công ty 3 tháng gần nhất', 'có giao dịch buôn bán', $DN),
    $g($CV, 'Giấy xác nhận việc làm + đồng ý nghỉ phép đi châu Âu', 'ghi họ tên, chức danh, ngày bắt đầu, mức lương; giấy tiêu đề công ty, có ngày, chữ ký, con dấu; kèm phiếu lương', $NV),
    $g($CV, 'Sao kê lương 3 tháng gần nhất', null, $NV),
    $g($CV, 'Bảo hiểm xã hội', 'nếu có', $NV),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y, nếu có', $NV),
    $g($CV, 'Thẻ hưu trí hoặc giấy xác nhận hưu trí', null, $HT),
    $g($CV, 'Sao kê lương hưu 3 tháng gần nhất', 'hoặc biên lai bưu điện / xác nhận nhận lương hưu tại phường xã', $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Đơn xin nghỉ học', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Sổ tiết kiệm + xác nhận số dư 3 tháng gần nhất', 'tối thiểu 300 triệu; tiếng Anh hoặc Việt–Anh'),
    $g($TC, 'Sao kê tài khoản ngân hàng', 'nếu có'),
], $congTac('Pháp'), $thamThan('Pháp', 'Giấy bảo lãnh nơi ở (Cerfa)', false),
    $saoY3Thang.' Nộp tại trung tâm tiếp nhận thị thực Vincom Đồng Khởi Tower, 72 Lê Thánh Tôn (hay 45A Lý Tự Trọng), Quận 1.',
    ['phap-thu-tuc.pdf' => 'Thủ tục visa Pháp.pdf'])];

// ---------------------------------------------------------------- ĐỨC + THỤY SĨ (cùng bộ giấy tờ)
$ducThuySi = fn (string $nuoc) => [
    $g($CN, 'Đơn xin cấp thị thực', 'giấy tờ sắp theo đúng thứ tự trong danh sách'),
    $g($CN, 'Ảnh 3.5x4.5', '2 ảnh, chụp không quá 3 tháng, mặt chiếm 70–80%'),
    $g($CN, 'Hộ chiếu', 'còn hạn trên 6 tháng, còn ít nhất 2 trang trống'),
    $g($CN, 'Xác nhận thông tin lưu trú CT07 / CT08'),
    $g($CN, 'Căn cước', 'của đương đơn; kèm của vợ/chồng, con cái nếu có'),
    $g($CN, 'Giấy khai sinh', 'của đương đơn + tất cả các con'),
    $g($CN, 'Giấy đăng ký kết hôn'),
    $g($CN, 'Giấy chứng tử', 'nếu đã kết hôn nhưng vợ/chồng đã qua đời'),
    $g($CN, 'Hộ chiếu cũ', 'bằng chứng lưu trú Schengen trước đây; bản gốc'),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'thuế môn bài, thuế GTGT của công ty', $DN),
    $g($CV, 'Sao kê tài khoản công ty 6 tháng gần nhất', null, $DN),
    $g($CV, 'Hợp đồng lao động', 'nêu rõ chức vụ, thời gian làm việc', $NV),
    $g($CV, 'Sao kê tài khoản ngân hàng 3 tháng', 'tài khoản nhận lương chuyển khoản', $NV),
    $g($CV, 'Đơn xin nghỉ phép', 'ghi rõ nghỉ có lương hay không lương', $NV),
    $g($CV, 'Sổ bảo hiểm xã hội + chụp VssID', null, $NV),
    $g($CV, 'Đơn xác nhận bảng lương 3 tháng', 'nếu nhận lương tiền mặt; không quá 14 ngày', $NV),
    $g($CV, 'Chứng nhận trả lương hưu / sao kê lương hưu 3 tháng gần nhất', null, $HT),
    $g($CV, 'Quyết định về hưu', null, $HT),
    $g($CV, 'Giấy cam kết bảo lãnh', 'người lớn tuổi', $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Thẻ học sinh, sinh viên', null, $HS),
    $g($TC, 'Xác nhận số dư sổ tiết kiệm', 'tối thiểu 200 triệu; làm không quá 1 ngày trước ngày nộp'),
    $g($TC, 'Sao kê tài khoản ngân hàng', 'nhận lương chuyển khoản: có thể dùng sao kê lương; làm không quá 1 ngày trước ngày nộp'),
    $g($TC, 'Thư tường trình không có sao kê', 'nếu nhận lương tiền mặt: giải thích vì sao không chứng minh tài chính bằng sao kê'),
    $g($TC, 'Giấy tờ tài sản', 'nộp 3 bản: nhà, đất, xe, cổ phần, cổ phiếu…'),
];
$luuYDuc = 'Mỗi loại giấy tờ mang theo bản gốc. Trung tâm chỉ nhận sao kê ngân hàng, xác nhận số dư sổ tiết kiệm làm không quá 1 ngày tính đến ngày nộp hồ sơ, lấy dấu vân tay. '.$saoY3Thang.' Nộp tại trung tâm tiếp nhận thị thực REE Tower, 9 Đoàn Văn Bơ, P.12, Quận 4.';
foreach (['Đức' => 'duc-thu-tuc.pdf', 'Thụy Sĩ' => 'thuy-si-thu-tuc.pdf'] as $nuoc => $tep) {
    $mau = [...$mau, ...$baMucDich($nuoc, $ducThuySi($nuoc), $congTac($nuoc),
        $thamThan($nuoc, 'Giấy bảo lãnh của Tòa Thị chính nơi người mời cư trú'),
        $luuYDuc, [$tep => "Thủ tục visa {$nuoc}.pdf"])];
}

// ---------------------------------------------------------------- HÀ LAN
$mau = [...$mau, ...$baMucDich('Hà Lan', [
    $g($CN, 'Hộ chiếu', 'bản chính + bản sao, còn hạn trên 6 tháng, có chữ ký; kèm hộ chiếu cũ nếu có'),
    $g($CN, 'Ảnh 3.5x4.5', '2 tấm, nền trắng, mặt chiếm 70%, không che tai và trán'),
    $g($CN, 'Hộ khẩu', 'sao công chứng tất cả các trang'),
    $g($CN, 'Giấy chứng nhận tình trạng hôn nhân', 'sao y công chứng'),
    $g($CN, 'Bảo hiểm du lịch'),
    $g($CN, 'Booking vé máy bay'),
    $g($CN, 'Đặt phòng khách sạn'),
    $g($CN, 'Chương trình du lịch'),
    $g($CN, 'Giấy chấp thuận của cha và mẹ', 'có chứng thực chữ ký; người nộp là bố mẹ, người giám hộ hoặc người được ủy thác', $TE),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Xác nhận số dư tài khoản công ty', 'song ngữ', $DN),
    $g($CV, 'Giấy xác nhận việc làm + đồng ý nghỉ phép đi châu Âu', 'ghi họ tên, chức danh, ngày bắt đầu, mức lương; giấy tiêu đề công ty, có ngày, chữ ký, con dấu; kèm phiếu lương', $NV),
    $g($CV, 'Bảng lương 3 tháng gần nhất', null, $NV),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm', 'bản sao y, nếu có', $NV),
    $g($CV, 'Xác nhận lương hưu', null, $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Đơn xin nghỉ học', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Sổ tiết kiệm + xác nhận số dư 3 tháng gần nhất', 'tối thiểu 300 triệu; tiếng Anh hoặc Việt–Anh'),
    $g($TC, 'Sao kê tài khoản ngân hàng', 'nếu có'),
], $congTac('Hà Lan'), $thamThan('Hà Lan', 'Giấy bảo lãnh của Tòa Thị chính nơi người mời cư trú'),
    $saoY3Thang.' Nộp tại trung tâm tiếp nhận thị thực REE Tower, 9 Đoàn Văn Bơ, Phường 12, Quận 4. Đối chiếu thêm checklist của đại sứ quán (file đính kèm).',
    ['ha-lan-thu-tuc.pdf' => 'Thủ tục visa Hà Lan.pdf', 'ha-lan-checklist-dai-su-quan.pdf' => 'Checklist đại sứ quán Hà Lan - du lịch, thăm thân.pdf'])];

// ---------------------------------------------------------------- BA LAN (không có phần công tác)
$mau = [...$mau, ...$baMucDich('Ba Lan', [
    $g($CN, 'Hộ chiếu', 'bản gốc + bản sao trang thông tin, trang visa Schengen; còn hạn ít nhất 6 tháng tính từ ngày về, còn ít nhất 2 trang trống, cấp trong vòng 10 năm'),
    $g($CN, 'Hộ chiếu cũ', 'nếu có'),
    $g($CN, 'Ảnh 3.5x4.5', '1 ảnh, chụp không quá 6 tháng, mặt chiếm 70–80%, không kính râm, không đội mũ, miệng khép'),
    $g($CN, 'Căn cước'),
    $g($CN, 'Hộ khẩu / CT07, CT08'),
    $g($CN, 'Giấy khai sinh'),
    $g($CN, 'Giấy đăng ký kết hôn'),
    $g($CN, 'Giấy đồng ý của bố mẹ', 'công chứng; trẻ đi một mình: cả bố và mẹ; đi cùng một phụ huynh: phụ huynh còn lại', $TE),
    $g($CN, 'Bản sao CCCD / hộ chiếu của bố và mẹ', null, $TE),
    $g($CV, 'Giấy phép kinh doanh', null, $DN),
    $g($CV, 'Biên lai nộp thuế 3 tháng gần nhất', 'của công ty', $DN),
    $g($CV, 'Sao kê tài khoản cá nhân 3 tháng', null, $DN),
    $g($CV, 'Sao kê tài khoản công ty 3 tháng', null, $DN),
    $g($CV, 'Hợp đồng lao động / quyết định bổ nhiệm / xác nhận việc làm', 'bản sao y; xác nhận ghi họ tên, chức danh, ngày bắt đầu, mức lương, đồng ý nghỉ phép đi châu Âu; kèm phiếu lương', $NV),
    $g($CV, 'Sao kê tài khoản nhận lương 3 tháng gần nhất', null, $NV),
    $g($CV, 'Đơn xin nghỉ phép', null, $NV),
    $g($CV, 'Xác nhận lương hưu + sao kê tài khoản nhận lương hưu 3 tháng', null, $HT),
    $g($CV, 'Giấy xác nhận học sinh, sinh viên', null, $HS),
    $g($CV, 'Đơn xin nghỉ học', null, $HS),
    $g($TC, 'Giấy tờ tài sản', 'nhà, đất, xe… sao y công chứng'),
    $g($TC, 'Sổ tiết kiệm + xác nhận số dư 3 tháng gần nhất', 'tối thiểu 300 triệu; tiếng Anh hoặc Việt–Anh'),
    $g($TC, 'Sao kê tài khoản ngân hàng', 'nếu có'),
], [], [
    $g($TT, 'Giấy tờ chứng minh quan hệ ruột thịt', 'trích lục khai sinh, trích lục kết hôn'),
    $g($TT, 'Giấy mời đích danh', 'cấp và đăng ký tại Cơ quan Tỉnh nơi người mời cư trú'),
    $g($TT, 'Giấy tờ chứng minh ràng buộc với Việt Nam', 'hợp đồng lao động không thời hạn, giấy tờ sở hữu bất động sản…'),
    $g($TT, 'Xác nhận chi trả / cung cấp chỗ ở của người bảo trợ', 'trên tờ khai quốc gia — nếu được người khác trả chi phí hoặc ở nhà người khác'),
    $g($TT, 'Thư mời / thư bảo lãnh gốc', 'nếu có người bảo trợ'),
    $g($TT, 'Hộ chiếu / chứng minh thư của người bảo trợ', 'bản copy'),
    $g($TT, 'Giấy phép cư trú của người bảo trợ', 'nếu người bảo trợ / chủ nhà là người nước ngoài'),
    $g($TT, 'Sao kê ngân hàng của người bảo trợ 3 tháng gần nhất'),
], 'Trẻ em dưới 18 tuổi: cần ít nhất một phụ huynh đi cùng ký đơn xin thị thực; phụ huynh còn lại cung cấp giấy đồng ý công chứng.',
    ['ba-lan-thu-tuc.pdf' => 'Hướng dẫn thủ tục visa Ba Lan.pdf'])];

// ---------------------------------------------------------------- ẤN ĐỘ
$anDoChung = [
    $g($TK, 'Form khai', 'theo mẫu đính kèm'),
    $g($CN, 'Hộ chiếu', 'scan mặt thông tin'),
    $g($CN, 'Ảnh 5x5', 'file ảnh, chụp không quá 3 tháng, không trùng ảnh các visa khác'),
];
$tepAnDo = ['an-do-thu-tuc.pdf' => 'Thủ tục visa Ấn Độ.pdf', 'an-do-form-khai.doc' => 'Form khai thông tin visa Ấn Độ.doc'];
$mau[] = [
    'name' => 'Ấn Độ — Du lịch', 'country' => 'Ấn Độ', 'purpose' => 'du_lich', 'profile' => null,
    'items' => $anDoChung, 'note' => null, 'tep' => $tepAnDo,
];
$mau[] = [
    'name' => 'Ấn Độ — Công tác', 'country' => 'Ấn Độ', 'purpose' => 'cong_tac', 'profile' => null,
    'items' => [
        ...$anDoChung,
        $g($CT, 'Name card tại công ty đang làm việc'),
        $g($CT, 'Thư cử đi công tác'),
        $g($CT, 'Thư mời của đối tác / công ty mời tại Ấn Độ'),
    ],
    'note' => 'Thời gian xử lý từ 4 ngày làm việc trở lên (không tính thứ 7, chủ nhật; chỉ là dự kiến).',
    'tep' => $tepAnDo,
];

// ---------------------------------------------------------------- DUBAI
$mau[] = [
    'name' => 'Dubai (UAE) — Du lịch', 'country' => 'Dubai (UAE)', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Hộ chiếu', 'scan trang thông tin'),
        $g($CN, 'Hộ chiếu — trang thông tin cuối', 'scan'),
        $g($CN, 'Ảnh 4x6', 'file ảnh, nền trắng, chụp không quá 3 tháng, không trùng ảnh các visa khác'),
    ],
    'note' => 'Visa lưu trú 30 ngày. Xét duyệt 4–5 ngày làm việc (không tính thứ 7, chủ nhật, ngày lễ).',
    'tep' => ['dubai-thu-tuc.pdf' => 'Hồ sơ nộp visa du lịch Dubai.pdf'],
];

// ---------------------------------------------------------------- MỸ
$mau[] = [
    'name' => 'Mỹ — Du lịch', 'country' => 'Mỹ', 'purpose' => 'du_lich', 'profile' => null,
    'items' => [
        $g($CN, 'Ảnh 5x5', 'file ảnh, nền trắng, chụp không quá 3 tháng, không trùng ảnh nào trong hộ chiếu'),
        $g($CN, 'Hộ chiếu', 'mặt thông tin (trang có thông tin và ảnh)'),
        $g($TK, 'Thông tin khai form DS-160', 'khách điền theo mẫu đính kèm'),
    ],
    'note' => 'File của PSV cho Mỹ là phiếu thông tin khai DS-160; giấy tờ mang theo khi phỏng vấn chuyên viên tư vấn theo từng hồ sơ.',
    'tep' => ['my-thong-tin-ds160.pdf' => 'Thông tin khai form DS-160.pdf'],
];

return $mau;
