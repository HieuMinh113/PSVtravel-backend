#!/usr/bin/env bash
# Xem tải của website theo giờ — CHỈ ĐỌC nhật ký nginx và tình trạng máy, không
# sửa gì.
#
# Cách dùng trên VPS (mỗi lệnh một dòng):
#   cd /opt/psvtravel/psvtravel-backend
#   ./scripts/kiem-tra-tai.sh          # 24 giờ gần nhất (mặc định)
#   ./scripts/kiem-tra-tai.sh 72       # 3 ngày
#
# Đọc thế nào:
#   - Theo giờ (giờ Việt Nam, cộng dồn các ngày): lượt mở trang website, lượt
#     vào API + trang quản trị, thời gian xử lý trung bình, % lượt chậm hơn 1
#     giây, lượt phải xếp hàng / bị chặn vì gửi quá nhanh, lỗi máy chủ.
#   - Bị chặn: IP nào bị chặn nhiều. IP văn phòng (mở trang "what is my ip" ở
#     công ty để biết) bị chặn = nhân viên thấy web lag, trang thiếu chữ.
#   - Trang chậm nhất: trang nào bắt máy chủ xử lý lâu nhất.
#   - Ai tải nhiều nhất: bot (Googlebot, AhrefsBot, bot AI…) hay người thật.
#
# Thời gian xử lý chỉ có từ bản cấu hình nginx ngày 03/11/2026 (rt=… cuối mỗi
# dòng nhật ký). Ảnh, JS, CSS không ghi nhật ký nên chỉ đếm trang và API.
# Nhật ký chỉ còn từ lần container nginx được tạo lại gần nhất.
#
# Thử trên máy khác: ./scripts/kiem-tra-tai.sh 24 /đường/dẫn/nginx.log
set -euo pipefail

GIO="${1:-24}"
TEP="${2:-}"
case "$GIO" in ''|*[!0-9]*) echo "Số giờ phải là số, VD: ./scripts/kiem-tra-tai.sh 24" >&2; exit 1 ;; esac

if [ -n "$TEP" ]; then
    doc_log() { cat "$TEP"; }
else
    cd "$(dirname "$0")/.."
    # 2>&1: nhật ký lỗi (bị chặn, quá thời gian chờ) nằm ở luồng lỗi của container
    doc_log() { docker compose -f docker-compose.prod.yml logs --no-color --no-log-prefix --since "${GIO}h" nginx 2>&1; }

    echo "== Máy chủ (lúc này)"
    echo "   CPU: $(nproc) nhân · tải 1 / 5 / 15 phút: $(cut -d' ' -f1-3 /proc/loadavg)  (cao hơn số nhân = máy đang quá tải)"
    free -h | awk 'NR == 2 { print "   RAM: dùng " $3 " / " $2 " (còn dùng được " $7 ")" }'
    df -h / | awk 'NR == 2 { print "   Ổ đĩa: dùng " $3 " / " $2 " (" $5 ")" }'
    docker stats --no-stream --format '   {{.Name}}: CPU {{.CPUPerc}} · RAM {{.MemUsage}}' 2>/dev/null || true
    echo "   Múi giờ máy: $(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || echo '?')"
    crontab -l 2>/dev/null | grep -v '^#' | grep sao-luu | sed 's/^/   Lịch sao lưu (theo múi giờ máy): /' || true
    echo
fi

# awk thường (mawk trên Ubuntu): không dùng hàm riêng của gawk
doc_log | awk -v gio="$GIO" '
function vn(h, lech) { return (h - lech + 7 + 48) % 24 }   # giờ trong nhật ký → giờ Việt Nam
function ms(tong, so) { return so ? sprintf("%d", tong / so * 1000) : "-" }
function phan(a, b) { return b ? sprintf("%d%%", a * 100 / b) : "-" }

# ---- Dòng nhật ký truy cập: 1.2.3.4 - - [03/Nov/2026:01:02:03 +0000] "GET /x HTTP/2.0" 200 … "host" rt=0.012 urt=0.010
/\] "/ {
    if ((nq = split($0, q, "\"")) < 7) next
    if (!match($0, /\[[^]]+\]/)) next
    tg = substr($0, RSTART + 1, RLENGTH - 2)          # 03/Nov/2026:01:02:03 +0000
    tz = substr(tg, 22, 5)
    lech = (substr(tz, 1, 1) == "-" ? -1 : 1) * (substr(tz, 2, 2) + 0)
    h = vn(substr(tg, 13, 2) + 0, lech)
    ngay[substr(tg, 1, 11)] = 1
    if (dau == "") dau = tg
    cuoi = tg

    split(q[2], yc, " "); duong = yc[2]
    split(q[3], sau, " "); ma = sau[1] + 0
    loai = (nq >= 10 && q[10] ~ /^api\./) ? "api" : "web"
    n[h, loai]++; tong[loai]++
    if (ma >= 500) l5[h]++

    # rt = khách chờ (gồm cả lúc xếp hàng), urt = Next / PHP xử lý ("-" = nginx tự trả)
    rt = -1; urt = -1
    if (nq >= 11 && match(q[nq], / rt=[0-9.]+/)) rt = substr(q[nq], RSTART + 4, RLENGTH - 4) + 0
    if (nq >= 11 && match(q[nq], /urt=[0-9.]+/)) urt = substr(q[nq], RSTART + 4, RLENGTH - 4) + 0
    if (rt >= 0) {
        ct[h, loai]++; st[h, loai] += rt
        if (rt > 1) cham[h, loai]++
    }
    if (urt >= 0) {
        sub(/\?.*/, "", duong)
        gsub(/\/[0-9]+(\/|$)/, "/{so}/", duong)       # /admin/bookings/123/edit → /admin/bookings/{so}/edit
        k = loai ":" duong
        pc[k]++; ps[k] += urt; pr[k] += rt
        if (urt > pm[k]) pm[k] = urt
    }

    ua = q[6]
    if (match(ua, /[A-Za-z0-9_.-]*([Bb]ot|[Cc]rawler|[Ss]pider|[Ss]craper)[A-Za-z0-9_.-]*/)) ten = substr(ua, RSTART, RLENGTH)
    else if (ua == "-" || ua == "") ten = "Không ghi tên (không có User-Agent)"
    else if (ua ~ /curl|python|Go-http|axios|node-fetch|undici|Wget|HeadlessChrome/) ten = "Công cụ tự động (" substr(ua, 1, 20) ")"
    else ten = "Người dùng (trình duyệt)"
    ai[ten]++
    ip[$1]++; ipTen[$1] = ten
    next
}

