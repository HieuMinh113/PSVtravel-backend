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

# 9. (Bước 6 đã tự làm) Tạo lại quyền + cấp hết cho super_admin — chạy khi
#    tài khoản admin thiếu mục trong menu, hoặc sau khi thêm mục quản trị mới
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

Có sẵn để thử các việc nghiệp vụ:

| Tài khoản | Mật khẩu | Dùng để thử |
|---|---|---|
| `visa1@psvtravel.com` | `NhanVien@123456` | Nhân viên visa: chỉ thấy hồ sơ của mình + hồ sơ chưa ai nhận |
| `visa2@psvtravel.com` | `NhanVien@123456` | Nhân viên visa thứ hai (giữ đoàn Hàn Quốc) |
| `dieuhanh@psvtravel.com` | `NhanVien@123456` | Điều hành: tra chỗ liên minh |
| `sale@psvtravel.com` | `NhanVien@123456` | Kinh doanh: tạo đơn, ghi khoản thu kèm ảnh chuyển khoản, thống kê đơn của mình |
| `ketoan@psvtravel.com` | `NhanVien@123456` | Kế toán: Duyệt khoản thu, xem thống kê doanh số của mọi người |
| `khach@example.com` | `Khach@123456` | Khách đăng nhập website: tab Hồ sơ visa, đơn đặt tour |

- **Tour** (14 tour): trong nước Miền Bắc / Trung / Nam / Tây Nguyên, nước ngoài
  Đông Nam Á / Đông Bắc Á / Trung Quốc / Châu Âu; mỗi tour 2–3 đợt khởi hành
  (có đợt **hết chỗ**), lịch trình từng ngày, ảnh, đánh giá; kèm 8 điểm đến nối
  vào danh mục. Giá là số mẫu.
- **Hồ sơ visa** (8 hồ sơ, tên giả): 1 hồ sơ khách nộp web chưa ai nhận; 1 hồ sơ
  Trung Quốc hẹn nộp sau 2 ngày, còn nợ phí, hộ chiếu sắp hết hạn; đoàn Hàn
  Quốc 3 người (thử xuất ZIP cả đoàn); đã nộp / đậu / trượt. File đính kèm là
  ảnh ghi chữ "FILE MAU".
- **Đơn đặt tour**: có tỷ lệ cọc; 1 đơn tới hạn nhắn khách hôm nay (nút
  "Nhắc đóng tiền", chuông, lọc "Cần nhắc khách"); 1 đơn đã trả đủ; 1 đơn khách
  đặt từ web chưa xác nhận.
- Tài khoản mẫu **không** được tạo khi `APP_ENV=production`.
- Chạy lại lệnh nhiều lần không sinh trùng.

Xoá sạch để bắt đầu nhập dữ liệu thật:

