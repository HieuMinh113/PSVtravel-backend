# Bàn giao website PSV Travel — bắt đầu từ đây

Bộ tài liệu cho người tiếp quản website **psvtravel.com**. Không chứa mật khẩu
hay mã khoá nào — những thứ đó bàn giao trực tiếp (xem file 01).

| File | Dành cho | Nội dung |
|---|---|---|
| `00-BAT-DAU-TU-DAY.md` | Mọi người | Hệ thống gồm những gì, đọc gì trước |
| `01-TAI-KHOAN-VA-QUYEN-TRUY-CAP.md` | Mọi người | Tài khoản phải chuyển giao, việc làm ngay sau khi nhận |
| `02-CHUC-NANG-VA-MA-NGUON.md` | Lập trình viên | Chức năng nào nằm ở đâu trong mã nguồn, quy tắc nghiệp vụ |
| `03-VAN-HANH-HANG-NGAY.md` | Lập trình viên / người quản trị máy chủ | Cập nhật, sao lưu, khôi phục, xem lỗi, việc tự chạy |
| `04-RUI-RO-VA-VIEC-CON-DO.md` | Mọi người | Lỗi đã biết, việc chưa làm, lưu ý dữ liệu cá nhân, danh sách việc ngày bàn giao |

Tài liệu đã có sẵn, không chép lại ở đây:

| Tài liệu | Ở đâu |
|---|---|
| Dựng máy lập trình (Docker Desktop) | `README.md` của repo backend và repo frontend |
| Đưa lên máy chủ từ đầu, cập nhật, xử lý sự cố | `TRIEN-KHAI.md` (repo backend) |
| Hướng dẫn nhân viên dùng trang quản trị | Trong admin: nút **Hướng dẫn** góc trên |
| Kết nối trợ lý AI (MCP) | Trong admin: **Hệ thống → Kết nối AI (MCP)** |
| File báo giá, theo dõi trích dẫn AI | `tai-lieu/README.md` (repo backend) |

---

## Hệ thống gồm những gì

```
Khách ──► psvtravel.com ──────► nginx ──► frontend (Next.js 16)  ──┐ gọi API
                                                                   ▼
Nhân viên ─► api.psvtravel.com/admin ─► nginx ──► app (Laravel 13 + Filament 5)
                                                     │       │
                                                 postgres  redis
                                                 (dữ liệu) (bộ nhớ đệm, hàng đợi)
```

Tất cả chạy bằng **Docker** trên **một VPS** (thư mục `/opt/psvtravel`), file
cấu hình `docker-compose.prod.yml` trong repo backend:

| Container | Việc |
|---|---|
| `nginx` | Nhận mọi truy cập, HTTPS, chia cho frontend / app |
| `frontend` | Website khách xem (Next.js, render sẵn HTML cho SEO) |
| `app` | API + trang quản trị (Laravel / Filament) |
| `queue` | Chạy việc nền (gửi mail…) |
| `scheduler` | Chạy việc hẹn giờ (xem file 03) |
| `postgres` | Cơ sở dữ liệu |
| `redis` | Bộ nhớ đệm, hàng đợi |
| `certbot` | Tự gia hạn chứng chỉ HTTPS (Let's Encrypt) |

Hai repo GitHub:

| Repo | Nội dung |
|---|---|
| `psvtravel-backend` | Laravel, trang quản trị, API, Docker, nginx, script triển khai |
| `psvtravel-frontend` | Website Next.js |

Nhánh chính là `main` — VPS kéo mã từ `main` khi triển khai.

## Đọc theo thứ tự

- **Người vận hành (chỉ dùng admin):** file 01 → 04 → nút **Hướng dẫn** trong admin.
- **Lập trình viên:** file 01 → `README.md` (dựng máy) → 02 → 03 → `TRIEN-KHAI.md` → 04.
