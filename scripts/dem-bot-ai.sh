#!/usr/bin/env bash
# Đếm bot AI (ChatGPT, Perplexity, Claude, Gemini…) vào website và lượt khách
# bấm link PSV từ câu trả lời của AI — CHỈ ĐỌC nhật ký nginx, không sửa gì.
#
# Cách dùng trên VPS (mỗi lệnh một dòng):
#   cd /opt/psvtravel/psvtravel-backend
#   ./scripts/dem-bot-ai.sh 30          # 30 ngày gần nhất (mặc định)
#
# Đọc thế nào:
#   - Bot thu thập (GPTBot, OAI-SearchBot, PerplexityBot, ClaudeBot…) có vào thì
#     AI mới biết tới trang. 0 lượt nhiều tuần liền = AI chưa đọc website.
#   - Bot "theo yêu cầu người dùng" (ChatGPT-User, Perplexity-User, Claude-User):
#     một người đang hỏi AI và AI mở trang PSV để trả lời — dấu hiệu PSV đang
#     được dùng làm nguồn trích dẫn.
#   - "Khách bấm từ trợ lý AI": người thật bấm link PSV trong câu trả lời.
#   - Mã lỗi 4xx/5xx nhiều ở một bot = bot bị chặn / trang lỗi, cần báo IT.
#
# Nhật ký nginx chỉ còn từ lần container nginx được tạo lại gần nhất. Ảnh, tệp
# tĩnh không ghi nhật ký (cấu hình nginx) nên chỉ đếm lượt mở trang.
#
# Thử trên máy khác: ./scripts/dem-bot-ai.sh 30 /đường/dẫn/access.log
set -euo pipefail

NGAY="${1:-30}"
case "$NGAY" in ''|*[!0-9]*) echo "Số ngày phải là số, VD: ./scripts/dem-bot-ai.sh 30" >&2; exit 1 ;; esac

if [ -n "${2:-}" ]; then
    doc_log() { cat "$2"; }
else
    cd "$(dirname "$0")/.."
    doc_log() { docker compose -f docker-compose.prod.yml logs --no-color --no-log-prefix --since "$((NGAY * 24))h" nginx 2>/dev/null; }
fi

