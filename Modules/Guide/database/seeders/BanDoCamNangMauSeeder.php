<?php

namespace Modules\Guide\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Guide\Models\Guide;
use Modules\Guide\Models\GuideCity;
use Modules\Guide\Models\GuideCountry;
use Modules\Guide\Models\GuideSpot;

/**
 * Dữ liệu MẪU cho bản đồ cẩm nang — chỉ để thử trên máy local:
 *
 *   php artisan db:seed --class="Modules\Guide\Database\Seeders\BanDoCamNangMauSeeder"
 *
 * Toạ độ là địa điểm thật, bài viết là bài mẫu ngắn. Không chạy trên
 * production. Xoá bằng `php artisan psv:don-du-lieu-mau`.
 */
class BanDoCamNangMauSeeder extends Seeder
{
    private const DU_LIEU = [
        'CHN' => ['Trung Quốc', 'Từ phố cổ Bắc Kinh tới đô thị hiện đại bậc nhất châu Á Thượng Hải.', [
            ['Thượng Hải', 31.2304, 121.4737, 11.6, 'Thành phố của Bến Thượng Hải, khu Phố Đông và những con hẻm thạch khố môn.', 'Cẩm nang Thượng Hải tự túc 5 ngày', [
                ['Bến Thượng Hải (The Bund)', 'tham_quan', 31.2400, 121.4900, 'Đại lộ ven sông với dãy kiến trúc châu Âu, đẹp nhất lúc lên đèn 19h–22h.', 'Ngắm Bến Thượng Hải về đêm: giờ đẹp, góc chụp, cách đi'],
                ['Tháp Minh Châu Phương Đông', 'tham_quan', 31.2397, 121.4998, 'Biểu tượng khu Phố Đông, đài quan sát kính ở độ cao 259 m.', null],
                ['Dự Viên', 'tham_quan', 31.2272, 121.4921, 'Khu vườn cổ thời nhà Minh giữa lòng thành phố, nên đến sớm trước 9h.', 'Dự Viên và phố cổ Thành Hoàng Miếu: đi gì, ăn gì'],
                ['Tiểu long bao Nam Tường', 'an_uong', 31.2275, 121.4915, 'Quán bánh bao súp hơn trăm năm tuổi cạnh Dự Viên.', 'Dự Viên và phố cổ Thành Hoàng Miếu: đi gì, ăn gì'],
                ['Phố đi bộ Nam Kinh', 'mua_sam', 31.2350, 121.4750, 'Con phố mua sắm sầm uất nhất, nối Quảng trường Nhân Dân với Bến Thượng Hải.', null],
                ['Điền Tử Phường', 'mua_sam', 31.2093, 121.4690, 'Mê cung hẻm nhỏ với quán cà phê, đồ thủ công, rất hợp chụp ảnh.', null],
                ['Thất Bảo cổ trấn', 'tham_quan', 31.1550, 121.3530, 'Thị trấn sông nước cổ ngay trong thành phố, đi tàu điện ngầm tuyến 9.', null],
            ]],
            ['Bắc Kinh', 39.9042, 116.4074, 11, 'Thủ đô ngàn năm với Tử Cấm Thành và Vạn Lý Trường Thành.', 'Cẩm nang Bắc Kinh: lịch trình 4 ngày cho người đi lần đầu', [
                ['Tử Cấm Thành', 'tham_quan', 39.9163, 116.3972, 'Phải đặt vé trước bằng hộ chiếu, đóng cửa thứ Hai.', 'Tham quan Tử Cấm Thành: đặt vé, lộ trình 3 giờ'],
                ['Quảng trường Thiên An Môn', 'tham_quan', 39.9055, 116.3976, 'Xem lễ thượng cờ lúc bình minh.', null],
                ['Vạn Lý Trường Thành Mộ Điền Dục', 'thien_nhien', 40.4319, 116.5704, 'Đoạn trường thành ít đông, có cáp treo và máng trượt.', null],
            ]],
        ]],
        'JPN' => ['Nhật Bản', 'Đền chùa cổ kính, ẩm thực tinh tế và mùa hoa anh đào.', [
            ['Tokyo', 35.6762, 139.6503, 10.8, 'Thủ đô sôi động từ Asakusa cổ kính tới Shibuya hiện đại.', 'Cẩm nang Tokyo cho người đi lần đầu', [
                ['Chùa Sensō-ji', 'tham_quan', 35.7148, 139.7967, 'Ngôi chùa cổ nhất Tokyo, phố Nakamise bán đồ lưu niệm.', 'Asakusa nửa ngày: chùa Sensō-ji, phố Nakamise, sông Sumida'],
                ['Ngã tư Shibuya', 'tham_quan', 35.6595, 139.7005, 'Ngã tư đông người qua nhất thế giới.', null],
                ['Tháp Tokyo', 'tham_quan', 35.6586, 139.7454, 'Ngắm hoàng hôn rồi lên đèn ở công viên Shiba.', null],
            ]],
            ['Osaka', 34.6937, 135.5023, 11.2, 'Thiên đường ẩm thực đường phố của Nhật.', null, [
                ['Lâu đài Osaka', 'tham_quan', 34.6873, 135.5262, 'Đẹp nhất mùa hoa anh đào cuối tháng 3.', null],
                ['Phố Dotonbori', 'an_uong', 34.6687, 135.5013, 'Takoyaki, okonomiyaki và biển hiệu Glico nổi tiếng.', 'Ăn gì ở Dotonbori: 7 món phải thử'],
            ]],
        ]],
        'KOR' => ['Hàn Quốc', 'Cung điện, phố mua sắm và ẩm thực đường phố Seoul.', [
            ['Seoul', 37.5665, 126.9780, 11.2, 'Thủ đô Hàn Quốc giao hoà giữa cung điện cổ và phố thời trang.', null, [
                ['Cung Gyeongbokgung', 'tham_quan', 37.5796, 126.9770, 'Mặc hanbok được vào cửa miễn phí, xem đổi gác 10h và 14h.', 'Cung Gyeongbokgung: thuê hanbok, giờ đổi gác'],
                ['Phố Myeongdong', 'mua_sam', 37.5636, 126.9850, 'Mỹ phẩm, đồ ăn vặt đường phố buổi tối.', null],
                ['Tháp Namsan', 'tham_quan', 37.5512, 126.9882, 'Ngắm toàn cảnh Seoul, đi cáp treo lên đỉnh.', null],
            ]],
        ]],
        'SGP' => ['Singapore', 'Quốc đảo xanh sạch, dễ đi tự túc.', [
            ['Singapore', 1.2903, 103.8520, 12.2, 'Vịnh Marina, vườn nhiệt đới và các khu ẩm thực bình dân.', null, [
                ['Marina Bay Sands', 'tham_quan', 1.2834, 103.8607, 'Xem nhạc nước Spectra lúc 20h và 21h, miễn phí.', null],
                ['Gardens by the Bay', 'thien_nhien', 1.2816, 103.8636, 'Siêu cây phát sáng lúc 19h45 và 20h45.', 'Gardens by the Bay: mua vé nào, xem gì miễn phí'],
                ['Chợ ẩm thực Lau Pa Sat', 'an_uong', 1.2806, 103.8504, 'Phố sa tế mở từ 19h, ăn khuya rất vui.', null],
            ]],
        ]],
        'VNM' => ['Việt Nam', 'Những điểm đến trong nước được khách PSV yêu thích.', [
            ['Đà Nẵng', 16.0544, 108.2022, 11.3, 'Thành phố biển đáng sống với Bà Nà, Cầu Rồng và biển Mỹ Khê.', null, [
                ['Cầu Rồng', 'tham_quan', 16.0610, 108.2272, 'Rồng phun lửa, phun nước 21h tối thứ Bảy, Chủ nhật.', null],
                ['Cầu Vàng – Bà Nà Hills', 'thien_nhien', 15.9951, 107.9966, 'Đi cáp treo sớm lúc 8h để tránh đông.', null],
                ['Biển Mỹ Khê', 'thien_nhien', 16.0544, 108.2470, 'Tắm biển đẹp nhất buổi sáng sớm và chiều muộn.', null],
            ]],
        ]],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Đây là dữ liệu MẪU — không chạy trên production.');

            return;
        }

