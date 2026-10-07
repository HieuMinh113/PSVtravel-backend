<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Category\Models\Category;
use Modules\Review\Models\Review;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Tour\Models\TourImage;
use Modules\Tour\Models\TourItinerary;

/**
 * Bộ tour mẫu đủ các miền / châu lục để website và trang quản trị có cái mà
 * bấm: 12 tour (6 trong nước, 6 nước ngoài), mỗi tour 2–3 đợt khởi hành (có
 * đợt hết chỗ), lịch trình từng ngày, ảnh, đánh giá; cùng các danh mục và
 * điểm đến nối vào danh mục. Gọi từ DemoSeeder. Chạy lại không sinh trùng.
 *
 * Giá, lịch trình là số liệu MẪU — không phải bảng giá thật của công ty.
 */
class DuLieuMauTourSeeder extends Seeder
{
    private const DANH_MUC = [
        // slug => [loại, tên, thứ tự]
        'mien-bac' => ['domestic', 'Miền Bắc', 2],
        'mien-trung' => ['domestic', 'Miền Trung', 1],
        'mien-nam' => ['domestic', 'Miền Nam', 3],
        'tay-nguyen' => ['domestic', 'Tây Nguyên', 4],
        'dong-nam-a' => ['abroad', 'Đông Nam Á', 1],
        'dong-bac-a' => ['abroad', 'Đông Bắc Á', 2],
        'trung-quoc' => ['abroad', 'Trung Quốc', 3],
        'chau-au' => ['abroad', 'Châu Âu', 4],
    ];

