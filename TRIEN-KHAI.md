# Triển khai PSV Travel lên máy chủ

Hướng dẫn cho VPS Ubuntu 24.04. Làm lần lượt từ trên xuống, mỗi bước chạy xong
mới sang bước sau.

Thay `psvtravel.com` bằng tên miền thật của bạn ở mọi chỗ xuất hiện.

---

## Bước 0 — Mở cửa sổ dòng lệnh và đăng nhập vào VPS

Tất cả các lệnh trong tài liệu này đều gõ vào **một cửa sổ đen** trên máy tính
của bạn, đang kết nối tới VPS. Cách mở:

**Trên Windows:** bấm phím `Windows`, gõ `powershell`, mở **Windows PowerShell**.

Lấy mật khẩu: vào trang VinaHost → Dịch vụ → VPS của bạn → dòng **Mật khẩu**,
bấm nút con mắt màu xanh để hiện, bôi đen rồi Ctrl+C.

Trong PowerShell gõ:

```
ssh root@103.109.187.16
```

Lần đầu nó hỏi:

```
The authenticity of host '103.109.187.16' can't be established.
ED25519 key fingerprint is SHA256:...
Are you sure you want to continue connecting (yes/no/[fingerprint])?
```

Gõ `yes` rồi Enter. Sau đó nó hỏi mật khẩu:

```
root@103.109.187.16's password:
```

Bấm **chuột phải** để dán (Ctrl+V không dùng được ở đây), rồi Enter.

> Lúc dán mật khẩu **màn hình không hiện gì cả** — không có dấu sao, không có
> chấm. Đó là bình thường, không phải bàn phím hỏng. Cứ dán rồi Enter.

Vào được sẽ thấy dòng nhắc như:

```
root@vps59781:~#
```

Từ giờ, "chạy lệnh" nghĩa là gõ vào sau dấu `#` này rồi Enter.

---

## Bước 1 — Kiểm tra VPS chạy được Docker

```bash
systemd-detect-virt
```

- Trả về `kvm` → tốt, làm tiếp.
- Trả về `openvz` hoặc `lxc` → **dừng lại**, VPS này không chạy Docker được,
  liên hệ VinaHost đổi sang gói KVM.

---

## Bước 1b — Tạo tài khoản riêng, khoá đăng nhập root

Đăng nhập bằng `root` là thói quen nguy hiểm: gõ nhầm một lệnh là hỏng máy, và
mọi công cụ dò mật khẩu trên Internet đều thử `root` đầu tiên.

**1. Tạo tài khoản mới:**

```bash
adduser psv
```

Nó hỏi mật khẩu — **tự đặt một mật khẩu mới, dài và khó đoán**, gõ hai lần
(màn hình vẫn không hiện gì). Sau đó hỏi họ tên, số phòng, số điện thoại...
cứ Enter bỏ qua hết, cuối cùng gõ `Y` rồi Enter.

**Ghi mật khẩu này lại ngay** — mất là không vào được VPS nữa.

**2. Cho tài khoản đó quyền quản trị:**

```bash
usermod -aG sudo psv
```

**3. Cài fail2ban để chặn dò mật khẩu:**

```bash
apt update && apt install -y fail2ban
systemctl enable --now fail2ban
```

Sau 5 lần nhập sai mật khẩu, địa chỉ IP đó bị chặn 10 phút. Cần thiết vì VPS
đang cho đăng nhập bằng mật khẩu.

**4. Thử tài khoản mới TRƯỚC KHI khoá root:**

Mở một cửa sổ PowerShell **mới** (giữ nguyên cửa sổ cũ, đừng đóng), gõ:

```
ssh psv@103.109.187.16
```

Nhập mật khẩu bạn vừa đặt ở mục 1. Vào được sẽ thấy dòng nhắc đổi thành:

```
psv@vps59781:~$
```

> Dấu `$` thay vì `#` nghĩa là đang dùng tài khoản thường — đúng rồi.
> **Chưa vào được thì đừng làm bước 5**, quay lại cửa sổ cũ kiểm tra lại.

**5. Khoá đăng nhập root:**

Ở cửa sổ mới (tài khoản psv), chạy:

```bash
sudo nano /etc/ssh/sshd_config
```

Nó hỏi mật khẩu — nhập mật khẩu của `psv`. Một trình soạn thảo văn bản mở ra.
Dùng phím mũi tên tìm dòng có chữ `PermitRootLogin`, sửa thành:

```
PermitRootLogin no
```

Dòng đó có thể đang là `#PermitRootLogin prohibit-password` — xoá dấu `#` ở
đầu và sửa phần sau thành `no`.