        $bai = fn (?string $tieuDe) => $tieuDe ? Guide::updateOrCreate(['slug' => 'mau-'.Str::slug($tieuDe)], [
            'title' => $tieuDe,
            'excerpt' => 'Bài mẫu cho bản đồ cẩm nang — thay bằng nội dung thật trong trang quản trị.',
            'content' => '<p>Đây là bài cẩm nang <strong>mẫu</strong> để thử bản đồ. Nội dung thật do biên tập viên viết trong mục Cẩm nang.</p>',
            'cover_image' => 'https://picsum.photos/seed/'.Str::slug($tieuDe).'/1000/600',
            'category' => 'diem-den',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]) : null;

        $thuTuNuoc = 0;
        foreach (self::DU_LIEU as $code => [$tenNuoc, $gioiThieu, $dsTp]) {
            $nuoc = GuideCountry::updateOrCreate(['code' => $code], [
                'name' => $tenNuoc, 'slug' => Str::slug($tenNuoc), 'intro' => $gioiThieu,
                'status' => 'published', 'sort_order' => ++$thuTuNuoc,
            ]);
            foreach ($dsTp as $i => [$tenTp, $lat, $lng, $zoom, $moTa, $baiTongQuan, $dsDiem]) {
                $tp = GuideCity::updateOrCreate(['slug' => Str::slug($tenTp).($code === 'SGP' ? '-sg' : '')], [
                    'guide_country_id' => $nuoc->id, 'name' => $tenTp, 'lat' => $lat, 'lng' => $lng, 'zoom' => $zoom,
                    'intro' => $moTa, 'cover_image' => 'https://picsum.photos/seed/tp-'.Str::slug($tenTp).'/900/500',
                    'guide_id' => $bai($baiTongQuan)?->id, 'status' => 'published', 'sort_order' => $i + 1,
                ]);
                foreach ($dsDiem as $j => [$tenDiem, $loai, $dLat, $dLng, $tomTat, $tieuDeBai]) {
                    GuideSpot::updateOrCreate(['guide_city_id' => $tp->id, 'name' => $tenDiem], [
                        'type' => $loai, 'lat' => $dLat, 'lng' => $dLng, 'summary' => $tomTat,
                        'guide_id' => ($bai($tieuDeBai) ?? $tp->guide)?->id, 'status' => 'published', 'sort_order' => $j + 1,
                    ]);
                }
            }
        }

        $this->command?->info('Đã tạo bản đồ cẩm nang mẫu: '.GuideCountry::count().' nước, '
            .GuideCity::count().' thành phố, '.GuideSpot::count().' điểm.');
    }
}