    private function anh(string $seed, int $w = 1200, int $h = 700): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    public function run(): void
    {
        $adminId = User::where('email', 'admin@psvtravel.com')->value('id');

        $danhMuc = [];
        foreach (self::DANH_MUC as $slug => [$loai, $ten, $thuTu]) {
            $danhMuc[$slug] = Category::updateOrCreate(['slug' => $slug], [
                'type' => $loai, 'name' => $ten, 'status' => 'published', 'sort_order' => $thuTu,
                'image' => $this->anh('cat-'.$slug, 600, 400),
                'description' => 'Các tour '.$ten.' do PSV Travel tổ chức.',
            ]);
        }

        foreach ($this->tours() as $thuTu => $t) {
            $tour = Tour::updateOrCreate(['slug' => $t['slug']], [
                'name' => $t['ten'],
                'type' => $t['loai'],
                'region' => $t['khu_vuc'],
                'country' => $t['nuoc'] ?? null,
                'duration_days' => $t['ngay'],
                'duration_nights' => $t['ngay'] - 1,
                'departure_from' => $t['tu'] ?? 'Hồ Chí Minh',
                'adult_price' => $t['gia'],
                'child_price' => (int) round($t['gia'] * 0.75 / 10_000) * 10_000,
                'old_price' => $t['gia_cu'] ?? null,
                'tag' => $t['nhan'] ?? null,
                'cover_image' => $this->anh('tour-'.$t['slug']),
                'highlights' => $t['diem_nhan'],
                'included' => $t['loai'] === 'abroad'
                    ? ['Vé máy bay khứ hồi + hành lý ký gửi', 'Khách sạn 4 sao', 'Visa (nếu có)', 'Ăn theo chương trình', 'HDV tiếng Việt suốt tuyến', 'Bảo hiểm du lịch']
                    : ['Xe đưa đón đời mới', 'Khách sạn 3–4 sao', 'Ăn theo chương trình', 'Vé tham quan', 'HDV suốt tuyến', 'Bảo hiểm du lịch'],
                'excluded' => ['Chi phí cá nhân, giặt ủi', 'Tip HDV và tài xế', 'Phụ thu phòng đơn'],
                'cancellation_policy' => 'Huỷ trước 15 ngày hoàn 70%, trước 7 ngày hoàn 50%, sau đó không hoàn.',
                'description' => $t['mo_ta'],
                'status' => 'published',
                'is_featured' => $t['noi_bat'] ?? false,
                'sort_order' => 10 + $thuTu,
            ]);
            $tour->categories()->syncWithoutDetaching([$danhMuc[$t['danh_muc']]->id]);

            // Đợt khởi hành: tính từ hôm nay; đợt có chỗ = 0 hiện "Hết chỗ"
            foreach ($t['dot'] as [$sauNgay, $tong, $con]) {
                $ngay = today()->addDays($sauNgay)->toDateString();
                $dot = TourDeparture::where('tour_id', $tour->id)->whereDate('start_date', $ngay)->first()
                    ?? new TourDeparture(['tour_id' => $tour->id, 'start_date' => $ngay]);
                $dot->fill(['seats_total' => $tong, 'seats_left' => $con, 'status' => $con > 0 ? 'open' : 'full'])->save();
            }

            foreach ($t['lich'] as $i => $tieuDe) {
                TourItinerary::updateOrCreate(
                    ['tour_id' => $tour->id, 'day_number' => $i + 1],
                    ['title' => $tieuDe, 'description' => 'Ngày '.($i + 1).': '.$tieuDe.'. Nghỉ đêm tại khách sạn (ngày cuối trả phòng).', 'sort_order' => $i],
                );
            }

            foreach (range(1, 3) as $i) {
                TourImage::updateOrCreate(
                    ['tour_id' => $tour->id, 'path' => $this->anh($t['slug'].'-'.$i, 1000, 700)],
                    ['alt' => $t['ten'].' — ảnh '.$i, 'sort_order' => $i],
                );
            }

            foreach ($t['danh_gia'] ?? [] as [$ten, $sao, $noiDung]) {
                Review::updateOrCreate(['tour_id' => $tour->id, 'customer_name' => $ten], [
                    'rating' => $sao, 'content' => $noiDung, 'status' => 'approved',
                    'approved_by' => $adminId, 'approved_at' => now(),
                ]);
            }
        }

        foreach ([
            ['ha-long', 'Vịnh Hạ Long', 'Miền Bắc', 'mien-bac', 'Di sản thiên nhiên thế giới với hàng nghìn đảo đá vôi.'],
            ['sa-pa', 'Sa Pa', 'Miền Bắc', 'mien-bac', 'Ruộng bậc thang, đỉnh Fansipan và bản làng vùng cao.'],
            ['phu-quoc', 'Phú Quốc', 'Miền Nam', 'mien-nam', 'Đảo ngọc với biển xanh, cáp treo Hòn Thơm.'],
            ['da-lat', 'Đà Lạt', 'Tây Nguyên', 'tay-nguyen', 'Thành phố ngàn hoa, khí hậu mát mẻ quanh năm.'],
            ['seoul', 'Seoul', 'Đông Bắc Á', 'dong-bac-a', 'Cung điện cổ, phố mua sắm Myeongdong, đảo Nami.'],
            ['tokyo', 'Tokyo', 'Đông Bắc Á', 'dong-bac-a', 'Thủ đô hiện đại bên núi Phú Sĩ.'],
            ['bali', 'Bali', 'Đông Nam Á', 'dong-nam-a', 'Đảo thần với đền cổ, ruộng bậc thang Tegallalang.'],
            ['paris', 'Paris', 'Châu Âu', 'chau-au', 'Kinh đô ánh sáng với tháp Eiffel và bảo tàng Louvre.'],
        ] as $thuTu => [$slug, $ten, $khuVuc, $dm, $tomTat]) {
            Destination::updateOrCreate(['slug' => $slug], [
                'name' => $ten, 'region' => $khuVuc, 'category_slug' => $dm,
                'image' => $this->anh('dd-'.$slug, 900, 600),
                'summary' => $tomTat,
                'description' => '<p>'.$tomTat.'</p><p>Xem các tour '.$khuVuc.' của PSV Travel bên dưới.</p>',
                'is_featured' => $thuTu < 4, 'status' => 'published', 'sort_order' => $thuTu + 1,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function tours(): array
    {
        return [
            // ---------------- TRONG NƯỚC ----------------
            [
                'slug' => 'ha-noi-ha-long-sa-pa-5n4d', 'ten' => 'Hà Nội - Hạ Long - Sa Pa 5 ngày 4 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Miền Bắc', 'danh_muc' => 'mien-bac', 'ngay' => 5,
                'gia' => 7_990_000, 'gia_cu' => 8_590_000, 'nhan' => 'Bán chạy', 'noi_bat' => true,
                'diem_nhan' => ['Du thuyền vịnh Hạ Long', 'Chinh phục Fansipan', 'Phố cổ Hà Nội'],
                'mo_ta' => 'Hành trình trọn vẹn miền Bắc: thủ đô nghìn năm, kỳ quan Hạ Long và núi rừng Sa Pa.',
                'lich' => ['HCM - Hà Nội - Phố cổ', 'Hà Nội - Hạ Long, du thuyền', 'Hạ Long - Sa Pa', 'Fansipan - Bản Cát Cát', 'Sa Pa - Hà Nội - HCM'],
                'dot' => [[12, 30, 9], [26, 30, 30], [40, 30, 30]],
                'danh_gia' => [['Ngô Thị Hạnh', 5, 'Lịch trình hợp lý, du thuyền rất đẹp.'], ['Đinh Công Lực', 4, 'Sa Pa lạnh, nhớ mang áo ấm.']],
            ],
            [
                'slug' => 'ha-giang-dong-van-4n3d', 'ten' => 'Hà Giang - Đồng Văn - Mã Pì Lèng 4 ngày 3 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Miền Bắc', 'danh_muc' => 'mien-bac', 'ngay' => 4, 'tu' => 'Hà Nội',
                'gia' => 4_590_000, 'nhan' => 'Mùa hoa tam giác mạch',
                'diem_nhan' => ['Đèo Mã Pì Lèng, sông Nho Quế', 'Cột cờ Lũng Cú', 'Phố cổ Đồng Văn'],
                'mo_ta' => 'Cung đường đèo hùng vĩ bậc nhất Việt Nam, mùa hoa tam giác mạch tím cả núi rừng.',
                'lich' => ['Hà Nội - Hà Giang', 'Quản Bạ - Yên Minh - Đồng Văn', 'Lũng Cú - Mã Pì Lèng', 'Hà Giang - Hà Nội'],
                'dot' => [[9, 20, 0], [23, 20, 14]], // đợt gần nhất HẾT CHỖ
            ],
            [
                'slug' => 'phu-quoc-3n2d', 'ten' => 'Phú Quốc - Hòn Thơm - Grand World 3 ngày 2 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Miền Nam', 'danh_muc' => 'mien-nam', 'ngay' => 3,
                'gia' => 3_990_000, 'gia_cu' => 4_590_000, 'nhan' => 'Giảm giá', 'noi_bat' => true,
                'diem_nhan' => ['Cáp treo Hòn Thơm vượt biển', 'Grand World về đêm', 'Lặn ngắm san hô'],
                'mo_ta' => 'Nghỉ dưỡng đảo ngọc, biển xanh cát trắng, ăn hải sản tươi.',
                'lich' => ['HCM - Phú Quốc - Grand World', 'Nam đảo - Hòn Thơm', 'Chợ Dương Đông - HCM'],
                'dot' => [[6, 25, 3], [13, 25, 25], [20, 25, 25]],
                'danh_gia' => [['Lý Mỹ Duyên', 5, 'Biển đẹp, khách sạn sát biển.']],
            ],
            [
                'slug' => 'mien-tay-can-tho-2n1d', 'ten' => 'Miền Tây: Mỹ Tho - Bến Tre - Cần Thơ 2 ngày 1 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Miền Nam', 'danh_muc' => 'mien-nam', 'ngay' => 2,
                'gia' => 1_890_000,
                'diem_nhan' => ['Chợ nổi Cái Răng', 'Đi xuồng rạch dừa Bến Tre', 'Đờn ca tài tử'],
                'mo_ta' => 'Sông nước miệt vườn, trái cây và con người miền Tây hiếu khách.',
                'lich' => ['HCM - Mỹ Tho - Bến Tre - Cần Thơ', 'Chợ nổi Cái Răng - HCM'],
                'dot' => [[4, 35, 20], [11, 35, 35]],
            ],
            [
                'slug' => 'da-lat-3n2d', 'ten' => 'Đà Lạt mộng mơ 3 ngày 2 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Tây Nguyên', 'danh_muc' => 'tay-nguyen', 'ngay' => 3,
                'gia' => 2_790_000,
                'diem_nhan' => ['Đồi chè Cầu Đất', 'Thác Datanla', 'Chợ đêm Đà Lạt'],
                'mo_ta' => 'Thành phố ngàn hoa với khí hậu se lạnh, cà phê view đồi thông.',
                'lich' => ['HCM - Đà Lạt - Chợ đêm', 'Cầu Đất - Thác Datanla', 'Vườn dâu - HCM'],
                'dot' => [[5, 30, 0], [12, 30, 0], [19, 30, 11]], // hai đợt đầu hết chỗ
                'danh_gia' => [['Tạ Quang Vinh', 4, 'Không khí mát, đồ ăn ngon.']],
            ],
            [
                'slug' => 'nha-trang-binh-ba-3n2d', 'ten' => 'Nha Trang - Đảo Bình Ba 3 ngày 2 đêm',
                'loai' => 'domestic', 'khu_vuc' => 'Miền Trung', 'danh_muc' => 'mien-trung', 'ngay' => 3,
                'gia' => 3_290_000,
                'diem_nhan' => ['Đảo tôm hùm Bình Ba', 'Tháp Bà Ponagar', 'Tắm bùn khoáng'],
                'mo_ta' => 'Biển Nha Trang và đảo Bình Ba hoang sơ, thiên đường hải sản.',
                'lich' => ['HCM - Nha Trang', 'Đảo Bình Ba', 'Tắm bùn - HCM'],
                'dot' => [[8, 30, 30], [22, 30, 30]],
            ],
            // ---------------- NƯỚC NGOÀI ----------------
            [
                'slug' => 'han-quoc-seoul-nami-everland-5n4d', 'ten' => 'Hàn Quốc: Seoul - Nami - Everland 5 ngày 4 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Đông Bắc Á', 'nuoc' => 'Hàn Quốc', 'danh_muc' => 'dong-bac-a', 'ngay' => 5,
                'gia' => 13_990_000, 'gia_cu' => 15_490_000, 'nhan' => 'Mùa lá đỏ', 'noi_bat' => true,
                'diem_nhan' => ['Đảo Nami mùa thu', 'Công viên Everland', 'Mặc Hanbok tại cung Gyeongbok'],
                'mo_ta' => 'Xứ sở kim chi mùa lá đỏ, mua sắm Myeongdong, trải nghiệm văn hoá Hàn.',
                'lich' => ['HCM - Incheon', 'Đảo Nami - Seoul', 'Cung Gyeongbok - Myeongdong', 'Everland', 'Seoul - HCM'],
                'dot' => [[18, 25, 6], [32, 25, 25], [46, 25, 25]],
                'danh_gia' => [['Kiều Bảo Ngọc', 5, 'Nami đẹp như phim, HDV vui tính.'], ['Mạc Văn Tú', 4, 'Hơi lạnh nhưng rất đáng đi.']],
            ],
            [
                'slug' => 'nhat-ban-tokyo-phu-si-osaka-6n5d', 'ten' => 'Nhật Bản: Tokyo - Phú Sĩ - Kyoto - Osaka 6 ngày 5 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Đông Bắc Á', 'nuoc' => 'Nhật Bản', 'danh_muc' => 'dong-bac-a', 'ngay' => 6,
                'gia' => 29_900_000, 'nhan' => 'Cao cấp', 'noi_bat' => true,
                'diem_nhan' => ['Núi Phú Sĩ - làng cổ Oshino Hakkai', 'Chùa Vàng Kinkakuji', 'Tàu cao tốc Shinkansen'],
                'mo_ta' => 'Cung đường vàng Nhật Bản từ Tokyo hiện đại đến Kyoto cổ kính.',
                'lich' => ['HCM - Tokyo', 'Tokyo - Asakusa - Ginza', 'Phú Sĩ - Oshino Hakkai', 'Shinkansen - Kyoto', 'Osaka - Lâu đài Osaka', 'Osaka - HCM'],
                'dot' => [[30, 20, 12], [60, 20, 20]],
            ],
            [
                'slug' => 'trung-quoc-truong-gia-gioi-phuong-hoang-6n5d', 'ten' => 'Trung Quốc: Trương Gia Giới - Phượng Hoàng cổ trấn 6 ngày 5 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Trung Quốc', 'nuoc' => 'Trung Quốc', 'danh_muc' => 'trung-quoc', 'ngay' => 6,
                'gia' => 12_490_000,
                'diem_nhan' => ['Thiên Môn Sơn - đường cáp treo dài nhất thế giới', 'Cầu kính Đại Hiệp Cốc', 'Phượng Hoàng cổ trấn về đêm'],
                'mo_ta' => 'Núi non kỳ vĩ như phim Avatar và cổ trấn bên dòng Đà Giang.',
                'lich' => ['HCM - Trương Gia Giới', 'Thiên Môn Sơn', 'Viên Gia Giới - Thiên Tử Sơn', 'Đại Hiệp Cốc - Phượng Hoàng', 'Phượng Hoàng cổ trấn', 'Trương Gia Giới - HCM'],
                'dot' => [[21, 25, 17], [35, 25, 25]],
            ],
            [
                'slug' => 'singapore-malaysia-5n4d', 'ten' => 'Singapore - Malaysia 5 ngày 4 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Đông Nam Á', 'nuoc' => 'Singapore', 'danh_muc' => 'dong-nam-a', 'ngay' => 5,
                'gia' => 11_490_000,
                'diem_nhan' => ['Gardens by the Bay', 'Sentosa', 'Tháp đôi Petronas'],
                'mo_ta' => 'Hai quốc gia một hành trình: Singapore xanh sạch và Kuala Lumpur sôi động.',
                'lich' => ['HCM - Singapore', 'Sentosa - Gardens by the Bay', 'Singapore - Malacca', 'Kuala Lumpur - Genting', 'Kuala Lumpur - HCM'],
                'dot' => [[15, 30, 22], [29, 30, 30]],
            ],
            [
                'slug' => 'bali-4n3d', 'ten' => 'Bali - Đảo thần 4 ngày 3 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Đông Nam Á', 'nuoc' => 'Indonesia', 'danh_muc' => 'dong-nam-a', 'ngay' => 4,
                'gia' => 9_990_000, 'gia_cu' => 10_990_000, 'nhan' => 'Giảm giá',
                'diem_nhan' => ['Đền Tanah Lot hoàng hôn', 'Ruộng bậc thang Tegallalang', 'Xích đu Bali Swing'],
                'mo_ta' => 'Thiên đường nhiệt đới với đền cổ, ruộng bậc thang và bãi biển hoàng hôn.',
                'lich' => ['HCM - Bali - Kuta', 'Ubud - Tegallalang', 'Tanah Lot - Jimbaran', 'Bali - HCM'],
                'dot' => [[10, 20, 0], [24, 20, 20]],
                'danh_gia' => [['Âu Thị Kim', 5, 'Bali chill, ảnh sống ảo cực đẹp.']],
            ],
            [
                'slug' => 'chau-au-phap-thuy-si-y-10n9d', 'ten' => 'Châu Âu: Pháp - Thụy Sĩ - Ý 10 ngày 9 đêm',
                'loai' => 'abroad', 'khu_vuc' => 'Châu Âu', 'nuoc' => 'Pháp', 'danh_muc' => 'chau-au', 'ngay' => 10,
                'gia' => 59_900_000, 'nhan' => 'Cao cấp',
                'diem_nhan' => ['Tháp Eiffel - du thuyền sông Seine', 'Đỉnh Titlis tuyết phủ', 'Đấu trường Colosseum'],
                'mo_ta' => 'Ba quốc gia Tây Âu kinh điển: Paris lãng mạn, Thụy Sĩ thơ mộng, Ý cổ kính.',
                'lich' => ['HCM - Paris', 'Paris - Eiffel - Louvre', 'Paris - Dijon', 'Lucerne - đỉnh Titlis', 'Zurich - Milan', 'Milan - Venice', 'Venice - Florence', 'Florence - Rome', 'Rome - Vatican', 'Rome - HCM'],
                'dot' => [[45, 20, 8], [75, 20, 20]],
            ],
        ];
    }
}
