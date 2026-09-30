<?php

namespace App\Services;

/**
 * Loại tệp được phép tải lên ở các ô ảnh trong trang quản trị.
 *
 * ->image() của Filament nhận "image/*" — tức là nhận cả SVG. SVG là tệp văn
 * bản có thể chứa JavaScript, và ảnh upload được phục vụ trên cùng tên miền với
 * trang /admin: một tài khoản nhân viên (quyền thấp nhất) có thể tải SVG độc
 * lên rồi gửi link cho quản trị viên để chiếm quyền. Chỉ nhận ảnh raster.
 *
 * (nginx cũng đã chặn chạy mã trong /storage/ — đây là lớp bảo vệ thứ hai.)
 *
 * KHÔNG nhận AVIF: bộ tối ưu ảnh của Next.js từng có lỗ chạy mã từ xa khi xử
 * lý tệp AVIF (GHSA-2xp9-vwfh-vxw4). Đã nâng bản vá, nhưng JPG/PNG/WebP đã đủ
 * dùng — không nhận định dạng từng là cửa vào thì bớt hẳn một mặt tấn công.
 */
class TepTaiLen
{
    public const ANH = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
}
