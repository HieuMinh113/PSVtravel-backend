<?php

// IndexNow — báo cho Bing/Yandex/Naver biết ngay khi có trang mới/đổi, để
// lập chỉ mục nhanh. Bing nuôi ChatGPT và Copilot nên đây cũng là tín hiệu GEO.
// Khoá KHÔNG bí mật: nó nằm công khai tại https://psvtravel.com/<khoá>.txt.
return [
    'key' => env('INDEXNOW_KEY', '4805dd19bb8135c1e6e0eed87d9f8728'),
    // Địa chỉ tệp khoá (frontend phục vụ). Để trống thì suy ra từ khoá.
    'key_location' => env('INDEXNOW_KEY_LOCATION'),
    'enabled' => env('INDEXNOW_ENABLED', true),
];
