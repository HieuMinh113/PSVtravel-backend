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

# Chính file script này nằm trong repo, nên bước lấy mã nguồn có thể thay đổi
# nó ngay giữa lúc đang chạy. Bash đọc file theo vị trí byte chứ không nạp
# toàn bộ vào bộ nhớ, nên phiên bản mới KHÔNG có tác dụng ở lần chạy này —
# đã xảy ra thật: bản sửa thứ tự các bước nằm trên đĩa mà lần chạy đó vẫn theo
# thứ tự cũ.
#
# Cách xử lý: lần gọi đầu chỉ sao lưu và lấy mã nguồn, rồi tự khởi động lại
# bằng đúng file vừa tải về.
if [ "${PSV_DA_LAY_MA:-0}" != "1" ]; then
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

    # Bỏ bẫy trước khi nhảy sang bản mới, nếu không nó tưởng đang lỗi
    trap - EXIT
    export PSV_DA_LAY_MA=1
    exec "$0" "$@"
fi

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

# Website đã chạy lại. Từ đây có lỗi cũng không phải chuyện bảo trì nữa, bỏ bẫy
# đi để phần kiểm tra bên dưới không in ra câu "đang tắt thông báo bảo trì".
trap - EXIT

WEB="https://${PSV_DOMAIN:-psvtravel.com}"
API="https://${PSV_API_DOMAIN:-api.psvtravel.com}"

# Gọi trước vài trang chính để máy chủ dựng lại nội dung mới ngay, khách đầu
# tiên khỏi phải chờ.
for DUONG in "" "/tour-trong-nuoc" "/tour-nuoc-ngoai"; do
    curl -s -o /dev/null "$WEB$DUONG" || true
done

echo
echo "==> Kiểm tra"
sleep 3

MA_API=$(curl -s -o /dev/null -w "%{http_code}" "$API/api/v1/settings" || echo 000)
MA_WEB=$(curl -s -o /dev/null -w "%{http_code}" "$WEB/" || echo 000)
echo "    API: $MA_API    Website: $MA_WEB"

# Mã 200 KHÔNG đủ để nói là xong.
#
# Đúng cái bẫy đã cắn ba lần: trang dựng ra rỗng vẫn trả về 200 tử tế, khách mở
# lên thấy "0 tour phù hợp" mà nhật ký không có lấy một dòng lỗi. Nên phải đếm
# xem trang có thật sự chứa tour không.
#
# Danh sách tour do trình duyệt dựng nên chuỗi HTML không có thẻ tour, nhưng dữ
# liệu tour thì luôn nằm trong đó — "categorySlugs" là dấu vết chắc chắn nhất.
dem_tour() {
    # || true là BẮT BUỘC: script bật set -o pipefail, mà grep không tìm thấy gì
    # thì trả mã 1 — đúng trường hợp cần cảnh báo. Thiếu nó thì script chết ngay
    # tại dòng này và không in ra lấy một chữ giải thích.
    curl -s "$WEB$1" | grep -o 'categorySlugs' | wc -l || true
}
SO_NN=$(dem_tour /tour-nuoc-ngoai)
SO_TN=$(dem_tour /tour-trong-nuoc)
echo "    Tour dựng sẵn trong trang — nước ngoài: $SO_NN    trong nước: $SO_TN"

if [ "$MA_API" != "200" ] || [ "$MA_WEB" != "200" ]; then
    echo
    echo "    !! Website không phản hồi. Xem nhật ký:"
    echo "       $COMPOSE logs --tail=50 app"
    echo "       $COMPOSE logs --tail=50 frontend"
    exit 1
fi

if [ "$SO_NN" -eq 0 ] || [ "$SO_TN" -eq 0 ]; then
    echo
    echo "    !! Website trả về 200 nhưng TRANG DỰNG RA KHÔNG CÓ TOUR NÀO."
    echo "       Khách mở lên sẽ thấy \"0 tour phù hợp\" và phải F5 vài lần."
    echo "       Thường là do lúc đóng ảnh Docker không gọi được API."
    echo "       Xem nhật ký:  $COMPOSE logs --tail=50 frontend"
    exit 1
fi

echo "    Xong. Website đã chạy phiên bản mới."
