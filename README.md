# PSV Travel — Backend

API và trang quản trị cho website đặt tour PSV Travel.
Laravel 13 · Filament 5 · PostgreSQL 16 · Redis 7 · chạy bằng Docker.

Frontend nằm ở repo riêng: `psvtravel-frontend`.

---

## Dựng môi trường lần đầu

Cần cài sẵn **Docker Desktop**. Không cần cài PHP hay PostgreSQL trên máy.

```bash
git clone <url-repo> psvtravel-backend
cd psvtravel-backend

# 1. Tạo file cấu hình
cp .env.example .env          # Windows PowerShell: copy .env.example .env

# 2. Bật các container
docker compose up -d --build  # lần đầu build mất khoảng 3-5 phút

# 3. Cài thư viện PHP
docker compose exec app composer install

# 4. Sinh khoá ứng dụng
docker compose exec app php artisan key:generate

# 5. Tạo bảng trong cơ sở dữ liệu
docker compose exec app php artisan migrate

# 6. Tạo vai trò + tài khoản quản trị
docker compose exec app php artisan db:seed --class=Database\\Seeders\\RoleSeeder

# 7. Tạo trang tĩnh và các mục Cài đặt
docker compose exec app php artisan db:seed --class="Modules\Page\Database\Seeders\PageDatabaseSeeder"

# 8. Cho phép website đọc ảnh đã upload
docker compose exec app php artisan storage:link

# 9. Cấp đủ quyền cho tài khoản Toàn quyền (máy mới cài chưa có quyền nào)
docker compose exec app php artisan shield:generate --all --panel=admin --option=permissions
```

Xong: trang quản trị ở **http://localhost:8000/admin**

Lệnh `docker compose up` bật luôn container `scheduler` — nó chạy các việc hẹn
giờ (đọc sheet liên minh 5 phút/lần...). Xem nó chạy: `docker compose logs -f scheduler`.

### Tài khoản có sẵn sau bước 6

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Toàn quyền | `admin@psvtravel.com` | `Admin@123456` |
| Nhân viên (quyền hạn chế) | `nhanvien@psvtravel.com` | `NhanVien@123456` |

> Đổi mật khẩu ngay khi đưa lên môi trường thật.

---

## Dữ liệu để kiểm thử

Muốn có sẵn tour, banner, đánh giá... để tester có cái mà bấm:

```bash
docker compose exec app php artisan db:seed --class=Database\\Seeders\\DemoSeeder
```

Đây là **dữ liệu giả**, chỉ dùng trên máy dev và staging. Ảnh lấy từ picsum.photos nên cần có mạng.

Xoá sạch để bắt đầu nhập dữ liệu thật:

```bash
docker compose exec app php artisan psv:don-du-lieu-mau --force
docker compose exec app php artisan psv:don-du-lieu-mau --don-hang --force   # xoá luôn đơn đặt tour
```

Lệnh này giữ nguyên tài khoản, phân quyền, Cài đặt và trang tĩnh.

---

## Liên minh (dành cho điều hành)

Menu **Điều hành** trong trang quản trị:

- **Sheet liên minh** — dán link Google Sheet chỗ trống của từng đối tác. Sheet
  phải để chế độ *Bất kỳ ai có đường liên kết đều xem được*. Lưu xong máy đọc
  thử ngay; sau đó tự đọc lại mỗi 10–15 phút.
- **Tra chỗ liên minh** — mọi ngày khởi hành của mọi đối tác trong một bảng: lọc
  theo ngày, số chỗ còn, đối tác, tình trạng; tìm theo tên tour.
- **Chuông thông báo** (góc phải) — có chỗ trở lại, sắp hết chỗ, tour PSV đang
  bán vừa hết chỗ, đối tác thêm ngày mới, sheet bị khoá / lỗi.
- **Nối với tour trên web** — trong trang sửa tour, ô *Lấy số chỗ theo tour liên
  minh*. Nối xong, các ngày đi trùng ngày trong sheet tự cập nhật “Còn N chỗ”
  trên website; tab *Lịch khởi hành* có nút *Lấy ngày đi từ liên minh*.