doc_log "$@" | awk -v ngay="$NGAY" '
BEGIN {
    # Thứ tự quan trọng: tên dài trước (Claude-SearchBot trước ClaudeBot…)
    n = split("ChatGPT-User|OAI-SearchBot|GPTBot|Perplexity-User|PerplexityBot|Claude-User|Claude-SearchBot|ClaudeBot|Google-CloudVertexBot|GoogleOther|Googlebot|bingbot|meta-externalfetcher|meta-externalagent|Applebot|DuckAssistBot|MistralAI-User|Amazonbot|Bytespider|CCBot", BOT, "|")
    LOAI["ChatGPT-User"] = "ChatGPT mở trang khi trả lời người dùng"
    LOAI["OAI-SearchBot"] = "ChatGPT tìm kiếm (lập chỉ mục)"
    LOAI["GPTBot"] = "OpenAI thu thập để huấn luyện"
    LOAI["Perplexity-User"] = "Perplexity mở trang khi trả lời"
    LOAI["PerplexityBot"] = "Perplexity lập chỉ mục"
    LOAI["Claude-User"] = "Claude mở trang khi trả lời"
    LOAI["Claude-SearchBot"] = "Claude tìm kiếm (lập chỉ mục)"
    LOAI["ClaudeBot"] = "Anthropic thu thập để huấn luyện"
    LOAI["Google-CloudVertexBot"] = "Google Vertex AI"
    LOAI["GoogleOther"] = "Google (nghiên cứu, AI)"
    LOAI["Googlebot"] = "Google Search + AI Overviews, Gemini"
    LOAI["bingbot"] = "Bing (nguồn của Copilot, ChatGPT tìm kiếm)"
    LOAI["meta-externalfetcher"] = "Meta AI mở trang khi trả lời"
    LOAI["meta-externalagent"] = "Meta AI thu thập"
    LOAI["Applebot"] = "Apple (Siri, Apple Intelligence)"
    LOAI["DuckAssistBot"] = "DuckDuckGo AI"
    LOAI["MistralAI-User"] = "Mistral Le Chat mở trang"
    LOAI["Amazonbot"] = "Amazon (Alexa)"
    LOAI["Bytespider"] = "ByteDance / TikTok"
    LOAI["CCBot"] = "Common Crawl (dữ liệu nhiều AI dùng)"
    THEO_YEU_CAU = "|ChatGPT-User|Perplexity-User|Claude-User|meta-externalfetcher|MistralAI-User|"
    # Bot theo dõi mã lỗi chi tiết (máy tìm kiếm + AI quan trọng)
    CHI_TIET = "|Googlebot|bingbot|OAI-SearchBot|ChatGPT-User|GPTBot|PerplexityBot|Perplexity-User|Claude-User|Claude-SearchBot|ClaudeBot|Applebot|"
    split("chatgpt.com|chat.openai.com|perplexity.ai|gemini.google.com|copilot.microsoft.com|claude.ai|chat.deepseek.com|meta.ai", AIREF, "|")
}
# Lượt MỞ TRANG (không tính ảnh, tệp tĩnh, gọi API, tải ngầm _rsc của Next)
function laTrang(d,    goc) {
    if (d ~ /_rsc=/ || d ~ /^\/(_next|api|storage)\//) return 0
    goc = d; sub(/\?.*/, "", goc)
    if (goc ~ /\.(png|jpe?g|webp|gif|svg|ico|css|js|woff2?|map|xml)$/) return 0
    return goc != "/robots.txt"
}
function nhomMa(m) { return m ~ /^404|^410/ ? "404" : (m ~ /^429|^503/ ? "429_503" : (m ~ /^5/ ? "5xx" : "khac")) }
{
    # Dạng combined: IP - - [thời gian] "GET /duong-dan HTTP/1.1" mã bytes "giới thiệu" "trình duyệt"
    if ((nq = split($0, q, "\"")) < 7) next
    split(q[2], yc, " "); duong = yc[2]
    split(q[3], sau, " "); ma = sau[1]
    ref = q[4]; ua = q[6]
    # Tên miền ở cuối dòng (nhật ký từ 10/2026); lượt vào API ghi kèm "api:"
    mien = (nq >= 10 && q[10] ~ /^api\./) ? "api:" : ""
    if (match($0, /\[[^]]+\]/)) tg = substr($0, RSTART + 1, 17); else tg = ""

    for (i = 1; i <= n; i++) {
        if (index(ua, BOT[i])) {
            b = BOT[i]; dem[b]++; cuoi[b] = tg
            if (ma !~ /^[23]/) {
                loi[b]++
                if (index(CHI_TIET, "|" b "|")) {
                    loiNhom[b, nhomMa(ma)]++
                    dl = mien duong; if (length(dl) > 90) dl = substr(dl, 1, 90) "…"
                    trangLoi[ma "  " dl]++
                }
            }
            if (duong == "/robots.txt") robots[b]++
            if (index(THEO_YEU_CAU, "|" b "|") && laTrang(duong)) trang[duong]++
            break
        }
    }
    # Người thật bấm link PSV trong câu trả lời của AI: chỉ lượt mở trang; đếm
    # cả số người khác nhau (IP + trình duyệt) — một người mở 3 trang là 3 lượt
    if (ua !~ /[Bb]ot|[Ss]pider|[Cc]rawl/ && laTrang(duong)) {
        nguon = ""
        for (j in AIREF) if (index(ref, AIREF[j])) nguon = AIREF[j]
        if (nguon == "" && duong ~ /utm_source=chatgpt\.com/) nguon = "chatgpt.com"
        if (nguon != "") {
            khach[nguon]++
            ai = $1 "|" ua
            if (!((nguon, ai) in daThay)) { daThay[nguon, ai] = 1; nguoi[nguon]++ }
            dk = duong; sub(/[?&]_rsc=[^&]*/, "", dk); trangKhach[dk]++
        }
    }
}
END {
    printf "\n== Bot AI / máy tìm kiếm vào website — %s ngày gần nhất\n", ngay
    # Tiêu đề viết sẵn: printf đếm byte nên chữ có dấu làm lệch cột
    print "Bot                        Lượt      Lỗi  Lần cuối           Là gì"
    co = 0
    for (i = 1; i <= n; i++) { b = BOT[i]; if (dem[b]) { co = 1; printf "%-22s %8d %8d  %-17s  %s\n", b, dem[b], loi[b], cuoi[b], LOAI[b] } }
    if (!co) print "   (không thấy bot nào — nhật ký trống hoặc nginx vừa được tạo lại)"

    printf "\n== Lỗi bot gặp (mã 404 = trang không có, 429/503 = bị chặn vì gửi quá nhanh / bảo trì, 5xx = lỗi máy chủ)\n"
    print "Bot                         404  429/503      5xx     khác"
    co = 0
    for (i = 1; i <= n; i++) {
        b = BOT[i]
        if (index(CHI_TIET, "|" b "|") && loi[b]) {
            co = 1
            printf "%-22s %8d %8d %8d %8d\n", b, loiNhom[b, "404"], loiNhom[b, "429_503"], loiNhom[b, "5xx"], loiNhom[b, "khac"]
        }
    }
    if (!co) print "   (không có lỗi)"
    if (co) {
        printf "\n   Đường dẫn lỗi nhiều nhất (máy tìm kiếm + AI):\n"
        for (k in trangLoi) printf "%6d  %s\n", trangLoi[k], k | "sort -rn | head -20"
        close("sort -rn | head -20")
    }

    printf "\n== Trang AI mở khi đang trả lời người dùng (nhiều nhất)\n"
    co = 0
    for (d in trang) { co = 1; printf "%6d  %s\n", trang[d], d | "sort -rn | head -15" }
    close("sort -rn | head -15")
    if (!co) print "   (chưa có — PSV chưa được AI mở ra để trả lời trong khoảng này)"

    printf "\n== Khách bấm link PSV từ trợ lý AI (chỉ lượt mở trang)\n"
    co = 0
    for (k in khach) { co = 1; printf "%6d lượt mở trang, %d người  %s\n", khach[k], nguoi[k], k }
    if (!co) print "   (chưa có lượt nào)"
    if (co) { printf "   Trang khách vào:\n"; for (d in trangKhach) printf "%6d  %s\n", trangKhach[d], d | "sort -rn | head -10"; close("sort -rn | head -10") }
    print ""
}'
