# Rủi ro, việc còn dở và danh sách việc ngày bàn giao

## 1. Lỗi đã biết

| Lỗi | Tình trạng |
|---|---|
| 5 test backend lỗi sẵn: `DangKyTaiKhoanTest` (2), `HoSoVisaTest` (2), `TaoLinkChienDichTest` (1) | Lỗi từ trước, không do thay đổi gần đây. `TaoLinkChienDichTest` lỗi vì CSDL test (SQLite) không hiểu toán tử JSON `->>` của PostgreSQL. 4 test còn lại **chưa rõ nguyên nhân** — người tiếp quản nên xem trước khi sửa phần đăng ký tài khoản / hồ sơ visa |
| `README.md` của frontend ghi "Next.js 14" và mục "Việc cần làm khi lên chạy thật" đã cũ | Thực tế là Next.js 16; tin file 00–03 này hơn |
| Ảnh bìa tour không đúng tỉ lệ 16:9 bị cắt bớt ở giữa trên thẻ tour | Không phải lỗi mã: vào tour → bấm bút trên ảnh bìa → chọn 16:9 → kéo khung giữ phần chữ → Lưu. Thiết kế banner mới cỡ 1600×900 |

## 2. Việc chưa làm / chưa xác nhận

| Việc | Vì sao quan trọng |
|---|---|
| **Sao lưu ảnh và tệp tải lên** chưa tự động | Mất VPS là mất ảnh tour, hồ sơ visa, CV. Tạm thời sao lưu tay (file 03 mục 2) |
| Xác nhận cron sao lưu CSDL đã cài trên VPS (`crontab -l`) và đã có bản lưu ngoài VPS | Chưa kiểm tra lại từ khi cài |
| Bộ nhớ ảo (swap) cho VPS | Build frontend tốn RAM; VPS ít RAM có thể dừng giữa lúc triển khai |
| Xác thực 2 lớp (2FA) trang quản trị | Còn một việc sửa 2FA treo — kiểm tra lại bật / đăng nhập 2FA với tài khoản admin trên máy thật |
| Trợ lý AI (MCP) nội bộ đợt 2 (soạn nháp tour, FAQ, ưu đãi, banner…) và đợt 3 (xử lý đơn, tin liên hệ, hồ sơ visa) | Đợt 1 (chỉ xem số liệu) và soạn bài cẩm nang nháp đã xong |
| MCP nội bộ cho ChatGPT / claude.ai bản web (cần OAuth) | Hiện chỉ nối được ứng dụng trên máy tính (Claude Code, Claude Desktop, Codex, Cursor…) |

## 3. Dữ liệu cá nhân và quy tắc an toàn (Nghị định 13/2023)

Website giữ dữ liệu cá nhân của khách và nhân viên. Người tiếp quản chịu trách nhiệm giữ đúng các quy tắc sau:

| Dữ liệu | Quy tắc đã cài trong hệ thống | Việc của người tiếp quản |
|---|---|---|
| Hồ sơ visa (hộ chiếu, giấy tờ) | Lưu ổ riêng tư, chỉ xem qua link có chữ ký; nhân viên visa chỉ thấy hồ sơ mình phụ trách | Không gửi thư mục hồ sơ visa của khách cho ai, kể cả cho trợ lý AI; không chép ra USB / Drive cá nhân |
| Khuôn mặt nhân viên (chấm công) | Dữ liệu nhạy cảm; nhân viên phải đồng ý khi đăng ký; ảnh lúc chấm tự xoá sau 3 tháng | Giữ container `scheduler` chạy để việc xoá ảnh không ngừng |
| CV ứng viên | Mặc định chỉ super_admin / admin xem | Không mở quyền rộng hơn khi không cần |
| Số điện thoại / email khách | Trợ lý AI (MCP) chỉ thấy bản che bớt | Không chụp màn hình danh sách khách gửi ra ngoài |
| Tên đối tác liên minh / nhà cung cấp | Không hiện công khai trên web | Không đưa tên đối tác lên website; ảnh lấy từ file đối tác có thể có bản quyền |

Việc **không bao giờ làm** trên máy chủ thật:

- Chạy `psv:don-du-lieu-mau` khi đã có dữ liệu thật (nếu bắt buộc: sao lưu trước, chạy `--xem` trước).
- Chạy seeder dữ liệu mẫu (`BanDoCamNangMauSeeder`, `DemoSeeder`…) hoặc tạo tài khoản demo khi `APP_ENV=production`.
- Đổi `APP_KEY` (mất phiên đăng nhập và mã 2FA đã mã hoá).
- Quét bảo mật kiểu tấn công thử (active scan) vào website thật.
- Commit `.env`, file sao lưu `.sql` / `.sql.gz`, dữ liệu khách vào GitHub.
- Gửi mật khẩu, mã khoá MCP, khoá SMTP qua chat hay ảnh chụp màn hình. Lỡ lộ: thu hồi / đổi ngay.

## 4. Danh sách việc ngày bàn giao

Hai bên cùng đánh dấu, ký tên cuối trang.

**Chuyển quyền** (chi tiết: file 01)

- [ ] Tên miền `psvtravel.com` (hết hạn ngày: ______) và `psvtravel.com.vn` (hết hạn ngày: ______)
- [ ] Tài khoản nhà cung cấp VPS (gia hạn ngày: ______)
- [ ] Hai repo GitHub `psvtravel-backend`, `psvtravel-frontend`
- [ ] Brevo (gửi mail), Google Analytics, Search Console, Google Ads, Facebook Pixel, fanpage / Zalo / YouTube
- [ ] File `.env` trên VPS — trao qua kênh an toàn

**Khoá quyền người cũ**

- [ ] Thêm khoá SSH người nhận; xoá khoá SSH người bàn giao khỏi VPS
- [ ] Tạo tài khoản admin cho người nhận; hạ quyền / khoá tài khoản admin người bàn giao
- [ ] Thu hồi mọi mã khoá MCP của người bàn giao (Hệ thống → Kết nối AI)
- [ ] Gỡ người bàn giao khỏi GitHub và các dịch vụ trên

**Đổi bí mật** (rồi chạy `./scripts/trien-khai.sh`)

- [ ] Tạo khoá SMTP Brevo mới, sửa `MAIL_PASSWORD`, xoá khoá cũ
- [ ] Đổi `PSV_NOI_BO`
- [ ] Đổi mật khẩu `admin@psvtravel.com` (nếu còn dùng) và tài khoản dùng chung khác

**Kiểm tra người nhận làm được**

- [ ] Đăng nhập VPS, xem `DC ps` đủ container đang chạy
- [ ] Chạy `./scripts/trien-khai.sh` một lần thành công
- [ ] `crontab -l` có dòng sao lưu; chạy `./scripts/sao-luu-csdl.sh` tay một lần; tải một bản về máy mình
- [ ] Sao lưu thư mục ảnh / tệp tải lên một lần (file 03 mục 2)
- [ ] Dựng được máy dev theo `README.md` của hai repo, chạy được test
- [ ] Đăng nhập admin bằng tài khoản mới, bật 2FA

| | Người bàn giao | Người nhận |
|---|---|---|
| Họ tên | | |
| Ngày | | |
| Ký | | |