Lưu và thoát: `Ctrl+O` → Enter → `Ctrl+X`.

```bash
sudo systemctl restart ssh
```

Xong. Từ giờ đăng nhập bằng `ssh psv@103.109.187.16`.

> Các bước sau cần quyền quản trị, nên nhiều lệnh phải thêm `sudo` ở đầu.
> Tài liệu đã ghi sẵn.

---

## Bước 2 — Cài Docker

```bash
sudo apt update && sudo apt upgrade -y
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker psv
```

Thoát ra rồi đăng nhập lại để nhóm `docker` có hiệu lực:

```bash
exit
```

Rồi ở PowerShell gõ lại `ssh psv@103.109.187.16`. Kiểm tra Docker chạy được:

```bash
docker run --rm hello-world
```

---

## Bước 3 — Tường lửa

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status
```

> **Lưu ý quan trọng:** Docker publish cổng **đi vòng qua UFW**. Nghĩa là nếu
> file compose có `ports: 5432:5432` thì UFW chặn cũng vô ích, cả Internet vẫn
> vào được Postgres. Vì vậy `docker-compose.prod.yml` cố ý **không mở** cổng
> Postgres và Redis. Đừng thêm vào.

---

## Bước 4 — Trỏ tên miền

Vào trang quản lý tên miền `psvtravel.com` (nơi bạn mua tên miền), tìm mục
**Quản lý DNS** hoặc **DNS Records**, thêm **ba** bản ghi:

| Loại | Tên | Trỏ tới |
|------|-----|---------|
| A | `@` | `103.109.187.16` |
| A | `www` | `103.109.187.16` |
| A | `api` | `103.109.187.16` |

Chờ 5–30 phút rồi kiểm tra trên VPS:

```bash
sudo apt install -y dnsutils
dig +short psvtravel.com
dig +short api.psvtravel.com
```

Cả hai phải trả về `103.109.187.16`. **Chưa đúng thì đừng sang bước xin chứng
chỉ** — Let's Encrypt giới hạn 5 lần thất bại mỗi giờ.

---

## Bước 5 — Tải mã nguồn

```bash
sudo mkdir -p /opt/psvtravel && sudo chown psv:psv /opt/psvtravel
cd /opt/psvtravel

git clone https://github.com/HieuMinh113/PSVtravel-backend.git psvtravel-backend
git clone https://github.com/HieuMinh113/PSVtravel-frontend.git psvtravel-frontend
```

Hai thư mục phải nằm cạnh nhau đúng như trên — file compose tìm frontend theo
đường dẫn `../psvtravel-frontend`.

---

## Bước 6 — Cấu hình

```bash
cd /opt/psvtravel/psvtravel-backend
cp .env.production.example .env
```

Sinh ba chuỗi bí mật:

```bash
openssl rand -base64 32    # dùng cho DB_PASSWORD
openssl rand -base64 32    # dùng cho REDIS_PASSWORD
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show
```

```bash
nano .env
```

Điền hết các chỗ `ĐIỀN_VÀO_ĐÂY` và sửa `PSV_DOMAIN`, `PSV_API_DOMAIN`,
`APP_URL`, `FRONTEND_URL` theo tên miền thật.

Khoá SMTP Brevo phải **tạo mới** — khoá cũ đã lộ trong lịch sử git, vào Brevo
xoá đi rồi tạo khoá khác.

Khoá quyền đọc file `.env`:

```bash
chmod 600 .env
```

---

## Bước 7 — Xin chứng chỉ HTTPS

nginx không khởi động được khi chưa có file chứng chỉ, mà certbot lại cần
nginx đang chạy để xác minh. Gỡ vòng lặp này bằng một chứng chỉ tạm:

```bash
cd /opt/psvtravel/psvtravel-backend
source .env

# 1. Tạo chứng chỉ tạm để nginx chịu khởi động
docker compose -f docker-compose.prod.yml run --rm --entrypoint "\
  sh -c 'mkdir -p /etc/letsencrypt/live/$PSV_DOMAIN && \
  openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
    -keyout /etc/letsencrypt/live/$PSV_DOMAIN/privkey.pem \
    -out /etc/letsencrypt/live/$PSV_DOMAIN/fullchain.pem \
    -subj /CN=localhost'" certbot

# 2. Build và khởi động nginx
#    nginx phải phân giải được tên "app" và "frontend" ngay lúc đọc cấu hình,
#    nên hai container đó phải tồn tại trước. Lệnh này tự build chúng —
#    lần đầu mất 5–10 phút, cứ để chạy.
docker compose -f docker-compose.prod.yml up -d --build nginx

