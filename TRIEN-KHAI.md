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

> Nếu bạn lỡ bỏ qua bước này cũng không sao: lệnh `docker run --rm hello-world`
> ở Bước 2 chạy được cũng đủ chứng minh VPS chạy Docker tốt.

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
```

> **Chú ý hộp thoại tím hỏi về `sshd_config`.** Nếu hiện ra màn hình
> *"A new version of configuration file /etc/ssh/sshd_config is available, but
> the version installed currently has been locally modified"*, bấm **mũi tên
> xuống một lần** để chọn **`keep the local version currently installed`** rồi
> Enter. Chọn dòng mặc định (`install the package maintainer's version`) sẽ ghi
> đè file, xoá mất `PermitRootLogin no` vừa đặt ở Bước 1b.
>
> Còn hộp thoại hỏi *"Which services should be restarted?"* thì cứ Enter chọn
> `<Ok>`, cái đó vô hại.

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

Kiểm tra tài khoản đã vào nhóm `docker` chưa — cuối dòng phải có tên `psv`:

```bash
getent group docker
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

Bản Ubuntu của VinaHost không cài sẵn ufw:

```bash
sudo apt install -y ufw
```

Mở cổng theo đúng thứ tự này — cổng 22 (SSH) phải mở **trước** khi bật tường
lửa, nếu không bạn bị ngắt kết nối và không vào lại được:

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status
```

`ufw status` phải hiện `Status: active` và đủ ba cổng 22, 80, 443.

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

> **Phải điền xong `.env` TRƯỚC khi chạy bất kỳ lệnh `docker compose` nào.**
>
> Postgres ghi mật khẩu vào ổ đĩa ngay lần khởi động đầu tiên và không bao giờ
> đọc lại biến môi trường nữa. Lỡ khởi động container khi `.env` còn là
> `ĐIỀN_VÀO_ĐÂY` thì sau này Laravel báo
> `password authentication failed for user "psvtravel"`, và cách sửa là phải
> xoá volume dựng lại:
>
> ```bash
> docker compose -f docker-compose.prod.yml down
> docker volume rm psvtravel-backend_psv_pgdata
> docker compose -f docker-compose.prod.yml up -d
> ```

Sinh ba chuỗi bí mật:

```bash
echo "base64:$(openssl rand -base64 32)"   # dùng cho APP_KEY
openssl rand -base64 32                    # dùng cho DB_PASSWORD
openssl rand -base64 32                    # dùng cho REDIS_PASSWORD
```

> APP_KEY phải giữ nguyên cả chữ `base64:` ở đầu.
>
> Không dùng `php artisan key:generate` ở bước này được: artisan cần thư mục
> `vendor/` mà thư mục đó phải cài xong ở Bước 6b mới có. Lệnh `openssl` trên
> sinh ra đúng dạng khoá mà Laravel cần.

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

## Bước 6b — Cài thư viện PHP

```bash
docker compose -f docker-compose.prod.yml run --rm app composer install --no-dev --optimize-autoloader
```

Lần đầu lệnh này phải build ảnh Docker cho PHP — mất khoảng 10–15 phút, phần
lâu nhất là biên dịch thư viện xử lý ảnh `gd`. Những lần sau chỉ vài giây.

Kiểm tra xong chưa:

```bash
ls vendor/autoload.php
```

Ra đường dẫn là được. Không có file này thì mọi lệnh `php artisan` sau đây đều
báo `Failed opening required '/var/www/vendor/autoload.php'`.

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

# 3. Xoá chứng chỉ tạm.
#    Certbot từ chối ghi vào thư mục live/ mà nó không tự tạo, báo
#    "live directory exists for ...". nginx vẫn chạy bình thường sau lệnh này
#    vì đã nạp chứng chỉ vào bộ nhớ — nhưng ĐỪNG restart nginx cho tới khi có
#    chứng chỉ thật ở bước 4.
docker compose -f docker-compose.prod.yml run --rm --entrypoint sh certbot -c \
  "rm -rf /etc/letsencrypt/live/$PSV_DOMAIN /etc/letsencrypt/archive/$PSV_DOMAIN /etc/letsencrypt/renewal/$PSV_DOMAIN.conf"

# 4. Xin chứng chỉ thật cho cả ba tên miền
#    --entrypoint certbot là BẮT BUỘC: dịch vụ certbot trong compose có sẵn
#    entrypoint là vòng lặp tự gia hạn. Thiếu cờ này thì tham số bên dưới bị
#    nuốt mất, container chạy vòng lặp rồi ngủ 12 tiếng, trông như bị treo.
docker compose -f docker-compose.prod.yml run --rm --entrypoint certbot certbot certonly \
  --webroot -w /var/certbot \
  -d $PSV_DOMAIN -d www.$PSV_DOMAIN -d $PSV_API_DOMAIN \
  --email hieuvadanh091@gmail.com --agree-tos --no-eff-email

