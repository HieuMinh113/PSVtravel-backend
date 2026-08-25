#!/bin/bash
# Cập nhật website lên phiên bản mới nhất.
#
#   cd /opt/psvtravel/psvtravel-backend && ./scripts/trien-khai.sh
#
# set -e: gặp lỗi là dừng ngay, không chạy tiếp các bước sau. Thà dừng giữa
# chừng còn hơn chạy tới cùng với CSDL đã đổi mà mã nguồn thì chưa.
set -euo pipefail

# Nhánh đang phát triển. Truyền tên nhánh khác làm tham số nếu cần:
#   ./scripts/trien-khai.sh main
NHANH="${1:-claude/github-repos-exploration-1izvgb}"
THU_MUC_BE="$(cd "$(dirname "$0")/.." && pwd)"
THU_MUC_FE="$(dirname "$THU_MUC_BE")/psvtravel-frontend"
COMPOSE="docker compose -f docker-compose.prod.yml"

cd "$THU_MUC_BE"

echo "==> 0/8  Sao lưu cơ sở dữ liệu trước khi đụng vào gì"
./scripts/sao-luu-csdl.sh

echo "==> 1/8  Lấy mã nguồn mới (nhánh $NHANH)"
# Dùng fetch + reset --hard chứ không phải pull.
#
# Máy chủ là nơi CHẠY, không phải nơi sửa code — nó phải khớp đúng nhánh trên
# GitHub. Dùng pull thì chỉ cần một commit lỡ tay trên máy chủ là git báo
# "divergent branches" và dừng giữa chừng.
#
# reset --hard chỉ động vào file git theo dõi: .env, vendor/ và thư mục ảnh
# upload đều nằm ngoài git nên không bị mất.
for DIR in "$THU_MUC_BE" "$THU_MUC_FE"; do
    git -C "$DIR" fetch origin "$NHANH"
    git -C "$DIR" reset --hard FETCH_HEAD
done

echo "==> 2/8  Bật thông báo bảo trì"
# --render dùng trang bảo trì đẹp thay vì dòng chữ trống trơn.
# Bỏ qua nếu lệnh lỗi: web đang chạy vẫn hơn là dừng deploy vì cái này.
$COMPOSE exec -T app php artisan down --retry=60 || true

echo "==> 3/8  Đóng lại ảnh Docker"
# Frontend BẮT BUỘC build lại mỗi lần: địa chỉ API được nhúng thẳng vào mã
# JavaScript lúc build, không đọc lúc chạy.
$COMPOSE build app queue frontend

echo "==> 4/8  Cài thư viện PHP"
$COMPOSE run --rm --no-deps app composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 5/8  Cập nhật cấu trúc cơ sở dữ liệu"
$COMPOSE run --rm app php artisan migrate --force

echo "==> 6/8  Khởi động lại các dịch vụ"
# --force-recreate cho frontend: ảnh Docker mới chỉ có tác dụng khi container
# được tạo lại, restart suông thì vẫn chạy ảnh cũ.
$COMPOSE up -d --remove-orphans --force-recreate frontend
$COMPOSE up -d --remove-orphans

echo "==> 7/8  Nạp lại bộ nhớ đệm"
$COMPOSE exec -T app php artisan optimize
$COMPOSE exec -T app php artisan filament:optimize
# Worker chạy nền giữ mã cũ trong bộ nhớ mãi mãi nếu không bảo nó thoát.
# Thiếu dòng này thì mail xác nhận đơn vẫn dùng mẫu cũ.
$COMPOSE exec -T app php artisan queue:restart

echo "==> 8/8  Tắt thông báo bảo trì"
$COMPOSE exec -T app php artisan up

echo
echo "==> Kiểm tra"
sleep 3
MA_API=$(curl -s -o /dev/null -w "%{http_code}" "https://${PSV_API_DOMAIN:-api.psvtravel.com}/api/v1/settings" || echo 000)
MA_WEB=$(curl -s -o /dev/null -w "%{http_code}" "https://${PSV_DOMAIN:-psvtravel.com}/" || echo 000)
echo "    API: $MA_API    Website: $MA_WEB"

if [ "$MA_API" = "200" ] && [ "$MA_WEB" = "200" ]; then
    echo "    Xong. Website đã chạy phiên bản mới."
else
    echo
    echo "    !! Có gì đó không ổn. Xem log:"
    echo "       $COMPOSE logs --tail=50 app"
    echo "       $COMPOSE logs --tail=50 frontend"
    exit 1
fi