# ---- Dòng nhật ký lỗi: 2026/11/03 01:02:03 [error] … limiting requests, excess: … by zone "psv_chung", client: 1.2.3.4, …
/^[0-9][0-9][0-9][0-9]\/[0-9][0-9]\/[0-9][0-9] / {
    h = vn(substr($2, 1, 2) + 0, 0)                   # container nginx chạy giờ UTC
    khach = ""
    if (match($0, /client: [^,]+/)) khach = substr($0, RSTART + 8, RLENGTH - 8)
    if ($0 ~ /limiting requests/) {
        chan[h]++; tongChan++; chanIp[khach]++
        if (match($0, /zone "[^"]+"/)) chanVung[substr($0, RSTART + 6, RLENGTH - 7)]++
    } else if ($0 ~ /delaying request/) {
        cho[h]++; tongCho++
    } else if ($0 ~ /upstream timed out/) {
        treo[h]++; tongTreo++
    }
}

END {
    soNgay = 0; for (d in ngay) soNgay++
    if (!soNgay) {
        print "Không có dòng nhật ký nào trong " gio " giờ qua (container nginx mới tạo lại?)."
        exit
    }
    print "== Nhật ký từ " dau " đến " cuoi " (giờ ghi trong nhật ký: UTC)"
    printf "   %d lượt mở trang website, %d lượt API + trang quản trị, trong %d ngày\n\n", tong["web"], tong["api"], soNgay

    print "== Theo giờ (giờ Việt Nam, cộng dồn " soNgay " ngày)"
    print "   TB = thời gian xử lý trung bình; >1s = % lượt chậm hơn 1 giây"
    print "   Xếp hàng = gửi hơi nhanh, phải chờ một chút; Bị chặn = quá nhanh, trả lỗi 503"
    print ""
    print "   Giờ  │ Website   TB(ms)  >1s │ API+admin  TB(ms)  >1s │ Xếp hàng  Bị chặn  Lỗi 5xx  Treo"
    for (h = 0; h < 24; h++) {
        if (!(n[h, "web"] + n[h, "api"] + chan[h] + cho[h])) continue
        printf "   %02dh  │ %7d %8s %4s │ %9d %7s %4s │ %8d %8d %8d %5d\n", h,
            n[h, "web"], ms(st[h, "web"], ct[h, "web"]), phan(cham[h, "web"], ct[h, "web"]),
            n[h, "api"], ms(st[h, "api"], ct[h, "api"]), phan(cham[h, "api"], ct[h, "api"]),
            cho[h], chan[h], l5[h], treo[h]
    }

    print ""
    if (tongChan) {
        printf "== Bị chặn vì gửi quá nhanh: %d lượt", tongChan
        for (v in chanVung) printf " · %s: %d", (v == "psv_chung" ? "trang + API" : v == "psv_anh" ? "ảnh" : v == "psv_dangnhap" ? "đăng nhập admin" : v), chanVung[v]
        print ""
        print "   IP bị chặn nhiều nhất (so với IP văn phòng):"
        lenh = "sort -k1,1nr | head -10"
        for (x in chanIp) printf "   %7d  %s%s\n", chanIp[x], x, (x ~ /^(172\.|10\.|192\.168\.)/ ? "  (mạng nội bộ Docker — chính máy chủ Next)" : "") | lenh
        close(lenh)
    } else {
        print "== Không lượt nào bị chặn vì gửi quá nhanh."
    }
    if (tongCho) printf "   (%d lượt phải xếp hàng chờ một chút — bình thường lúc đông người)\n", tongCho
    if (tongTreo) printf "== %d lượt Next / PHP quá thời gian chờ (upstream timed out) — máy chủ quá tải hoặc treo\n", tongTreo

    print ""
    print "== Trang bắt máy chủ xử lý lâu nhất (ít nhất 3 lượt có đo thời gian)"
    print "   Xử lý TB(ms)  Lâu nhất(ms)  Khách chờ TB(ms)  Số lượt  Trang (web: website, api: API + quản trị)"
    lenh = "sort -k1,1nr | head -15"
    co = 0
    for (k in pc) if (pc[k] >= 3) { co = 1; printf "   %12d  %12d  %16d  %7d  %s\n", ps[k] / pc[k] * 1000, pm[k] * 1000, pr[k] / pc[k] * 1000, pc[k], k | lenh }
    close(lenh)
    if (!co) print "   (chưa có dữ liệu thời gian — nhật ký trước bản cấu hình nginx ngày 03/11/2026)"

    print ""
    print "== Ai tải nhiều nhất"
    lenh = "sort -k1,1nr | head -12"
    for (t in ai) printf "   %7d  %s\n", ai[t], t | lenh
    close(lenh)
    print ""
    print "== IP gọi nhiều nhất"
    lenh = "sort -k1,1nr | head -8"
    for (x in ip) printf "   %7d  %-40s %s\n", ip[x], x, ipTen[x] | lenh
    close(lenh)
}'
