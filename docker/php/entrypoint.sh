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
# Bỏ qua lỗi bằng "|| true": trên một số cách gắn ổ đĩa của Windows, chown
# không có tác dụng nhưng cũng không gây hại, và chmod bên dưới mới là thứ
# thực sự mở được quyền ghi.
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

# Thư mục con Laravel cần nhưng Git không giữ (Git bỏ qua thư mục rỗng)
mkdir -p /var/www/storage/logs \
         /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/app/public 2>/dev/null || true
chmod -R ug+rwX /var/www/storage 2>/dev/null || true

exec "$@"
