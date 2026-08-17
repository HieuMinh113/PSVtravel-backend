<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Banner\Models\Banner;
use Modules\Booking\Models\Booking;
use Modules\Booking\Models\Payment;
use Modules\Category\Models\Category;
use Modules\Flight\Models\Airline;
use Modules\Flight\Models\FlightDeal;
use Modules\Guide\Models\Guide;
use Modules\Moment\Models\Moment;
use Modules\Review\Models\Review;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourDeparture;
use Modules\Tour\Models\TourItinerary;
use Modules\Tour\Models\TourImage;
use Modules\Visa\Models\VisaCountry;
use Modules\Visa\Models\VisaProvider;

class DemoSeeder extends Seeder
{
    // Ảnh mẫu ổn định (picsum trả ảnh thật, không 404). Thay bằng ảnh thật khi có.
    private function anh(string $seed, int $w = 1200, int $h = 700): string
    {
        return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
    }

    public function run(): void
    {
        $admin = User::where('email', 'admin@psvtravel.com')->first();
        $adminId = $admin?->id;

        // ---------- DANH MỤC ----------
        $dmTrongNuoc = Category::updateOrCreate(
            ['slug' => 'mien-trung'],
            ['type' => 'domestic', 'name' => 'Miền Trung', 'status' => 'published', 'sort_order' => 1,
             'image' => $this->anh('cat-mientrung', 600, 400),
             'description' => 'Các tour khám phá dải đất miền Trung.'],
        );

        $dmNuocNgoai = Category::updateOrCreate(
            ['slug' => 'dong-nam-a'],
            ['type' => 'abroad', 'name' => 'Đông Nam Á', 'status' => 'published', 'sort_order' => 1,
             'image' => $this->anh('cat-dongnama', 600, 400),
             'description' => 'Tour các nước Đông Nam Á giá tốt.'],
        );

        // ---------- TOUR 1: Đà Nẵng (trong nước) ----------
        $tour1 = Tour::updateOrCreate(
            ['slug' => 'da-nang-hoi-an-3n2d'],
            [
                'name' => 'Đà Nẵng - Hội An 3 ngày 2 đêm',
                'type' => 'domestic',
                'region' => 'Miền Trung',
                'country' => null,
                'duration_days' => 3,
                'duration_nights' => 2,
                'departure_from' => 'Hồ Chí Minh',
                'adult_price' => 4990000,
                'child_price' => 3490000,
                'old_price' => 5990000,
                'tag' => 'Bán chạy',
                'cover_image' => $this->anh('tour-danang', 1200, 700),
                'highlights' => ['Bà Nà Hills - Cầu Vàng', 'Phố cổ Hội An về đêm', 'Bãi biển Mỹ Khê'],
                'included' => ['Vé máy bay khứ hồi', 'Khách sạn 4 sao', 'Ăn theo chương trình', 'HDV suốt tuyến'],
                'excluded' => ['Chi phí cá nhân', 'Tip cho HDV và tài xế'],
                'cancellation_policy' => 'Huỷ trước 7 ngày hoàn 80%, trước 3 ngày hoàn 50%.',
                'description' => 'Hành trình khám phá Đà Nẵng năng động và Hội An cổ kính.',
                'status' => 'published',
                'is_featured' => true,
                'sort_order' => 1,
            ],
        );
        $tour1->categories()->syncWithoutDetaching([$dmTrongNuoc->id]);

        TourDeparture::updateOrCreate(
            ['tour_id' => $tour1->id, 'start_date' => Carbon::now()->addDays(15)->toDateString()],
            ['seats_total' => 30, 'seats_left' => 22, 'status' => 'open'],
        );
        TourDeparture::updateOrCreate(
            ['tour_id' => $tour1->id, 'start_date' => Carbon::now()->addDays(30)->toDateString()],
            ['seats_total' => 30, 'seats_left' => 30, 'status' => 'open', 'price_override' => 5290000],
        );

        $lichTrinh1 = [
            [1, 'HCM - Đà Nẵng - Bà Nà Hills', 'Bay ra Đà Nẵng, chinh phục Cầu Vàng và khu vui chơi Bà Nà.'],
            [2, 'Hội An - Rừng dừa Bảy Mẫu', 'Tham quan phố cổ Hội An, trải nghiệm rừng dừa.'],
            [3, 'Ngũ Hành Sơn - Tiễn khách', 'Viếng Ngũ Hành Sơn, mua đặc sản, ra sân bay về HCM.'],
        ];
        foreach ($lichTrinh1 as $i => $lt) {
            TourItinerary::updateOrCreate(
                ['tour_id' => $tour1->id, 'day_number' => $lt[0]],
                ['title' => $lt[1], 'description' => $lt[2], 'sort_order' => $i],
            );
        }

        foreach (['danang-1', 'danang-2', 'danang-3'] as $i => $seed) {
            TourImage::updateOrCreate(
                ['tour_id' => $tour1->id, 'path' => $this->anh($seed, 1000, 700)],
                ['alt' => 'Đà Nẵng '.($i + 1), 'sort_order' => $i],
            );
        }

        // ---------- TOUR 2: Thái Lan (nước ngoài) ----------
        $tour2 = Tour::updateOrCreate(
            ['slug' => 'thai-lan-bangkok-pattaya-5n4d'],
            [
                'name' => 'Thái Lan: Bangkok - Pattaya 5 ngày 4 đêm',
                'type' => 'abroad',
                'region' => null,
                'country' => 'Thái Lan',
                'duration_days' => 5,
                'duration_nights' => 4,
                'departure_from' => 'Hồ Chí Minh',
                'adult_price' => 6990000,
                'child_price' => 4990000,
                'old_price' => 8490000,
                'tag' => 'Mới',
                'cover_image' => $this->anh('tour-thailan', 1200, 700),
                'highlights' => ['Chùa Phật Vàng', 'Đảo San Hô Coral', 'Chợ nổi Pattaya', 'Show Alcazar'],
                'included' => ['Vé máy bay', 'Khách sạn 4 sao', 'Visa (nếu cần)', 'Ăn uống theo chương trình'],
                'excluded' => ['Chi phí cá nhân', 'Tip HDV bản địa'],
                'cancellation_policy' => 'Huỷ trước 10 ngày hoàn 70%, trước 5 ngày hoàn 40%.',
                'description' => 'Khám phá Bangkok sôi động và Pattaya biển xanh nắng vàng.',
                'status' => 'published',
                'is_featured' => false,
                'sort_order' => 2,
            ],
        );
        $tour2->categories()->syncWithoutDetaching([$dmNuocNgoai->id]);

        $dep2 = TourDeparture::updateOrCreate(
            ['tour_id' => $tour2->id, 'start_date' => Carbon::now()->addDays(20)->toDateString()],
            ['seats_total' => 25, 'seats_left' => 18, 'status' => 'open'],
        );

        $lichTrinh2 = [
            [1, 'HCM - Bangkok', 'Bay sang Bangkok, tham quan chùa Phật Vàng.'],
            [2, 'Bangkok - Pattaya', 'Di chuyển Pattaya, xem show Alcazar.'],
            [3, 'Đảo San Hô Coral', 'Tắm biển, trò chơi biển tại đảo Coral.'],
            [4, 'Chợ nổi - Mua sắm', 'Trải nghiệm chợ nổi, mua sắm đặc sản.'],
            [5, 'Bangkok - HCM', 'Tự do mua sắm, ra sân bay về nước.'],
        ];
        foreach ($lichTrinh2 as $i => $lt) {
            TourItinerary::updateOrCreate(
                ['tour_id' => $tour2->id, 'day_number' => $lt[0]],
                ['title' => $lt[1], 'description' => $lt[2], 'sort_order' => $i],
            );
        }

        foreach (['thailan-1', 'thailan-2', 'thailan-3'] as $i => $seed) {
            TourImage::updateOrCreate(
                ['tour_id' => $tour2->id, 'path' => $this->anh($seed, 1000, 700)],
                ['alt' => 'Thái Lan '.($i + 1), 'sort_order' => $i],
            );
        }

        // ---------- ĐÁNH GIÁ (đã duyệt → điểm sao tự tính) ----------
        $danhGia = [
            [$tour1->id, 'Nguyễn Văn An', 5, 'Tour tuyệt vời, HDV nhiệt tình!', 'approved'],
            [$tour1->id, 'Trần Thị Bình', 4, 'Khách sạn đẹp, ăn ngon. Sẽ quay lại.', 'approved'],
            [$tour1->id, 'Lê Hoàng Cường', 5, 'Cầu Vàng đúng chuẩn sống ảo.', 'approved'],
            [$tour2->id, 'Phạm Thu Dung', 5, 'Thái Lan quá vui, giá hợp lý.', 'approved'],
            [$tour2->id, 'Võ Minh Đức', 3, 'Ổn nhưng lịch trình hơi dày.', 'approved'],
            [$tour2->id, 'Khách ẩn danh', 4, 'Chờ duyệt thử.', 'pending'],
        ];
        foreach ($danhGia as $dg) {
            Review::updateOrCreate(
                ['tour_id' => $dg[0], 'customer_name' => $dg[1]],
                [
                    'rating' => $dg[2],
                    'content' => $dg[3],
                    'status' => $dg[4],
                    'approved_by' => $dg[4] === 'approved' ? $adminId : null,
                    'approved_at' => $dg[4] === 'approved' ? now() : null,
                ],
            );
        }

        // ---------- ĐƠN ĐẶT TOUR ----------
        $don1 = Booking::updateOrCreate(
            ['customer_email' => 'khach1@example.com', 'tour_id' => $tour1->id],
            [
                'tour_departure_id' => $tour1->departures()->first()?->id,
                'customer_name' => 'Đặng Quốc Khách',
                'customer_phone' => '0901234567',
                'adults' => 2, 'children' => 1,
                'unit_price_adult' => 4990000, 'unit_price_child' => 3490000,
                'total_price' => 2 * 4990000 + 1 * 3490000,
                'status' => 'pending', 'payment_status' => 'unpaid',
                'note' => 'Cần phòng tầng cao.',
            ],
        );

        $don2 = Booking::updateOrCreate(
            ['customer_email' => 'khach2@example.com', 'tour_id' => $tour2->id],
            [
                'tour_departure_id' => $dep2->id,
                'customer_name' => 'Hồ Thị Mai',
                'customer_phone' => '0912345678',
                'adults' => 2, 'children' => 0,
                'unit_price_adult' => 6990000, 'unit_price_child' => 0,
                'total_price' => 2 * 6990000,
                'status' => 'confirmed', 'payment_status' => 'unpaid',
                'created_at' => now()->subDays(3),
            ],
        );

        // Khoản thu cho đơn 2 (Payment saved event tự cập nhật payment_status)
        Payment::updateOrCreate(
            ['booking_id' => $don2->id, 'transaction_ref' => 'DEMO-COC-001'],
            [
                'method' => 'bank_transfer', 'amount' => 5000000, 'status' => 'success',
                'received_by' => $adminId, 'paid_at' => now()->subDays(2),
                'note' => 'Đặt cọc 50%.',
            ],
        );

        // ---------- BANNER ----------
        Banner::updateOrCreate(
            ['title' => 'Ưu đãi hè - Giảm đến 30%'],
            ['subtitle' => 'Đặt tour ngay hôm nay', 'image' => $this->anh('banner-he', 1920, 700),
             'link' => '/tour-trong-nuoc', 'status' => 'published', 'sort_order' => 1],
        );
        Banner::updateOrCreate(
            ['title' => 'Khám phá Đông Nam Á'],
            ['subtitle' => 'Tour nước ngoài giá tốt', 'image' => $this->anh('banner-dna', 1920, 700),
             'link' => '/tour-nuoc-ngoai', 'status' => 'published', 'sort_order' => 2],
        );

        // ---------- CẨM NANG ----------
        Guide::updateOrCreate(
            ['slug' => 'kinh-nghiem-di-da-nang'],
            [
                'title' => 'Kinh nghiệm du lịch Đà Nẵng từ A đến Z',
                'excerpt' => 'Tổng hợp mọi điều cần biết khi đi Đà Nẵng.',
                'content' => '<p>Đà Nẵng là điểm đến không thể bỏ qua...</p><p>Thời điểm đẹp nhất là từ tháng 3 đến tháng 8.</p>',
                'cover_image' => $this->anh('guide-danang', 1000, 600),
                'author_id' => $adminId,
                'category' => 'kinh-nghiem',
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'sort_order' => 1,
            ],
        );

        // ---------- KHOẢNH KHẮC DU KHÁCH ----------
        Moment::updateOrCreate(
            ['caption' => 'Check-in Cầu Vàng'],
            ['image' => $this->anh('moment-1', 800, 800), 'customer_name' => 'Anh Tuấn',
             'tour_id' => $tour1->id, 'status' => 'published', 'sort_order' => 1],
        );
        Moment::updateOrCreate(
            ['caption' => 'Biển Pattaya'],
            ['image' => $this->anh('moment-2', 800, 800), 'customer_name' => 'Chị Lan',
             'tour_id' => $tour2->id, 'status' => 'published', 'sort_order' => 2],
        );

        // ---------- HÃNG BAY + CHẶNG ----------
        $vna = Airline::updateOrCreate(
            ['code' => 'VN'],
            ['name' => 'Vietnam Airlines', 'country' => 'Việt Nam',
             'logo' => $this->anh('airline-vna', 200, 100), 'status' => 'published', 'sort_order' => 1],
        );
        FlightDeal::updateOrCreate(
            ['airline_id' => $vna->id, 'from_city' => 'Hồ Chí Minh', 'to_city' => 'Hà Nội'],
            ['trip_type' => 'round_trip', 'price' => 1590000, 'old_price' => 2100000,
             'valid_to' => Carbon::now()->addMonths(2)->toDateString(),
             'status' => 'published', 'sort_order' => 1],
        );
        FlightDeal::updateOrCreate(
            ['airline_id' => $vna->id, 'from_city' => 'Hồ Chí Minh', 'to_city' => 'Đà Nẵng'],
            ['trip_type' => 'one_way', 'price' => 890000,
             'status' => 'published', 'sort_order' => 2],
        );

        // ---------- VISA ----------
        VisaCountry::updateOrCreate(
            ['slug' => 'han-quoc'],
            [
                'name' => 'Hàn Quốc', 'flag_image' => $this->anh('visa-hanquoc', 400, 300),
                'visa_type' => 'tourist', 'price' => 1500000,
                'processing_time' => '7-10 ngày làm việc', 'success_rate' => 95,
                'required_documents' => ['Hộ chiếu', 'Ảnh thẻ 3.5x4.5', 'Sổ hộ khẩu', 'Sao kê ngân hàng'],
                'description' => '<p>Dịch vụ visa du lịch Hàn Quốc trọn gói.</p>',
                'status' => 'published', 'sort_order' => 1,
            ],
        );
        VisaProvider::updateOrCreate(
            ['name' => 'Đối tác Visa Toàn Cầu'],
            ['contact_person' => 'Nguyễn Văn Đối Tác', 'phone' => '0288888888',
             'email' => 'doitac@visa.vn', 'address' => '123 Nguyễn Huệ, Q1, HCM',
             'status' => 'active', 'note' => 'Đối tác chính, chiết khấu 10%.'],
        );

        $this->command->info('Đã tạo xong dữ liệu mẫu: 2 tour + đơn/đánh giá/banner/cẩm nang/khoảnh khắc/vé/visa.');
    }
}