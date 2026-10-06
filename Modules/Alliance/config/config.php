<?php

return [
    'name' => 'Alliance',

    // Đọc lại mỗi sheet sau bao nhiêu phút. Lịch chạy 5 phút một lần, nên
    // thực tế mỗi sheet được cập nhật sau 10–15 phút.
    'chu_ky_phut' => (int) env('LIEN_MINH_CHU_KY_PHUT', 10),

    // Còn từ chừng này chỗ trở xuống thì báo "sắp hết chỗ".
    'nguong_sap_het' => (int) env('LIEN_MINH_NGUONG_SAP_HET', 3),

    // Cấu hình khai sẵn cho sheet đối tác đã biết (theo mã file Google trong
    // link). Áp dụng khi điều hành thêm sheet đó mà chưa tự chỉnh; chỉnh lại
    // được trong trang sửa sheet.
    'cau_hinh_san' => [
        // Hanvina — tab HCM ghi chú "Đỏ hết chỗ"; tab "BẮC KINH - THƯỢNG HẢI
        // HCM" không có dòng tiêu đề nên khai cột tay.
        '1iS3G9tbkfpE95csaYadKk4PZjY3dYJato1uKXSLLNGw' => [
            'mau_do' => 'theo_chu_thich',
            'cot_tay' => [[
                'tab' => 'BẮC KINH - THƯỢNG HẢI HCM',
                'ten' => 'B', 'thoi_gian' => 'D', 'hang_bay' => 'E', 'ngay_di' => 'F',
                'gia' => 'H', 'hoa_hong' => 'I', 'ghi_chu' => 'J',
            ]],
        ],
    ],
];
