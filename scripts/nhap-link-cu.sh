#!/usr/bin/env bash
# Lấy các link bị 404 trên website trong nhật ký nginx (phần lớn là link của web
# WordPress cũ psvtravel.com.vn) rồi cho máy khớp sang trang mới — kết quả hiện
# ngay trong admin: Hệ thống → Chuyển hướng link cũ.
#
# Cách dùng trên VPS (mỗi lệnh một dòng):
#   cd /opt/psvtravel/psvtravel-backend
#   ./scripts/nhap-link-cu.sh 30 --xem    # chỉ xem máy khớp thế nào, chưa lưu
#   ./scripts/nhap-link-cu.sh 30          # lưu vào bảng chuyển hướng
#
# Chạy lại nhiều lần không sao: link đã có trong bảng giữ nguyên.
set -euo pipefail

NGAY="${1:-30}"
case "$NGAY" in ''|*[!0-9]*) echo "Số ngày phải là số, VD: ./scripts/nhap-link-cu.sh 30" >&2; exit 1 ;; esac
cd "$(dirname "$0")/.."
COMPOSE="docker compose -f docker-compose.prod.yml"

# Chỉ lấy lượt GET bị 404 vào website (không phải tên miền API — dòng nhật ký
# mới có tên miền ở cuối), bỏ tệp Next.js / API / ảnh tải lên.
$COMPOSE logs --no-color --no-log-prefix --since "$((NGAY * 24))h" nginx 2>/dev/null \
    | awk -F'"' 'NF >= 7 {
        split($2, yc, " "); split($3, sau, " ")
        if (yc[1] != "GET" || sau[1] != "404") next
        if (NF >= 11 && $10 ~ /^api\./) next
        duong = yc[2]; sub(/\?.*/, "", duong)
        if (duong ~ /^\/(_next|api|storage|admin|livewire)\//) next
        print duong
    }' \
    | sort | uniq -c | sort -rn \
    | $COMPOSE exec -T app php artisan psv:link-cu ${2:+"$2"}
