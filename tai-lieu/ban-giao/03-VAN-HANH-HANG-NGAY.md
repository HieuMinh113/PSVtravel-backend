# Vận hành hằng ngày (máy chủ)

Mọi lệnh chạy trên VPS sau khi đăng nhập SSH, trong thư mục backend:

```bash
ssh psv@<IP-VPS>
cd /opt/psvtravel/psvtravel-backend
```

Các lệnh bên dưới viết tắt `DC` — chạy dòng này một lần mỗi lần đăng nhập để máy hiểu `DC`:

```bash
alias DC='docker compose -f docker-compose.prod.yml'
```

> Dán lệnh vào cửa sổ dòng lệnh mà đầu dòng hiện thêm `^[[200~` thì xoá phần đó rồi mới Enter.

## 1. Đưa bản mới lên (sau khi đã đẩy mã lên nhánh `main` trên GitHub)

```bash
./scripts/trien-khai.sh
```

Script tự làm hết: lấy mã mới cả **backend lẫn frontend**, cài thư viện,
chạy migration, build lại frontend, khởi động lại container, xoá bộ nhớ đệm.
Gặp lỗi là dừng ngay, website giữ bản cũ.

- **Không sửa mã trực tiếp trên VPS**: script dùng `git reset --hard` nên mọi
  sửa tay trên máy chủ (trừ `.env`, `vendor/`, ảnh tải lên) bị xoá ở lần triển khai sau.
- Script tự sinh `PSV_NOI_BO` nếu `.env` còn thiếu.
- Chi tiết từng bước và lỗi hay gặp: `TRIEN-KHAI.md`, mục "Cập nhật website sau này" và "Khi gặp sự cố".

## 2. Sao lưu

### Cơ sở dữ liệu — tự động

```bash
crontab -l          # phải thấy dòng có sao-luu-csdl.sh
ls -lh /opt/psvtravel/backups
```

- Chạy 3 giờ sáng mỗi ngày, giữ 14 bản (`scripts/sao-luu-csdl.sh`).
- Không thấy dòng cron → cài theo `TRIEN-KHAI.md` bước 11.
- Bản sao lưu nằm **cùng máy** với CSDL: máy hỏng là mất cả hai. Mỗi tuần tải
  một bản về máy công ty (chạy trên máy tính của bạn, không phải trên VPS):

  ```bash
  scp psv@<IP-VPS>:/opt/psvtravel/backups/*.gz .
  ```

### Ảnh và tệp tải lên — CHƯA tự động

`sao-luu-csdl.sh` **không** sao lưu tệp. Tệp nằm ở:

| Thư mục | Chứa |
|---|---|
| `storage/app/public` | Ảnh tour, banner, cẩm nang… (công khai) |
| `storage/app/private` | Hồ sơ visa khách, CV ứng viên, ảnh chấm công, chứng từ khoản thu (**dữ liệu cá nhân**) |

Sao lưu tay (nên làm hằng tuần, cất bản sao ở nơi có khoá — có dữ liệu cá nhân):

```bash
tar czf /opt/psvtravel/backups/tep_$(date +%Y%m%d).tar.gz -C /opt/psvtravel/psvtravel-backend storage/app/public storage/app/private
```

## 3. Khôi phục cơ sở dữ liệu

Làm thử trên máy dev trước khi làm trên VPS. Thay `<DB_USERNAME>`, `<DB_DATABASE>`
bằng giá trị trong `.env`, `<FILE>` bằng tên bản sao lưu.

```bash
DC stop app queue scheduler
DC exec postgres dropdb -U <DB_USERNAME> <DB_DATABASE>
DC exec postgres createdb -U <DB_USERNAME> <DB_DATABASE>
gunzip -c /opt/psvtravel/backups/<FILE>.sql.gz | DC exec -T postgres psql -U <DB_USERNAME> <DB_DATABASE>
DC start app queue scheduler
DC exec app php artisan optimize:clear
```

`dropdb` **xoá sạch dữ liệu hiện tại** — chắc chắn có bản sao lưu mới nhất trước khi chạy.

## 4. Xem tình trạng và lỗi

```bash
DC ps                                  # container nào đang chạy / đã dừng
DC logs --tail=100 app                 # lỗi API, trang quản trị
DC logs --tail=100 frontend            # lỗi website
DC logs --tail=100 nginx               # truy cập, lỗi 502
DC logs --tail=100 scheduler           # việc hẹn giờ
ls storage/logs/                       # nhật ký Laravel theo ngày, giữ 14 ngày
./scripts/kiem-tra-tai.sh              # tải website theo giờ (chỉ đọc)
```

Trong admin: **Hệ thống → Nhật ký hoạt động** — ai sửa gì, lúc nào.

## 5. Việc máy tự chạy

Container `scheduler` phải luôn chạy (`DC ps`). Nó lo:

| Giờ | Việc | Lệnh |
|---|---|---|
| 5 phút/lần | Đọc lại Google Sheet chỗ trống của đối tác liên minh | `lien-minh:dong-bo` |
| 08:00 và 13:00 | Báo chuông đơn tour tới hạn nhắn khách đóng tiền | `don-tour:nhac-nhan-khach` |
| 02:30 | Xoá ảnh chấm công cũ hơn 3 tháng (Nghị định 13) | `cham-cong:don-anh` |
| Hằng ngày | Dọn nhật ký hoạt động cũ | `activitylog:clean` |
| Hằng ngày | Xoá file in voucher đã xuất | `voucher:don-file-xuat` |

Ngoài `scheduler`:

| Việc | Ai chạy |
|---|---|
| Gia hạn chứng chỉ HTTPS | Container `certbot`, tự kiểm tra 12 giờ/lần |
| Sao lưu CSDL 03:00 | `crontab` của tài khoản `psv` (mục 2) |

## 6. Lệnh tiện ích

Chạy dạng `DC exec app php artisan <lệnh>`. Lệnh có `--xem` thì chạy thử với `--xem` trước.

| Lệnh | Việc |
|---|---|
| `psv:tat-2fa <email>` | Tắt 2FA admin cho người mất điện thoại |
| `psv:bao-indexnow` | Báo Bing mọi trang trong sitemap |
| `psv:link-cu` / `./scripts/nhap-link-cu.sh 30 --xem` | Khớp link 404 của web cũ sang trang mới |
| `psv:sua-link-noi-dung --xem` | Sửa link nội bộ sai trong nội dung bài |
| `psv:sua-slug --xem` | Sửa đường dẫn sai định dạng (tự chuyển hướng link cũ) |
| `psv:thu-nho-anh --xem` | Thu nhỏ ảnh quá lớn trong nội dung bài |
| `psv:kiem-tra-maldives`, `psv:kiem-tra-voucher` | Tìm lý do không thấy mục Maldives / Voucher trong admin |
| `./scripts/dem-bot-ai.sh 30` | Đếm bot AI và lượt khách đến từ câu trả lời của AI |
| `psv:don-du-lieu-mau` | Xoá dữ liệu mẫu. **Không chạy trên máy chủ thật khi đã có dữ liệu thật** — nếu bắt buộc: sao lưu trước, chạy với `--xem` để xem sẽ xoá gì |
| `shield:generate --all --panel=admin --option=permissions` | Tạo lại danh sách quyền sau khi thêm mục quản trị mới |
