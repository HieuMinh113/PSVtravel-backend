# Chức năng và mã nguồn (cho lập trình viên)

Dựng máy lập trình: `README.md` của từng repo. Chạy test: cuối file này.

## Backend (`psvtravel-backend`)

Laravel 13, Filament 5 (trang quản trị), nwidart/laravel-modules, PostgreSQL 16,
Redis 7, Spatie Permission + Filament Shield (phân quyền), Sanctum (đăng nhập
API), laravel/mcp (máy chủ cho trợ lý AI).

| Thư mục | Chứa |
|---|---|
| `Modules/<Tên>/` | Từng mảng nghiệp vụ: `app/Models`, `app/Http/Controllers/Api`, `app/Policies`, `database/migrations`, `routes/api.php` |
| `app/Filament/Resources/` | Các mục trong trang quản trị (bảng, form) |
| `app/Filament/Pages/` | Trang quản trị đặc biệt: Tổng quan, Chấm công, Bảng công tháng, Thống kê doanh số, Nguồn khách, Quét voucher, Kết nối AI, Hướng dẫn… |
| `app/Http/Controllers/Api/` | API cho website ngoài các module (đăng nhập, liên hệ, tuyển dụng, voucher…) |
| `app/Services/` | Logic dùng chung (chấm công, voucher, link cũ, OTP, thu nhỏ ảnh…) |
| `app/Mcp/` | Máy chủ MCP cho trợ lý AI |
| `app/Console/Commands/`, `Modules/*/app/Console/` | Lệnh `php artisan psv:…` (xem file 03) |
| `database/seeders/` | Vai trò, quyền, dữ liệu mẫu |
| `docker/`, `docker-compose*.yml`, `scripts/` | Máy chủ, nginx, script triển khai / sao lưu |
| `tests/Feature/` | Test (PHPUnit) |

### Chức năng → nơi đặt mã

| Chức năng (menu admin) | Mã nguồn chính | Quy tắc quan trọng |
|---|---|---|
| **Tour, ngày khởi hành, lịch trình, ảnh** (Tour & điều hành) | `Modules/Tour`, `app/Filament/Resources/Tours` | Đợt tự chuyển Còn nhận / Hết chỗ theo số chỗ; không xoá được đợt đã có đơn. Hạn nộp visa của đợt = sớm nhất trong: hạn riêng của đợt, "trước N ngày" của tour, hạn theo nước (mục Hạn nộp visa). Đợt quá hạn visa tự ẩn khỏi web |
| **Đơn đặt tour, khoản thu** (Bán hàng) | `Modules/Booking` | Mã đơn `PSV-YYYYMMDD-XXXX`. Người phụ trách = người tạo đơn trong admin, hoặc người bấm Xác nhận với đơn khách đặt web. Đơn đã xác nhận đổi số khách / đợt → tự trả chỗ đợt cũ, trừ chỗ đợt mới. Khoản thu chỉ tính khi kế toán duyệt. Tiền cọc làm tròn nghìn. Hạn nhắc khách đóng tiền = ngày đi − 7 ngày |
| **Thống kê doanh số** | `app/Filament/Pages/ThongKeDoanhSo.php` | Tính theo **tháng chốt đơn** (ngày xác nhận), đơn huỷ không tính doanh số. Không có quyền "xem tất cả" thì chỉ thấy đơn mình |
| **Nguồn khách** | `app/Services/NguonKhach.php` | Đơn / yêu cầu theo kênh (utm, mã clip, tên miền giới thiệu) |
| **Maldives** (combo, resort, đơn) | `Modules/Maldives` | Giá USD đổi VND theo tỷ giá nhập trong đơn |
| **Visa** (quốc gia, hồ sơ, checklist, hạn nộp, đối tác) | `Modules/Visa` | Nhân viên visa chỉ thấy hồ sơ mình phụ trách + hồ sơ chưa ai nhận. Tệp hồ sơ lưu ổ riêng tư (`storage/app/private`), xem qua link có chữ ký |
| **Voucher** (đợt, mã, quét) | `app/Models/Voucher*.php`, `app/Services/Voucher` | Mã không dùng ký tự dễ nhầm (0, 1, I, L, O). Đánh dấu "đã dùng" chỉ thành công một lần dù hai người quét cùng lúc. File xuất tự xoá hằng ngày |
| **Chấm công khuôn mặt** (Nhân sự) | `app/Services/ChamCong`, `app/Filament/Pages/ChamCong.php`, `public/js/psv/cham-cong.js`, `public/vendor/face-api` | Nhận diện khuôn mặt chạy **trên trình duyệt** (face-api); máy chủ lưu đặc trưng khuôn mặt đã đăng ký và ảnh lúc chấm. Kiểm tra vị trí GPS theo bán kính trong **Cấu hình chấm công**. Chấm ngoài công ty phải quản lý duyệt (không duyệt = 0 công). Ảnh chấm công tự xoá sau 3 tháng. Quyền "Gỡ lượt chấm" (`Remove:Attendance`) để gỡ lượt bấm nhầm |
| **Tuyển dụng, hồ sơ ứng tuyển** | `app/Filament/Resources/JobPostings`, `JobApplications` | CV là dữ liệu cá nhân: mặc định chỉ super_admin / admin xem |
| **Cẩm nang, bản đồ cẩm nang, tác giả** (Nội dung website) | `Modules/Guide`, `app/Filament/Resources/TeamMembers` | Gợi ý SEO cho từng bài trong form và danh sách (`app/Services/KiemTraSeoBai.php`) |
| **Banner, ưu đãi, trang theo mùa, link cũ** (Marketing) | `Modules/Banner`, `app/Filament/Resources/{Promotions,SeasonPages,Redirects}` | Link của web cũ `psvtravel.com.vn` tự khớp sang trang mới, lưu ở **Marketing → Chuyển hướng link cũ** |
| **Sheet liên minh, ngày đi liên minh** | `Modules/Alliance` | Đọc Google Sheet của đối tác 5 phút/lần (`lien-minh:dong-bo`) |
| **Team building, sự kiện** | `app/Filament/Resources/Events`, `app/Filament/Pages/TrangTeamBuilding.php` | |
| **Liên hệ** (Bán hàng → tin liên hệ) | `app/Http/Controllers/Api/ContactController.php` | |
| **Kết nối AI (MCP)** (Hệ thống) | `routes/ai.php`, `app/Mcp/` | `/mcp`: công khai, chỉ dữ liệu đang bán + gửi yêu cầu tư vấn khi khách đồng ý. `/mcp/noi-bo`: mã khoá của Admin; xem số liệu (chỉ đọc, che số điện thoại / email khách) và soạn bài cẩm nang **nháp** — không đăng được |
| **Người dùng, vai trò, nhật ký, cấu hình chung** (Hệ thống) | `app/Filament/Resources/{Users,Roles,Activities,Settings}` | Quyền theo dạng `Hành động:Mục` (vd `ViewAny:Booking`). Nhật ký hoạt động tự dọn hằng ngày |

