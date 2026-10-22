# Tài liệu nội bộ (không phục vụ ra web — nginx chỉ mở thư mục public/)

| Tệp | Dùng cho | Cách dùng |
|---|---|---|
| `mau-bao-gia-doanh-nghiep.xlsx` | Sales — báo giá nhanh du lịch công ty / team building | Sheet "Hướng dẫn". Lần đầu điền giá thật ở "Bảng đơn giá"; mỗi khách chép sheet "Báo giá" |
| `theo-doi-trich-dan-ai.xlsx` | Marketing — PSV có được ChatGPT / Perplexity / Gemini nhắc tới không | Mỗi tháng hỏi 20 câu ở sheet "Câu hỏi", mỗi câu 3 lần / nền tảng, ghi ở "Theo dõi"; "Tổng hợp" tự tính tỉ lệ |

Đo tự động đi kèm:
- Admin → Bán hàng → **Nguồn khách**: đơn / yêu cầu theo kênh (TikTok, Google, ChatGPT…) và theo mã clip (utm_content).
- `./scripts/dem-bot-ai.sh 30` trên VPS: bot AI có vào đọc web không, AI mở trang nào khi trả lời.
- `php artisan psv:bao-indexnow`: báo Bing (nguồn của ChatGPT tìm kiếm, Copilot) mọi trang trong sitemap.

Hồ sơ năng lực PDF nằm ở repo frontend: `tai-lieu/ho-so-nang-luc/` (nguồn) → `public/ho-so-nang-luc-psv-travel.pdf`.