# 5. Nạp lại nginx với chứng chỉ thật
docker compose -f docker-compose.prod.yml restart nginx
```

Chứng chỉ tự gia hạn — container `certbot` kiểm tra 12 tiếng một lần.

> **Nếu báo lỗi DNSSEC** (`DNSSEC: Bogus: validation failure ... nodata proof
> failed`): tên miền có bản ghi DS ở registry nhưng nameserver không ký. Vào
> trang quản lý tên miền tắt mục **Bảo mật DNS / DNSSEC**, chờ vài tiếng cho
> bản ghi DS được gỡ (`dig +short DS psvtravel.com @8.8.8.8` ra rỗng) rồi chạy
> lại. Đây là lỗi cấu hình tên miền, không sửa được từ máy chủ.

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

Mở PowerShell trên máy Windows, đăng nhập VPS rồi chạy **một lệnh duy nhất**:

```
ssh psv@103.109.187.16
```

```bash
cd /opt/psvtravel/psvtravel-backend && ./scripts/trien-khai.sh
```

Script tự làm 8 việc theo đúng thứ tự:

| Bước | Việc |
|---|---|
| 0 | Sao lưu cơ sở dữ liệu |
| 1 | Lấy mã nguồn mới của **cả hai** repo |
| 2 | Bật thông báo bảo trì |
| 3 | Đóng lại ảnh Docker (gồm build lại frontend) |
| 4 | Cài thư viện PHP |
| 5 | Cập nhật cấu trúc cơ sở dữ liệu |
| 6 | Khởi động lại dịch vụ |
| 7 | Nạp lại bộ nhớ đệm, khởi động lại worker gửi mail |
| 8 | Tắt bảo trì rồi kiểm tra web có lên không |

Chạy khoảng 5–8 phút. Cuối cùng in ra mã trạng thái của API và website — cả hai
là `200` thì xong.

Mặc định script lấy nhánh `claude/github-repos-exploration-1izvgb`. Muốn lấy
nhánh khác thì truyền tên nhánh:

```bash
./scripts/trien-khai.sh main
```

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
| Web hiện ra nhưng trống dữ liệu, không đăng nhập được | `$C logs --tail=30 frontend` — thấy `UND_ERR_CONNECT_TIMEOUT` thì xem mục dưới |

### Đăng ký tài khoản báo "Server Error", không nhận được mail

```bash
$C exec app sh -c 'grep -a "production.ERROR" $(ls -t storage/logs/*.log | head -1) | tail -n 3 | cut -c1-400'
```

| Thông báo trong log | Cách sửa |
|---|---|
| `The "tls" scheme is not supported` | Sửa `.env`: `MAIL_SCHEME=smtp` (không phải `tls`) |
| `535 Authentication failed` | Sai `MAIL_USERNAME` / `MAIL_PASSWORD` |
| `Sender not valid` | Chưa xác thực tên miền `psvtravel.com` bên Brevo |

Sau khi sửa `.env` **phải tạo lại container**, restart suông không đủ:

```bash
$C up -d --force-recreate app queue
$C exec app php artisan optimize
```

Mail OTP gửi đồng bộ trong lúc xử lý đăng ký, nên SMTP hỏng là cả yêu cầu đăng
ký hỏng theo — đó là lý do lỗi hiện ra dưới dạng "Server Error" chứ không phải
"không gửi được mail".

### git báo `Permission denied` khi cập nhật mã nguồn

```
error: unable to unlink old 'storage/logs/.gitignore': Permission denied
fatal: Could not reset index file to revision 'FETCH_HEAD'.
```

Thư mục `storage` và `bootstrap/cache` bị đổi chủ sở hữu sang `www-data`. Trả
lại quyền cho tài khoản máy chủ:

```bash
sudo chown -R psv:psv /opt/psvtravel/psvtravel-backend
```

Rồi chạy lại. Từ bản cập nhật ngày 25/08/2026 lỗi này không tái diễn: container
chỉ đổi **nhóm** sang `www-data` thay vì đổi chủ sở hữu, nên PHP vẫn ghi được
mà git vẫn cập nhật được.

### Web trống dữ liệu và không đăng nhập được

Log frontend đầy dòng `Không gọi được API: ... UND_ERR_CONNECT_TIMEOUT`.

Container Next.js gọi API qua tên miền công khai, nên gói tin phải đi ra
Internet rồi vòng ngược về chính IP của VPS. Nhiều nhà cung cấp chặn kiểu đi
vòng này (hairpin NAT).

`docker-compose.prod.yml` đã xử lý sẵn bằng `aliases` trong phần `nginx`: bên
trong mạng Docker, ba tên miền trỏ thẳng vào container nginx. Kiểm tra còn
nguyên không:

```bash
grep -A6 "aliases" docker-compose.prod.yml
$C exec frontend wget -qO- https://api.psvtravel.com/api/v1/settings
```

Lệnh sau phải in ra JSON. Không ra thì tạo lại nginx:

```bash
$C up -d --force-recreate nginx
```