## Frontend (`psvtravel-frontend`)

Next.js 16 (App Router), React 19, Tailwind 3, framer-motion. Render sẵn HTML
để SEO; dữ liệu lấy từ API backend, phần lớn trang làm mới sau tối đa 60 giây (`revalidate`).

> Next.js 16 khác nhiều so với bản cũ. Trước khi sửa, đọc tài liệu đi kèm ở
> `node_modules/next/dist/docs/` (xem `AGENTS.md`). `README.md` của frontend
> còn ghi "Next.js 14" ở đầu — đã cũ.

| Thư mục / file | Chứa |
|---|---|
| `app/(site)/` | Các trang khách xem (mỗi thư mục là một đường dẫn: `tour-nuoc-ngoai`, `lam-visa`, `maldives`, `cam-nang`…) |
| `app/lib/api.js` | **Nơi duy nhất gọi API backend**; chuyển dữ liệu API sang dạng giao diện dùng |
| `app/lib/seo.js`, `app/sitemap.js`, `app/robots.js`, `app/llms.txt` | SEO: tiêu đề, JSON-LD, sitemap, file cho trợ lý AI |
| `app/lib/anh.js` + `next.config.mjs` | Tên miền ảnh được tối ưu — hai danh sách phải khớp nhau |
| `app/api/` | Chuyển tiếp đăng nhập / OTP / tra cứu đơn sang backend (giữ token trong cookie httpOnly) |
| `components/`, `components/pages/` | Giao diện từng phần / từng trang |
| `tests/` | Test (`node --test`) |

Biến môi trường frontend trên VPS do `docker-compose.prod.yml` tự đặt từ `.env`
của backend: `NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_SITE_URL` (lúc build) và
`PSV_NOI_BO` (lúc chạy). Mã Google Analytics / Google Ads / Facebook Pixel ghi
sẵn trong mã (`app/lib/gtag.js`, `components/GoogleTag.jsx`, `components/MetaPixel.jsx`).
Đổi gì cũng chạy lại `./scripts/trien-khai.sh` để build lại frontend.

## Chạy test

```bash
# Backend (trên máy dev)
docker compose exec app php artisan test
# Frontend
npm run lint && npm test
```

Backend có **5 test đang lỗi sẵn**, không phải do mã mới (xem file 04). Test
khác lỗi là lỗi thật.

## Quy ước khi sửa mã

- Đặt tên biến / hàm bằng tiếng Việt không dấu, chú thích tiếng Việt có dấu — như mã hiện có.
- Mỗi chức năng mới kèm test trong `tests/Feature/`.
- Sửa định dạng PHP bằng Pint **chỉ trên file đã sửa**: `vendor/bin/pint <file>`.
- Không commit `.env`, file sao lưu SQL, dữ liệu khách.
- Đổi cấu trúc CSDL bằng migration mới; không sửa migration đã chạy trên máy chủ.
