#!/bin/sh
set -e

# Cấp quyền ghi cho thư mục Laravel cần ghi.
#
# PHP-FPM chạy dưới người dùng www-data, nhưng mã nguồn được gắn từ máy chủ
# vào container nên thư mục thuộc về root. Không có bước này thì Laravel
# không ghi được log, không lưu được cache và không nhận được file upload —
# lỗi hiện ra dưới dạng "could not be opened in append mode. Permission denied"
# và toàn bộ trang trắng 500.
#
# Đổi NHÓM chứ không đổi CHỦ SỞ HỮU.
#
# Trước đây dùng `chown -R www-data:www-data`. Trên máy chủ Linux, mã nguồn
# được gắn từ ngoài vào nên lệnh đó đổi luôn chủ sở hữu thư mục thật trên đĩa.
# Tài khoản quản trị máy chủ mất quyền ghi, mỗi lần cập nhật mã nguồn git báo:
#
#   error: unable to unlink old 'storage/logs/.gitignore': Permission denied
#
# Chỉ đổi nhóm sang www-data rồi mở quyền ghi cho nhóm là đủ cho PHP-FPM,
# mà chủ sở hữu vẫn là tài khoản trên máy chủ nên git vẫn cập nhật được.
#
# Bỏ qua lỗi bằng "|| true": trên một số cách gắn ổ đĩa của Windows các lệnh
# này không có tác dụng nhưng cũng không gây hại.
chgrp -R www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

# Thư mục con Laravel cần nhưng Git không giữ (Git bỏ qua thư mục rỗng)
mkdir -p /var/www/storage/logs \
         /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/app/public 2>/dev/null || true
chmod -R ug+rwX /var/www/storage 2>/dev/null || true

exec "$@"
