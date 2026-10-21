<?php

return [
    'name' => 'Guide',

    // Một người (theo IP) đọc lại bài trong N phút chỉ tính 1 lượt xem; 0 = tắt
    // (tính từng lần bấm, để kiểm thử). Đọc ở đây chứ không gọi env() trong
    // controller: production chạy "artisan optimize" thì env() ngoài file cấu
    // hình bị bỏ qua.
    'luot_xem_cach_nhau_phut' => (int) env('LUOT_XEM_CACH_NHAU_PHUT', 60),
];