# 3. Xin chứng chỉ thật cho cả ba tên miền
docker compose -f docker-compose.prod.yml run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d $PSV_DOMAIN -d www.$PSV_DOMAIN -d $PSV_API_DOMAIN \
  --email hieuvadanh091@gmail.com --agree-tos --no-eff-email --force-renewal

# 4. Nạp lại nginx với chứng chỉ thật
docker compose -f docker-compose.prod.yml restart nginx
```

Chứng chỉ tự gia hạn — container `certbot` kiểm tra 12 tiếng một lần.

---

## Bước 8 — Khởi động toàn bộ hệ thống

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Lần đầu mất 5–10 phút vì phải build cả PHP lẫn Next.js. Theo dõi:

```bash
docker compose -f docker-compose.prod.yml ps
```

Tất cả phải ở trạng thái `Up`.

---

## Bước 9 — Dựng cơ sở dữ liệu

```bash
C="docker compose -f docker-compose.prod.yml"

$C exec app php artisan migrate --force
$C exec app php artisan db:seed --force
$C exec app php artisan shield:generate --all --panel=admin --option=permissions
$C exec app php artisan storage:link
$C exec app php artisan optimize
$C exec app php artisan filament:optimize
```

> `--option=permissions` là bắt buộc. Bỏ đi thì Shield sẽ **ghi đè** các file
> policy viết tay trong `app/Policies/`, làm hỏng phần xem tin nhắn liên hệ.

Build lại frontend một lần nữa:

```bash
$C up -d --build frontend
```

Lúc ở bước 8, frontend được build khi API còn chưa có dữ liệu, nên các trang
tĩnh sinh ra bị rỗng. Chúng tự đầy lại sau 60 giây (cơ chế ISR), nhưng build
lại thì có ngay, khỏi phải chờ.

---

## Bước 10 — Đổi mật khẩu quản trị NGAY

Mở `https://api.psvtravel.com/admin` và đăng nhập:

```
admin@psvtravel.com  /  Admin@123456
```

Vào **góc phải trên → Hồ sơ → đổi mật khẩu**. Làm cho cả tài khoản
`nhanvien@psvtravel.com` (`NhanVien@123456`).

Hai mật khẩu này nằm công khai trong mã nguồn trên GitHub. Chưa đổi thì bất kỳ
ai đọc repo cũng vào được trang quản trị.

---

## Bước 11 — Sao lưu tự động hằng ngày

```bash
crontab -e
```

Thêm dòng:

```
0 3 * * * cd /opt/psvtravel/psvtravel-backend && ./scripts/sao-luu-csdl.sh >> /opt/psvtravel/backup.log 2>&1
```

Chạy 3 giờ sáng, giữ 14 bản gần nhất tại `/opt/psvtravel/backups`.

Chạy thử ngay một lần cho chắc:

```bash
cd /opt/psvtravel/psvtravel-backend && ./scripts/sao-luu-csdl.sh
```

> Bản sao lưu nằm cùng máy với CSDL. Máy hỏng là mất cả hai. Mỗi tuần nên tải
> một bản về máy công ty:
> `scp psv@103.109.187.16:/opt/psvtravel/backups/*.gz .`

---

## Cập nhật website sau này

Sau khi push mã mới lên nhánh `main`:

```bash
cd /opt/psvtravel/psvtravel-backend
./scripts/trien-khai.sh
```

Script tự sao lưu CSDL, kéo mã mới của cả hai repo, build lại, chạy migration,
khởi động lại dịch vụ và kiểm tra web có lên không.

---

## Lệnh hay dùng

```bash
C="docker compose -f docker-compose.prod.yml"

$C ps                          # trạng thái các dịch vụ
$C logs -f app                 # xem log Laravel
$C logs -f frontend            # xem log Next.js
$C logs --tail=100 nginx       # xem log nginx
$C restart app queue           # khởi động lại backend
$C exec app php artisan optimize   # nạp lại cache sau khi đổi .env
docker system prune -af        # dọn ảnh Docker cũ khi đầy ổ đĩa
```

---

## Khi gặp sự cố

| Hiện tượng | Kiểm tra |
|---|---|
| Web trắng trang | `$C logs --tail=50 app` |
| Lỗi 502 | `$C ps` — container `app` hoặc `frontend` có `Up` không |
| Ảnh admin upload không hiện | `$C exec app php artisan storage:link` |
| HTTPS báo không an toàn | `$C logs certbot`, và kiểm tra `dig +short psvtravel.com` |
| Đầy ổ đĩa | `docker system prune -af` rồi `du -sh /opt/psvtravel/backups` |
| Sửa `.env` mà không thấy đổi | `$C exec app php artisan optimize` |
