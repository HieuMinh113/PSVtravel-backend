# Tài khoản và quyền truy cập phải chuyển giao

> **Không ghi mật khẩu, mã khoá vào file này hay gửi qua Zalo / email / ảnh chụp
> màn hình.** Bàn giao trực tiếp hoặc qua trình quản lý mật khẩu dùng chung, và
> người nhận **đổi mật khẩu ngay** sau khi nhận.

Cột "Đang giữ" và "Ghi chú" để người bàn giao điền tay khi ký bàn giao.

## 1. Hạ tầng

| Tài khoản | Dùng để | Đang giữ | Cách chuyển / việc làm sau khi nhận |
|---|---|---|---|
| Nhà đăng ký tên miền `psvtravel.com` | Gia hạn tên miền, sửa DNS | ______ | Đổi email chủ tên miền hoặc chuyển tên miền sang tài khoản người nhận. **Hết hạn tên miền là web chết** — ghi ngày hết hạn: ______ |
| Tên miền cũ `psvtravel.com.vn` | Link cũ khách / Google còn giữ, tự chuyển về trang mới | ______ | Như trên. Giữ gia hạn để không mất lượt khách từ link cũ |
| Nhà cung cấp VPS | Bật / tắt / nâng cấp máy chủ, thanh toán | ______ | Đổi email + mật khẩu tài khoản nhà cung cấp; ghi ngày gia hạn: ______ |
| Đăng nhập VPS (SSH) | Triển khai, sao lưu | tài khoản `psv` | Thêm khoá SSH của người nhận vào `~/.ssh/authorized_keys`, **xoá khoá của người cũ**. Đăng nhập root đã khoá (xem `TRIEN-KHAI.md` bước 1b) |
| GitHub (2 repo) | Mã nguồn | ______ | Mời người nhận làm Admin repo, hoặc chuyển quyền sở hữu repo; gỡ quyền người cũ |

## 2. Dịch vụ bên ngoài

| Dịch vụ | Dùng để | Biến cấu hình (trong `.env`) | Việc làm sau khi nhận |
|---|---|---|---|
| Brevo (SMTP) | Gửi mail: OTP đăng ký, xác nhận đơn, thông báo | `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Chuyển tài khoản Brevo; **tạo khoá SMTP mới**, xoá khoá cũ, sửa `.env` trên VPS |
| Google Analytics 4 | Thống kê truy cập | Mã ghi sẵn trong `app/lib/gtag.js` (frontend) | Thêm người nhận làm Quản trị viên tài sản GA |
| Google Ads, Facebook Pixel | Đo quảng cáo | Mã ghi sẵn trong `components/GoogleTag.jsx`, `components/MetaPixel.jsx` (frontend) | Thêm quyền người nhận trong tài khoản quảng cáo. Đổi sang tài khoản khác thì sửa mã trong 2 file đó rồi triển khai lại |
| Google Search Console, Bing Webmaster | SEO, sitemap | — | Thêm người nhận làm chủ sở hữu |
| IndexNow | Báo Bing trang mới | `INDEXNOW_KEY` | Không cần đổi |
| Google Sheet của đối tác liên minh | Chỗ trống tour liên minh (đọc 5 phút/lần) | Nhập trong admin: **Tour & điều hành → Sheet liên minh** | Sheet do đối tác giữ, chỉ cần link còn mở xem được |
| Fanpage Facebook, Messenger, Zalo, YouTube, TikTok, Instagram | Liên kết trên web, video | Nhập trong admin: **Hệ thống → Cấu hình chung** | Thêm người nhận làm quản trị trang |

## 3. Trang quản trị (https://api.psvtravel.com/admin)

| Việc | Cách làm |
|---|---|
| Tạo tài khoản cho người nhận | Admin → **Hệ thống → Người dùng** → tạo mới, gán vai trò `super_admin` hoặc `admin` |
| Đổi mật khẩu `admin@psvtravel.com` | Nếu còn mật khẩu mặc định của `README.md` thì **đổi ngay** |
| Khoá / xoá tài khoản người nghỉ | Hệ thống → Người dùng. Không xoá vai trò `customer` (khách đăng ký web cần) |
| Xác thực 2 lớp (2FA) mất điện thoại | Trên VPS: `docker compose -f docker-compose.prod.yml exec app php artisan psv:tat-2fa <email>` |
| Mã khoá trợ lý AI (MCP) | Mỗi Admin tự tạo ở **Hệ thống → Kết nối AI (MCP)**. Người nghỉ: bấm **Thu hồi** mọi mã của họ |

Vai trò có sẵn: `super_admin` (toàn quyền), `admin`, `sale`, `ke_toan`,
`marketing`, `dieu_hanh`, `visa`, `staff`, `customer` (khách web). Sửa quyền
từng vai trò ở **Hệ thống → Vai trò** (tick ô quyền).

## 4. Bí mật nằm trên VPS (không có trong GitHub)

File `/opt/psvtravel/psvtravel-backend/.env` (quyền `600`) giữ toàn bộ bí mật:

| Biến | Là gì | Đổi được không |
|---|---|---|
| `APP_KEY` | Khoá mã hoá của Laravel | **Không tự đổi** — đổi là mất phiên đăng nhập và dữ liệu đã mã hoá (mã 2FA). Muốn đổi phải dùng `APP_PREVIOUS_KEYS` |
| `DB_PASSWORD` | Mật khẩu Postgres | Chỉ đổi khi biết cách đổi trong Postgres trước (xem `TRIEN-KHAI.md` bước 6) |
| `REDIS_PASSWORD` | Mật khẩu Redis | Đổi rồi khởi động lại container |
| `MAIL_PASSWORD` | Khoá SMTP Brevo | Nên đổi khi bàn giao |
| `PSV_NOI_BO` | Khoá để frontend gọi API không bị giới hạn tần suất | Đổi được, chạy lại `./scripts/trien-khai.sh` |
| `INDEXNOW_KEY` | Khoá IndexNow | Không cần đổi |

Người bàn giao chép file `.env` cho người nhận qua kênh an toàn (USB, trình
quản lý mật khẩu). Bản sao lưu cơ sở dữ liệu cũng nằm trên VPS, ở `/opt/psvtravel/backups`.
