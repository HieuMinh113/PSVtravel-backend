<?php

return [
    'name' => 'Alliance',

    // Đọc lại mỗi sheet sau bao nhiêu phút. Lịch chạy 5 phút một lần, nên
    // thực tế mỗi sheet được cập nhật sau 10–15 phút.
    'chu_ky_phut' => (int) env('LIEN_MINH_CHU_KY_PHUT', 10),

    // Còn từ chừng này chỗ trở xuống thì báo "sắp hết chỗ".
    'nguong_sap_het' => (int) env('LIEN_MINH_NGUONG_SAP_HET', 3),
];
