#!/bin/bash
# Sao lưu cơ sở dữ liệu ra file nén, giữ 14 bản gần nhất.
#
# Chạy tay:   ./scripts/sao-luu-csdl.sh
# Chạy tự động hằng ngày: xem hướng dẫn trong TRIEN-KHAI.md
set -euo pipefail

THU_MUC_BE="$(cd "$(dirname "$0")/.." && pwd)"
THU_MUC_LUU="/opt/psvtravel/backups"
GIU_LAI_NGAY=14

cd "$THU_MUC_BE"
mkdir -p "$THU_MUC_LUU"

# Đọc tên CSDL từ .env để không phải sửa hai chỗ khi đổi
DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
TEN_FILE="$THU_MUC_LUU/psvtravel_$(date +%Y%m%d_%H%M%S).sql.gz"

docker compose -f docker-compose.prod.yml exec -T postgres \
    pg_dump -U "$DB_USER" "$DB_NAME" | gzip > "$TEN_FILE"

KICH_THUOC=$(du -h "$TEN_FILE" | cut -f1)

# File rỗng nghĩa là pg_dump lỗi mà không báo — đừng để nó trôi qua
if [ ! -s "$TEN_FILE" ]; then
    echo "LỖI: bản sao lưu rỗng, kiểm tra lại container postgres" >&2
    rm -f "$TEN_FILE"
    exit 1
fi

echo "    Đã lưu: $TEN_FILE ($KICH_THUOC)"

# Xoá bản cũ hơn số ngày quy định
find "$THU_MUC_LUU" -name "psvtravel_*.sql.gz" -mtime +$GIU_LAI_NGAY -delete
