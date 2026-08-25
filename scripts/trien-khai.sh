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

# Gặp lỗi giữa chừng thì PHẢI tắt thông báo bảo trì trước khi thoát.
#
# Bước 2 bật bảo trì, mà từ bản này việc đóng ảnh sẽ DỪNG LẠI nếu không gọi
# được API (thà dừng còn hơn cho ra trang rỗng). Không có bẫy này thì lỗi build
# đồng nghĩa với website nằm trong màn hình bảo trì cho tới khi có người phát
# hiện ra.
don_dep() {
    local ma=$?
    if [ $ma -ne 0 ]; then
        echo
        echo "!! Triển khai DỪNG ở giữa chừng. Đang tắt thông báo bảo trì..."
        $COMPOSE exec -T app php artisan up || true
        echo "   Website đã chạy lại bằng phiên bản CŨ. Xem lỗi phía trên."
    fi
}
trap don_dep EXIT

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

echo "==> 2/8  Đóng lại ảnh Docker"
# CHƯA bật thông báo bảo trì ở bước này — và đây là điểm mấu chốt.
#
# Lúc build, Next gọi API để dựng sẵn nội dung các trang. Nếu website đang
# trong chế độ bảo trì thì Laravel trả 503 cho MỌI lời gọi, nên mọi trang dựng
# ra đều rỗng. Website chạy lên với các trang không có tour, khách phải F5 vài
# lần chờ máy chủ dựng lại mới thấy. Trước đây script bật bảo trì ngay từ đầu
# nên lần triển khai nào cũng tự tạo ra lỗi đó.
#
# Bảo trì chỉ cần bọc quanh phần thật sự nguy hiểm — đổi cấu trúc cơ sở dữ liệu
# và khởi động lại dịch vụ — chứ không phải cả quá trình build dài 5–8 phút.
# Nhờ vậy thời gian website ngừng phục vụ giảm từ vài phút xuống vài chục giây.
#
# nginx phải đang chạy: container build gọi API qua https://api.psvtravel.com
# và được trỏ tên miền đó về chính máy này (xem docker-compose.prod.yml).
$COMPOSE up -d nginx >/dev/null 2>&1 || true

# Frontend BẮT BUỘC build lại mỗi lần: địa chỉ API được nhúng thẳng vào mã
# JavaScript lúc build, không đọc lúc chạy.
$COMPOSE build app queue frontend

echo "==> 3/8  Bật thông báo bảo trì"
# Từ đây trở đi mới thật sự nguy hiểm: thay thư viện PHP, đổi cấu trúc cơ sở
# dữ liệu, khởi động lại dịch vụ. Ảnh Docker đã đóng xong ở trên nên khoảng
# ngừng phục vụ chỉ còn vài chục giây thay vì cả 5–8 phút build.
$COMPOSE exec -T app php artisan down --retry=60 || true

echo "==> 4/8  Cài thư viện PHP"
# Chạy trong lúc bảo trì: thay thư viện ngay dưới chân một tiến trình đang
# phục vụ khách có thể làm hỏng vài yêu cầu đang dở.
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

# Gọi trước vài trang chính để máy chủ dựng lại nội dung mới ngay, khách đầu
# tiên khỏi phải chờ.
for DUONG in "" "/tour-trong-nuoc" "/tour-nuoc-ngoai"; do
    curl -s -o /dev/null "https://${PSV_DOMAIN:-psvtravel.com}${DUONG}" || true
done
