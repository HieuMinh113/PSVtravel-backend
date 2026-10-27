<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Hướng dẫn sử dụng trang quản trị, chia theo bộ phận. Mở bằng nút "Hướng dẫn"
 * trên thanh trên cùng hoặc menu cá nhân; tự mở tab đúng vai trò người xem.
 *
 * Nội dung viết theo đúng tên menu / nút hiện tại — đổi tên menu, nút thì sửa
 * luôn ở đây (tests/Feature/HuongDanTest.php kiểm tra các đường dẫn menu nhắc
 * tới vẫn còn).
 */
class HuongDan extends Page
{
    protected static ?string $title = 'Hướng dẫn sử dụng';

    protected static ?string $slug = 'huong-dan';

    protected static bool $shouldRegisterNavigation = false;

    // Ai vào được trang quản trị là đọc được hướng dẫn
    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.huong-dan')->viewData(fn () => [
                'tabs' => self::noiDung(),
                'macDinh' => $this->tabMacDinh(),
            ]),
        ]);
    }

    /** Tab mở sẵn: ?tab= trên địa chỉ, không có thì theo vai trò người xem */
    private function tabMacDinh(): string
    {
        $tab = (string) request()->query('tab', '');
        if (array_key_exists($tab, self::noiDung())) {
            return $tab;
        }
        $u = auth()->user();
        foreach (['super_admin' => 'quan-tri', 'admin' => 'quan-tri', 'ke_toan' => 'ke-toan', 'visa' => 'visa',
            'dieu_hanh' => 'dieu-hanh', 'marketing' => 'marketing', 'sale' => 'sale', 'staff' => 'sale'] as $vaiTro => $t) {
            if ($u?->hasRole($vaiTro)) {
                return $t;
            }
        }

        return 'bat-dau';
    }

    /**
     * @return array<string, array{ten: string, icon: Heroicon, gioi_thieu: string, muc: list<array{tieu_de: string, o_dau?: string, buoc: list<string>, luu_y?: string}>}>
     */
    public static function noiDung(): array
    {
        return [
            'bat-dau' => [
                'ten' => 'Bắt đầu',
                'icon' => Heroicon::OutlinedSparkles,
                'gioi_thieu' => 'Những điều ai dùng trang quản trị cũng cần biết.',
                'muc' => [
                    [
                        'tieu_de' => 'Menu bên trái',
                        'buoc' => [
                            'Trên cùng là **Tổng quan** và **Chấm công**, bên dưới là các nhóm theo công việc: Bán hàng, Tour & điều hành, Visa, Marketing, Nội dung website, Giới thiệu công ty, Nhân sự, Hệ thống.',
                            'Bấm tên nhóm để mở / thu gọn. Trình duyệt nhớ lựa chọn của bạn.',
                            'Bạn chỉ thấy các mục vai trò của mình được phép dùng. Thiếu mục cần dùng thì báo quản trị viên.',
                            'Số đỏ cạnh mục = khách đang chờ hoặc đã tới hạn; số vàng = việc chờ duyệt.',
                            'Màn hình nhỏ: bấm nút ‹ ở cuối menu để thu gọn menu, bảng sẽ rộng hơn.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Trang Tổng quan',
                        'o_dau' => 'Tổng quan',
                        'buoc' => [
                            'Dải số đầu trang: đơn chờ xử lý, đơn mới hôm nay, doanh thu tháng, việc chờ duyệt.',
                            '**Việc cần làm**: gom các số đỏ / vàng trên menu về một chỗ. Bấm một ô là mở đúng danh sách cần xử lý.',
                            '**Lối tắt**: tạo đơn, thêm tour, hồ sơ visa mới, viết cẩm nang, tạo link chiến dịch, chấm công — chỉ hiện nút bạn có quyền.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Chấm công mỗi ngày',
                        'o_dau' => 'Chấm công',
                        'buoc' => [
                            'Lần đầu: đọc và đồng ý điều khoản, rồi đăng ký khuôn mặt theo hướng dẫn trên màn hình.',
                            'Mỗi lần vào / ra: mở **Chấm công**, cho phép trình duyệt dùng camera và vị trí, quét khuôn mặt.',
                            'Trễ, về sớm hoặc đang ở ngoài công ty: hệ thống hỏi lý do — ghi ngắn gọn để được duyệt.',
                            'Quên chấm: bấm **Xin bổ sung công**, chọn ngày, giờ thực tế và lý do.',
                        ],
                        'luu_y' => 'Ảnh chấm công là dữ liệu khuôn mặt (dữ liệu nhạy cảm), chỉ dùng để chấm công và tự xoá sau 3 tháng.',
                    ],
                    [
                        'tieu_de' => 'Tài khoản và bảo mật',
                        'o_dau' => 'Ảnh đại diện góc phải → Hồ sơ',
                        'buoc' => [
                            'Đổi mật khẩu, bật xác thực 2 lớp (app Google Authenticator), xem lại mã khôi phục.',
                            'Quản trị viên bắt buộc bật xác thực 2 lớp. Mất điện thoại thì dùng mã khôi phục để đăng nhập.',
                            'Không dùng chung tài khoản, không lưu mật khẩu trên máy dùng chung.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Chuông, tiếng báo và tìm kiếm',
                        'buoc' => [
                            'Biểu tượng **chuông** góc phải: thông báo việc mới (đơn mới, khoản thu chờ duyệt, sheet liên minh lỗi…).',
                            'Biểu tượng **loa**: bật / tắt tiếng báo trên máy đang dùng.',
                            'Ô **Tìm kiếm** trên cùng: gõ mã đơn, tên tour, tên bài viết… để mở nhanh.',
                        ],
                    ],
                ],
            ],

            'sale' => [
                'ten' => 'Sale',
                'icon' => Heroicon::OutlinedShoppingCart,
                'gioi_thieu' => 'Nhận đơn, tạo đơn, ghi tiền khách chuyển, nhắc đóng tiền.',
                'muc' => [
                    [
                        'tieu_de' => 'Nhận đơn khách đặt trên website',
                        'o_dau' => 'Bán hàng → Đơn đặt tour',
                        'buoc' => [
                            'Đơn khách đặt web, chưa ai nhận: mọi người đều thấy và xử lý được.',
                            'Gọi khách xác nhận lại ngày đi, số khách, giá.',
                            'Bấm **Xác nhận** trên dòng đơn. Hệ thống trừ số chỗ của đợt khởi hành; người xác nhận trở thành **người phụ trách** (hưởng hoa hồng).',
                            'Bộ lọc **Đơn tôi phụ trách** để chỉ xem đơn của mình.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Tạo đơn cho khách gọi điện / đến văn phòng',
                        'o_dau' => 'Bán hàng → Đơn đặt tour → Tạo (hoặc lối tắt "Tạo đơn đặt tour" ở Tổng quan)',
                        'buoc' => [
                            'Chọn tour và đợt khởi hành, nhập số người lớn / trẻ em, tên và số điện thoại khách.',
                            'Đặt **Hạn nhắn khách đóng tiền** nếu khách đóng sau.',
                            'Lưu, rồi bấm **Xác nhận** khi khách đã chốt.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Ghi nhận tiền khách chuyển',
                        'o_dau' => 'Mở đơn → tab Thanh toán',
                        'buoc' => [
                            'Thêm khoản thu: số tiền, hình thức, mã giao dịch / số phiếu thu, ảnh chứng từ chuyển khoản.',
                            'Khoản thu ở trạng thái **chờ kế toán duyệt**, chưa tính vào "đã thu".',
                            'Kế toán bấm **Đã nhận tiền** thì mới tính. Bị **Từ chối** thì xem lý do kế toán ghi, sửa rồi gửi lại.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Nhắc khách đóng tiền, gửi phiếu xác nhận',
                        'o_dau' => 'Bán hàng → Đơn đặt tour',
                        'buoc' => [
                            'Tới hạn nhắn khách đóng tiền: số đỏ hiện trên mục Đơn đặt tour và ở Việc cần làm.',
                            'Bấm **Nhắc đóng tiền**: chép tin nhắn mẫu, dán gửi khách qua Zalo / SMS.',
                            'Bấm **Phiếu xác nhận (PDF)** để tải phiếu gửi khách.',
                            'Sau chuyến đi bấm **Hoàn thành**. Khách huỷ: **Huỷ đơn** và ghi lý do.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Tin liên hệ từ website',
                        'o_dau' => 'Bán hàng → Tin liên hệ',
                        'buoc' => [
                            'Tin từ form Liên hệ và form Team building trên web. Số đỏ = tin mới chưa ai xử lý.',
                            'Gọi lại khách, ghi chú nội dung trao đổi và đổi trạng thái để người khác không gọi trùng.',
                            'Cột **Kênh** cho biết khách đến từ đâu (TikTok, Facebook, Google, ChatGPT…).',
                        ],
                    ],
                    [
                        'tieu_de' => 'Đơn combo Maldives',
                        'o_dau' => 'Maldives → Đơn Maldives',
                        'buoc' => [
                            'Khách đặt combo trên trang psvtravel.com/maldives: số đỏ cạnh mục = đơn mới chưa ai nhận, chuông cũng báo.',
                            'Mở đơn → **Nhận đơn** (bạn thành người phụ trách) → **Gọi khách** xác nhận ngày, hạng phòng, số khách.',
                            'Giá trên đơn là **tạm tính** (USD × tỷ giá lúc khách đặt). Hỏi đối tác còn phòng rồi **Sửa**: ghi Tổng VNĐ chốt, Đã thu, đổi trạng thái (Đã xác nhận → Đã đặt cọc → Hoàn tất).',
                            'Khách gọi điện đặt: **Tạo đơn cho khách gọi điện** — để trống ô Tạm tính thì hệ thống tự tính theo bảng giá.',
                        ],
                        'luu_y' => 'Đơn Maldives tách riêng hoàn toàn với Đơn đặt tour: không trừ chỗ, không có tab khoản thu — tiền đã thu ghi ở ô "Đã thu".',
                    ],
                    [
                        'tieu_de' => 'Gửi danh sách giấy tờ visa cho khách',
                        'o_dau' => 'Visa → Mẫu checklist',
                        'buoc' => ['Chọn nước và loại visa → **Xuất file** → gửi khách.'],
                    ],
                ],
            ],

            'marketing' => [
                'ten' => 'Marketing',
                'icon' => Heroicon::OutlinedMegaphone,
                'gioi_thieu' => 'Tạo link chiến dịch, đọc kết quả, quản lý banner / khuyến mãi / trang theo mùa.',
                'muc' => [
                    [
                        'tieu_de' => 'Tạo link cho bài đăng, clip, quảng cáo, tờ rơi',
                        'o_dau' => 'Marketing → Tạo link chiến dịch',
                        'buoc' => [
                            'Mở trang muốn khách vào trên psvtravel.com (một tour, trang Tết, team building…), chép địa chỉ dán vào **Trang đích**.',
                            '**Đăng ở đâu**: Facebook, TikTok, Zalo, YouTube, Instagram, Google, Email, hoặc **Mã QR in ấn** khi in lên giấy.',
                            '**Hình thức**: Đăng bài thường (không trả tiền) hay Quảng cáo trả tiền.',
                            '**Tên chiến dịch**: theo đợt bán, VD "Tết 2027". Mọi bài trong cùng đợt dùng **đúng một tên** — ô sẽ gợi ý tên đã dùng.',
                            '**Mã clip / bài đăng**: đánh số từng clip, VD "clip 01", "clip 02" để biết clip nào ra đơn.',
                            'Bấm ra ngoài ô vừa gõ → **Chép link** dán vào bài / bio / nút quảng cáo. Cần mã QR: tải **PNG** (đăng mạng) hoặc **SVG** (gửi nhà in).',
                        ],
                        'luu_y' => 'Mỗi clip / bài mới tạo một link mới. Không tự sửa phần đuôi "?utm_…" của link. Rút gọn link (bit.ly…) vẫn được.',
                    ],
                    [
                        'tieu_de' => 'Bài / clip nào ra đơn',
                        'o_dau' => 'Marketing → Nguồn khách',
                        'buoc' => [
                            'Chọn **Tháng**.',
                            'Bảng **theo kênh**: số yêu cầu liên hệ, đơn đặt tour, đơn đã chốt, doanh số đã chốt của từng kênh.',
                            'Bảng **theo chiến dịch và clip**: VD "Tết 2027 / clip 03 → 5 đơn, 2 đã chốt".',
                            'Khách bấm link rồi đặt tour / gửi liên hệ **trong 30 ngày** thì đơn ghi đúng kênh và chiến dịch.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Lượng người xem web (Google Analytics)',
                        'o_dau' => 'analytics.google.com (trang của Google, không nằm trong admin)',
                        'buoc' => [
                            '**Báo cáo → Thời gian thực**: ai đang xem web ngay lúc này.',
                            '**Thu nạp → Thu nạp lưu lượng truy cập**: khách đến từ đâu. Đổi cột đầu bảng sang "Phiên - chiến dịch" để xem theo chiến dịch / clip.',
                            '**Tương tác → Trang và màn hình**: trang nào được xem nhiều.',
                            '**Kiếm tiền → Mua hàng thương mại điện tử**: tour nào nhiều người xem.',
                            '**Tương tác → Sự kiện**: generate_lead = khách để lại thông tin; goi_hotline / nhan_zalo / nhan_messenger = bấm nút liên hệ; view_item = mở trang tour.',
                        ],
                        'luu_y' => 'Đọc kết hợp: nhiều người xem (GA4) mà ít đơn (Nguồn khách) → clip kéo được người nhưng trang / giá chưa thuyết phục.',
                    ],
                    [
                        'tieu_de' => 'Banner, khuyến mãi, trang theo mùa',
                        'o_dau' => 'Marketing → Banner trang chủ / Khuyến mãi / Trang theo mùa/dịp',
                        'buoc' => [
                            '**Banner trang chủ**: ảnh ngang ở đầu trang chủ, chạy vòng; số thứ tự nhỏ hiện trước.',
                            '**Khuyến mãi**: ảnh popup khi khách mở web.',
                            '**Trang theo mùa/dịp**: tạo trang như /tour-tet, chọn khoảng ngày khởi hành — tour có đợt đi trong khoảng đó tự hiện, không cần gắn tay. Hết dịp thì sửa khoảng ngày sang năm sau, giữ nguyên đường dẫn.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Gợi ý SEO khi nhập tour',
                        'o_dau' => 'Tour & điều hành → Tour',
                        'buoc' => [
                            'Cột **SEO** ở danh sách tour: "Đạt" (xanh), số gợi ý vàng = nên có, đỏ = nên sửa ngay. Rê chuột để xem chi tiết.',
                            'Mở tour: khung **Gợi ý SEO** trên cùng cho biết tiêu đề Google sẽ hiện và từng việc cần làm (tiêu đề bị cắt, thiếu lịch trình, đường dẫn dính chữ…).',
                            'Sửa xong bấm Lưu rồi tải lại trang để xem lại.',
                            'Đổi **Đường dẫn (slug)** của tour (hoặc bài cẩm nang) đang hiện trên web: khoảng 1 phút sau link cũ tự chuyển sang link mới (xem ở Marketing → Chuyển hướng link cũ, người đặt "Đổi đường dẫn"). Vẫn nên đặt đúng ngay từ đầu.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Bài cẩm nang có tác giả',
                        'o_dau' => 'Giới thiệu công ty → Đội ngũ',
                        'buoc' => [
                            'Mở người viết bài → phần **Trang tác giả**: số năm kinh nghiệm, chuyên về, những nơi đã dẫn tour, giới thiệu đầy đủ, tài khoản admin của người đó.',
                            'Khi viết bài (Nội dung website → Cẩm nang) chọn **Tác giả hiện trên bài**. Người có tài khoản admin gắn sẵn thì bài họ nhập tự chọn họ.',
                            'Có ít nhất một bài đã đăng là trang psvtravel.com/tac-gia/… tự có; bài viết có khung "Về tác giả" và link tới trang đó.',
                        ],
                        'luu_y' => 'Chỉ ghi thông tin THẬT người đó đồng ý công khai (ảnh, số năm, nơi đã đi). Google và ChatGPT đánh giá cao bài có người viết thật, có kinh nghiệm.',
                    ],
                    [
                        'tieu_de' => 'Nhập combo Maldives',
                        'o_dau' => 'Maldives → Resort Maldives / Combo Maldives',
                        'buoc' => [
                            'Nhanh nhất: **Combo Maldives → Nhập từ file Word/PDF** → tải file chương trình của đối tác → xem trước bảng giá, lịch trình → **Nhập combo**. Máy tạo combo **Nháp** (resort chưa có thì tạo luôn, kèm hạng phòng, tiện ích, quy định). Nhập lại file mới của cùng combo → chọn "Chỉ cập nhật bảng giá + ngày khởi hành".',
                            '**Resort Maldives** (nhập một lần): giới thiệu, ảnh, các **hạng phòng** kèm sức chứa, tiện ích, quy định của resort. Hạng phòng chỉ nhận khách từ 18 tuổi → bật "Chỉ nhận người lớn" (web ẩn ô trẻ em / em bé).',
                            '**Combo Maldives** → Tạo: chọn resort, tên combo, số ngày, hãng bay, nơi khởi hành (phụ thu USD / khách, lịch bay).',
                            '**Ngày khởi hành & bảng giá**: mỗi ngày một dòng, chép giá USD / người lớn cho từng hạng phòng đúng bảng giá đối tác. Nút nhân bản để chép nhanh ngày tiếp theo.',
                            'Thêm lịch trình từng ngày, giá bao gồm / không bao gồm, lưu ý → Trạng thái **Đang bán** → Lưu → **Xem trên web**.',
                            'Tỷ giá để web hiện "≈ … triệu đồng": Hệ thống → Cấu hình chung → "Maldives: tỷ giá USD tạm tính".',
                        ],
                        'luu_y' => 'Nhập từ file: phụ thu ghi khoảng ("$100 – $120") thì máy lấy mức thấp nhất; ngày đã qua tự bỏ — đọc mục "Cần xem lại" trước khi đăng bán. Ảnh lấy từ file đối tác có thể vướng bản quyền nên mặc định không lấy — dùng ảnh resort cấp chính thức (media kit) hoặc ảnh công ty tự chụp.',
                    ],
                    [
                        'tieu_de' => 'Gợi ý SEO khi viết bài cẩm nang',
                        'o_dau' => 'Nội dung website → Cẩm nang',
                        'buoc' => [
                            'Cột **SEO** ở danh sách bài: "Đạt" (xanh), vàng = nên có, đỏ = nên sửa ngay. Mở bài: khung **Gợi ý SEO** trên cùng ghi rõ từng việc.',
                            'Chèn ảnh: sau khi tải ảnh lên, điền ô **Văn bản thay thế (alt text)** — tả ảnh có gì (VD "Phố Dotonbori Osaka về đêm"). Ảnh đã chèn: bấm vào ảnh → nút ghim **Đính kèm tệp** → sửa ô đó.',
                            '**Tóm tắt** là đoạn hiện dưới tiêu đề trên Google: viết 120–160 ký tự.',
                            'Link sang trang của công ty: dán link nào cũng được (có www hay không) — bấm Lưu là tự đổi về link chuẩn. Ảnh to cũng tự thu nhỏ sau khi lưu.',
                        ],
                        'luu_y' => 'Đường dẫn (slug) chỉ gồm chữ thường không dấu và gạch ngang — form tự điền theo tiêu đề khi viết bài mới.',
                    ],
                    [
                        'tieu_de' => 'Mã QR cho từng tour',
                        'o_dau' => 'Tour & điều hành → Tour → nút Mã QR',
                        'buoc' => ['Tải PNG / SVG để in tờ rơi, poster. Đổi tên tour thì mã đã in vẫn quét được; xem được số lượt quét.'],
                    ],
                    [
                        'tieu_de' => 'Link web cũ chưa khớp',
                        'o_dau' => 'Marketing → Chuyển hướng link cũ',
                        'buoc' => [
                            'Số cạnh mục = link của web cũ khách / Google còn giữ mà chưa có trang mới tương ứng (khách đang thấy trang lỗi).',
                            'Lọc **Chưa khớp**, làm từ dòng nhiều **Lượt mở** trước: bấm dòng → Xử lý **Chuyển sang trang mới (301)** → dán đường dẫn trang thay thế → Lưu.',
                            'Không có trang nào phù hợp: chọn **Trang đã gỡ hẳn (410)**.',
                            'Thêm tour / bài mới xong, bấm **Tự khớp lại tất cả** để máy dò lại.',
                        ],
                    ],
                ],
            ],

            'visa' => [
                'ten' => 'Visa',
                'icon' => Heroicon::OutlinedIdentification,
                'gioi_thieu' => 'Nhận và theo dõi hồ sơ visa, gửi checklist, quản lý trang làm visa trên web.',
                'muc' => [
                    [
                        'tieu_de' => 'Nhận hồ sơ khách nộp',
                        'o_dau' => 'Visa → Hồ sơ visa',
                        'buoc' => [
                            'Số đỏ = hồ sơ khách nộp trên web chưa ai nhận; số vàng = hồ sơ có lịch hẹn trong 3 ngày tới.',
                            'Mở hồ sơ → **Nhận hồ sơ**.',
                            'Cập nhật trạng thái theo tiến độ: Mới nhận → Đang gom giấy tờ → Đủ giấy — chờ nộp → Đã nộp — chờ kết quả → Đậu / Trượt (hoặc Khách huỷ).',
                        ],
                    ],
                    [
                        'tieu_de' => 'Khách thiếu giấy tờ',
                        'o_dau' => 'Visa → Hồ sơ visa → mở hồ sơ',
                        'buoc' => ['Bấm **Tin nhắn giấy thiếu** → chép tin gửi khách qua Zalo / SMS.'],
                    ],
                    [
                        'tieu_de' => 'Xuất hồ sơ đi nộp',
                        'o_dau' => 'Visa → Hồ sơ visa',
                        'buoc' => [
                            'Một khách: **Xuất hồ sơ (ZIP)**. Cả đoàn: **Xuất ZIP đoàn**.',
                        ],
                        'luu_y' => 'Hồ sơ visa là dữ liệu cá nhân của khách: chỉ tải khi cần nộp, không gửi qua nhóm chat ngoài công ty, xoá bản tải về khi xong.',
                    ],
                    [
                        'tieu_de' => 'Checklist, hạn nộp, trang làm visa',
                        'o_dau' => 'Visa → Mẫu checklist / Hạn nộp visa theo nước / Visa theo quốc gia',
                        'buoc' => [
                            '**Mẫu checklist**: danh sách giấy tờ theo nước → **Xuất file** gửi khách.',
                            '**Hạn nộp visa theo nước**: số ngày cần nộp trước ngày bay. Ngày khởi hành đã quá hạn nộp tự ẩn trên website.',
                            '**Visa theo quốc gia**: nội dung trang "Làm visa" trên web (giá, thời gian xử lý, giấy tờ).',
                        ],
                    ],
                ],
            ],

            'ke-toan' => [
                'ten' => 'Kế toán',
                'icon' => Heroicon::OutlinedBanknotes,
                'gioi_thieu' => 'Duyệt khoản thu, xem doanh số, duyệt bảng công.',
                'muc' => [
                    [
                        'tieu_de' => 'Duyệt khoản thu',
                        'o_dau' => 'Bán hàng → Duyệt khoản thu (hoặc bấm thông báo trên chuông)',
                        'buoc' => [
                            'Mỗi dòng là một khoản nhân viên đã ghi kèm ảnh chứng từ.',
                            'Đối chiếu sao kê ngân hàng với ảnh chứng từ và mã giao dịch.',
                            'Khớp: bấm **Đã nhận tiền** — khoản thu được tính vào "đã thu" của đơn.',
                            'Không khớp: bấm **Từ chối** và ghi lý do (nhân viên sẽ thấy).',
                        ],
                    ],
                    [
                        'tieu_de' => 'Doanh số',
                        'o_dau' => 'Bán hàng → Thống kê doanh số',
                        'buoc' => ['Chọn tháng: doanh số đơn đã xác nhận / hoàn thành, theo nhân viên phụ trách.'],
                    ],
                    [
                        'tieu_de' => 'Bảng công',
                        'o_dau' => 'Nhân sự → Bảng chấm công / Bảng công tháng',
                        'buoc' => [
                            '**Bảng chấm công**: số vàng = lý do trễ / bổ sung công chờ duyệt.',
                            '**Bảng công tháng**: tổng công từng người trong tháng.',
                        ],
                    ],
                ],
            ],

            'dieu-hanh' => [
                'ten' => 'Điều hành',
                'icon' => Heroicon::OutlinedTableCells,
                'gioi_thieu' => 'Tra chỗ đối tác liên minh, lấy ngày khởi hành cho tour.',
                'muc' => [
                    [
                        'tieu_de' => 'Tra chỗ liên minh',
                        'o_dau' => 'Tour & điều hành → Tra chỗ liên minh',
                        'buoc' => [
                            'Số chỗ còn của các đối tác, đọc tự động từ Google Sheet của họ. Dòng đầu trang cho biết mỗi sheet đọc lúc nào, có lỗi không.',
                            'Cần số mới ngay: **Đọc lại tất cả sheet**.',
                            'Báo khách / đối tác: **Tin nhắn** hoặc **Soạn tin nhắn** → **Chép tin nhắn**.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Sheet đối tác bị lỗi',
                        'o_dau' => 'Tour & điều hành → Sheet liên minh',
                        'buoc' => [
                            'Số đỏ = sheet đang không đọc được (thường do đối tác đổi link hoặc tắt chia sẻ).',
                            'Sửa link / nhờ đối tác mở chia sẻ → **Đọc ngay** để thử lại.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Thêm ngày khởi hành cho tour',
                        'o_dau' => 'Tour & điều hành → Tour → mở tour → phần Đợt khởi hành',
                        'buoc' => [
                            'Bấm **Lấy ngày đi từ liên minh** để chọn ngày và số chỗ từ sheet đối tác.',
                            'Tour mới có chương trình sẵn: **Nhập từ file Word/PDF** để điền nhanh lịch trình.',
                        ],
                    ],
                ],
            ],

            'quan-tri' => [
                'ten' => 'Quản trị',
                'icon' => Heroicon::OutlinedCog6Tooth,
                'gioi_thieu' => 'Tài khoản nhân viên, phân quyền, cấu hình website, nhật ký.',
                'muc' => [
                    [
                        'tieu_de' => 'Thêm / khoá tài khoản nhân viên',
                        'o_dau' => 'Hệ thống → Người dùng',
                        'buoc' => [
                            '**Tạo**: họ tên, email, mật khẩu tạm, chọn **Vai trò** (Sale, Marketing, Visa, Kế toán, Điều hành, Admin).',
                            'Gửi mật khẩu tạm cho nhân viên và nhắc đổi ngay khi đăng nhập lần đầu (ảnh đại diện → Hồ sơ).',
                            'Nghỉ việc: gỡ hết vai trò — tài khoản không vào được trang quản trị nữa.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Phân quyền chi tiết',
                        'o_dau' => 'Hệ thống → Vai trò',
                        'buoc' => [
                            'Mỗi vai trò là một bộ quyền tick sẵn. Chỉ đổi khi hiểu rõ — bỏ nhầm quyền làm nhân viên mất mục cần dùng.',
                            'Vai trò "customer" là khách hàng trên website, không xoá.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Giao đơn cho nhân viên khác',
                        'o_dau' => 'Bán hàng → Đơn đặt tour → sửa đơn',
                        'buoc' => ['Đổi **Người phụ trách (hưởng hoa hồng)**. Chỉ quản lý đổi được; thống kê hoa hồng tính theo người này.'],
                    ],
                    [
                        'tieu_de' => 'Thông tin hiện trên website',
                        'o_dau' => 'Hệ thống → Cấu hình chung',
                        'buoc' => ['Hotline, email, địa chỉ, link mạng xã hội (Facebook, Zalo, Messenger…), giờ làm việc. Sửa xong website cập nhật trong khoảng 1 phút.'],
                    ],
                    [
                        'tieu_de' => 'Chấm công toàn công ty',
                        'o_dau' => 'Nhân sự → Cấu hình chấm công / Khuôn mặt nhân viên',
                        'buoc' => [
                            '**Cấu hình chấm công**: toạ độ công ty, bán kính, giờ vào / ra, số phút được trễ.',
                            '**Khuôn mặt nhân viên**: xoá khuôn mặt đã đăng ký khi nhân viên cần đăng ký lại.',
                        ],
                    ],
                    [
                        'tieu_de' => 'Ai sửa gì, lúc nào',
                        'o_dau' => 'Hệ thống → Nhật ký hoạt động',
                        'buoc' => ['Lọc theo người / loại dữ liệu để xem lịch sử thay đổi (tour, đơn, giá…).'],
                    ],
                ],
            ],
        ];
    }
}