Ai được xem: vai trò `dieu_hanh` (cấp ở Người dùng), cùng super_admin và admin.

Đọc tay để kiểm tra:

```bash
docker compose exec app php artisan lien-minh:dong-bo --tat-ca   # đọc lại mọi sheet ngay
docker compose exec app php artisan lien-minh:dong-bo --nguon=1  # chỉ sheet số 1
```

Chỉnh trong `.env` nếu cần: `LIEN_MINH_CHU_KY_PHUT=10` (bao lâu đọc lại một
sheet), `LIEN_MINH_NGUONG_SAP_HET=3` (còn từ chừng này chỗ trở xuống thì báo
"sắp hết").

---

## Xem mã OTP khi chưa cấu hình mail

Mặc định `MAIL_MAILER=log`, mail không gửi đi đâu mà ghi vào file:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

Mã OTP nằm trong nội dung mail ghi ở đó. Muốn gửi mail thật thì mở phần Brevo trong `.env`.

---

## Lệnh hay dùng

```bash
docker compose exec app php artisan optimize         # xoá cache CŨ và tạo lại — chạy sau mỗi lần pull
docker compose exec app php artisan filament:optimize # tăng tốc trang quản trị, xem mục dưới
docker compose exec app php artisan migrate:status   # xem migration nào đã chạy
docker compose logs -f app                           # xem log ứng dụng
docker compose down                                  # tắt (dữ liệu vẫn giữ)
docker compose down -v                               # tắt và XOÁ SẠCH cơ sở dữ liệu
```

---

## Trang quản trị chạy chậm

Website khách xem là HTML dựng sẵn nên nhanh. Trang quản trị (Filament) dựng lại
bằng PHP ở mỗi lần bấm, chạm tới hàng nghìn file — trên Docker Windows mỗi lượt
đọc file phải đi qua cầu nối giữa Windows và Linux nên rất tốn.

Ba lệnh này giải quyết phần lớn:

```bash
docker compose exec app composer dump-autoload --optimize
docker compose exec app php artisan optimize
docker compose exec app php artisan filament:optimize
```

`filament:optimize` là lệnh quan trọng nhất mà hay bị bỏ sót: nó gom sẵn danh
sách component và biểu tượng của Filament. Không có nó, mỗi lần mở một trang
quản trị là hệ thống phải đi dò lại toàn bộ.

**Sau khi chạy ba lệnh trên, sửa mã sẽ không thấy đổi ngay.** Muốn quay lại chế
độ phát triển bình thường:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan filament:optimize-clear
```

---

## Lỗi hay gặp

**`could not translate host name "postgres"`**
Bạn đang chạy `php artisan` từ Windows chứ không phải trong container. Thêm `docker compose exec app` vào đầu lệnh.

**`Class "Redis" not found`**
Cũng do chạy ngoài container. PHP trên Windows không có extension redis.

**Câu hỏi xác nhận (yes/no) gõ gì cũng thành "no"**
Terminal Windows qua `docker compose exec` không có TTY thật. Thêm cờ `--force` vào lệnh.

**Ảnh upload không hiện ở website**
Chưa chạy `php artisan storage:link` (bước 8).

**Website báo `ECONNREFUSED 127.0.0.1:8000`**
Backend chưa bật. Chạy `docker compose up -d`.

---

## Cấu trúc

Dự án chia module bằng `nwidart/laravel-modules`. Mỗi module tự chứa model, migration, controller API và routes:

```
Modules/
  Tour/       tour, lịch trình, đợt khởi hành, ảnh
  Booking/    đơn đặt tour, thanh toán, tra cứu đơn
  Review/     đánh giá của khách
  Banner/     banner khuyến mãi + ảnh vòng xoay
  Visa/       dịch vụ visa
  Flight/     hãng bay, vé máy bay
  Guide/      cẩm nang du lịch
  Moment/     khoảnh khắc du khách
  Category/   danh mục tour
  Page/       trang tĩnh + Cài đặt hệ thống
```

Giao diện quản trị (Filament) nằm ở `app/Filament/Resources/`, tách khỏi module.

Toàn bộ API công khai có tiền tố `/api/v1`.