```bash
docker compose exec app php artisan psv:don-du-lieu-mau --force
docker compose exec app php artisan psv:don-du-lieu-mau --don-hang --force   # xoá luôn đơn đặt tour + hồ sơ visa
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

**Máy tự hiểu được** (đã thử với 10 sheet đối tác thật — V1, VNA, AZ, M Tour,
J Travel HCM/HN, Hanvina, VGI, VVT, Triều Hảo):

- Cột nằm ở đâu cũng được — nhận ra theo chữ tiêu đề (NGÀY KHỞI HÀNH, LỊCH KH,
  KH, GIÁ, GIÁ KHUYẾN MÃI, COM / HH / COM AG, NHẬN, CÒN NHẬN, SIZE, SURE, HOLD,
  TUYẾN DU LỊCH, HÀNH TRÌNH, CHƯƠNG TRÌNH, THỊ TRƯỜNG, HK, HÀNG KHÔNG...).
- Ngày: `08/04`, `11.01`, `Tháng 10: 15, 22, 29`, cột tháng + cột ngày riêng
  (`Tháng 1` | `21`, `12; 27`, `8.15.22`), `06 - 10/02/2027`, `THỨ 5` (hằng tuần).
  Năm suy theo tựa tab, mục "THÁNG 10/2026", mốc Tết ("MÙNG 1 TẾT") và thứ tự
  các dòng — không sinh ngày đi giả từ lịch cũ.
- Tiền: `21.990` (nghìn), `16.990K`, `800k`, `2tr5`; giá KM làm giá chính,
  giá gốc gạch ngang.
- Hết chỗ: `FULL`, `-`, `ĐÓNG`, `ĐÓNG ĐOÀN`, `HỦY`; ngày tô đỏ nếu bật tuỳ chọn.

**Trang sửa sheet** có thêm: "Ngày tô ĐỎ có nghĩa là hết chỗ?", bỏ qua tab
(phí visa, vé máy bay...), khai cột bằng tay cho tab không có dòng tiêu đề, và
**báo cáo từng tab** của lần đọc gần nhất (đọc được bao nhiêu ngày đi, ô ngày
nào chưa hiểu kèm số dòng). Sheet Hanvina được khai sẵn các tuỳ chọn này.

Đọc tay để kiểm tra:

```bash
docker compose exec app php artisan lien-minh:dong-bo --tat-ca   # đọc lại mọi sheet ngay
docker compose exec app php artisan lien-minh:dong-bo --nguon=1  # chỉ sheet số 1
```

Chỉnh trong `.env` nếu cần: `LIEN_MINH_CHU_KY_PHUT=10` (bao lâu đọc lại một
sheet), `LIEN_MINH_NGUONG_SAP_HET=3` (còn từ chừng này chỗ trở xuống thì báo
"sắp hết").

## Hồ sơ visa (dành cho bộ phận visa)

Menu **Visa** trong trang quản trị:

- **Hồ sơ visa** — mỗi khách một hồ sơ (cả nhà / cả đoàn ghi chung ô *Nhóm*).
  Gồm thông tin khách, nước + mục đích + đối tượng, checklist giấy tờ (đánh dấu
  *Chưa có / Đã nhận / Không cần*), file scan, lịch hẹn, kết quả, tiền thu / chi
  / khách đã trả. Mỗi case một giá, không theo bảng giá cố định.
  - Nhập nước + mục đích + đối tượng là máy tự chép checklist từ mẫu khớp nhất;
    sửa thêm / bớt tuỳ case, không ảnh hưởng mẫu.
  - Nút **Tin nhắn giấy thiếu** soạn sẵn đoạn tin gửi khách qua Zalo.
  - Tab *Đang xử lý / Chờ kết quả / Đã xong*; lọc *Hồ sơ của tôi*, *lịch hẹn 7
    ngày tới*, *khách còn nợ*. Số cạnh menu = hồ sơ có lịch hẹn trong 3 ngày.
  - Cảnh báo hộ chiếu còn hạn dưới 6 tháng tính từ ngày đi.
- **Mẫu checklist** — bản số hoá thư mục "thủ tục visa các nước" trên Drive. Có
  sẵn vài mẫu (Trung Quốc, Ai Cập, Hàn Quốc) để sửa tiếp; nút *Nhân bản* để làm
  biến thể cho đối tượng khác.

File scan giấy tờ khách nằm ở `storage/app/private/ho-so-visa` (không công khai),
chỉ mở được bằng link có chữ ký do trang quản trị tạo, qua đường dẫn
`/tep-rieng/...`. Xoá hẳn hồ sơ thì file cũng bị xoá.

**Khách nộp trên website**: trang *Làm visa → [nước] → Nộp hồ sơ online*
(`/lam-visa/{slug}/nop-ho-so`). Không bắt buộc đăng nhập; đang đăng nhập thì
hồ sơ gắn vào tài khoản và khách xem trạng thái + giấy tờ còn thiếu ở *Tài
khoản → Hồ sơ visa*. Gửi kèm tối đa 10 file ảnh/PDF, mỗi file 10MB (gửi từng
file một vì nginx nhận tối đa 20MB mỗi lần). Có hồ sơ mới: chuông báo cho nhân
viên visa + admin, email xác nhận cho khách (nếu có email).

**Giấy tờ theo từng dòng + xuất ZIP**: mỗi dòng checklist có ô tải file riêng
(gắn file là tự "Đã nhận"); khách trên web cũng tải vào đúng ô từng giấy. Nút
**Xuất hồ sơ (ZIP)** (trang sửa hồ sơ, hoặc chọn nhiều hồ sơ cùng nhóm trong
bảng → *Xuất ZIP đoàn*) cho ra:

```
gđ anh tuấn.zip
  DANH SÁCH gđ anh tuấn.xlsx
  NGUYỄN TÔ THỤY BẢO CHÂU/
    Hộ chiếu.pdf, Căn cước.jpg, Hợp đồng lao động (1).pdf, (2).pdf…
    Giấy tờ khác/…            (file chưa xếp vào dòng nào)
    Phiếu thông tin.docx      (theo phiếu thông tin xin visa Nhật)
    GIẤY TỜ CÒN THIẾU.txt     (chỉ khi còn thiếu)
```

Xuất lúc nào cũng được; còn thiếu thì báo trước. File ZIP tải qua link có chữ ký
10 phút, chỉ người bấm xuất tải được, tải xong tự xoá (chưa tải thì lịch chạy
dọn sau 1 ngày). Mỗi lần xuất được ghi nhật ký hoạt động.

Ai thấy gì:

- Vai trò `visa` (cấp ở Người dùng): chỉ thấy hồ sơ mình phụ trách + hồ sơ
  khách nộp **chưa ai nhận** (tab *Chưa ai nhận*, bấm **Nhận hồ sơ** — ai bấm
  trước được trước). Không chuyển hồ sơ cho người khác được.
- super_admin, admin (quyền `ViewAll:VisaCase`): thấy mọi hồ sơ, giao / chuyển
  hồ sơ ở ô *Nhân viên phụ trách*.

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
