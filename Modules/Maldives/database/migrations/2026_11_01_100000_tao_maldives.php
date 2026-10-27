<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Combo nghỉ dưỡng Maldives — tách riêng hoàn toàn khỏi tour.
 *
 *  resort (giới thiệu, ảnh, tiện ích, quy định) ─┬─ hạng phòng (sức chứa)
 *                                                 └─ combo (hãng bay, lịch trình, nơi khởi hành + phụ thu)
 *                                                      └─ ngày khởi hành: giá USD/khách theo từng hạng phòng
 *  đơn đặt combo: chốt lại giá, tỷ giá lúc khách đặt
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maldives_resorts', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 150)->unique();
            $t->string('name');
            $t->unsignedTinyInteger('stars')->nullable();
            $t->string('atoll')->nullable();             // Noonu Atoll
            $t->string('package_name')->nullable();      // WOW Premium All-Inclusive
            $t->text('intro')->nullable();               // mỗi đoạn cách một dòng trống
            $t->json('highlights')->nullable();          // danh sách ngắn
            $t->string('cover_image')->nullable();
            $t->json('gallery')->nullable();
            $t->string('video_url')->nullable();
            $t->json('amenities')->nullable();           // [{title, items: [..]}]
            $t->json('policies')->nullable();            // [{title, content}]
            $t->string('status', 20)->default('published');
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('maldives_room_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('resort_id')->constrained('maldives_resorts')->cascadeOnDelete();
            $t->string('name');                          // Water Villa with Pool & Slide
            $t->string('capacity')->nullable();          // 3 người lớn hoặc 2 người lớn + 1 trẻ em
            $t->text('description')->nullable();
            $t->string('image')->nullable();
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('maldives_combos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('resort_id')->constrained('maldives_resorts')->restrictOnDelete();
            $t->string('slug', 150)->unique();
            $t->string('name');
            $t->string('seo_title', 70)->nullable();
            $t->string('summary', 500)->nullable();      // mô tả ngắn (Google)
            $t->unsignedTinyInteger('duration_days')->nullable();
            $t->unsignedTinyInteger('duration_nights')->nullable();
            $t->string('stay_summary')->nullable();      // 01 đêm khách sạn + 02 đêm resort
            $t->string('airline')->nullable();
            $t->string('transfer')->nullable();          // Thuỷ phi cơ
            $t->string('cover_image')->nullable();
            $t->json('highlights')->nullable();
            $t->json('departure_cities')->nullable();    // [{city, surcharge_usd, flights}]
            $t->unsignedTinyInteger('child_percent')->nullable(); // 75 = trẻ em tính 75% giá người lớn
            $t->string('child_age')->nullable();         // 2 – 12 tuổi
            $t->string('infant_note')->nullable();       // trẻ dưới 2 tuổi: Liên hệ
            $t->unsignedSmallInteger('min_group')->nullable();
            $t->json('included')->nullable();
            $t->json('excluded')->nullable();
            $t->json('notes')->nullable();               // [{title, content}]
            $t->string('status', 20)->default('draft');
            $t->boolean('is_featured')->default(false);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('maldives_combo_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('combo_id')->constrained('maldives_combos')->cascadeOnDelete();
            $t->unsignedTinyInteger('day_number');
            $t->string('title');
            $t->string('meals')->nullable();
            $t->text('description')->nullable();
            $t->json('images')->nullable();
            $t->timestamps();
        });

        Schema::create('maldives_combo_dates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('combo_id')->constrained('maldives_combos')->cascadeOnDelete();
            $t->date('start_date');
            $t->string('label')->nullable();             // Lịch Tết
            $t->json('prices')->nullable();              // {room_type_id: giá USD / người lớn}
            $t->string('status', 20)->default('open');   // open | full | closed
            $t->timestamps();
        });

        Schema::create('maldives_bookings', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('combo_id')->nullable()->constrained('maldives_combos')->nullOnDelete();
            $t->foreignId('combo_date_id')->nullable()->constrained('maldives_combo_dates')->nullOnDelete();
            $t->foreignId('room_type_id')->nullable()->constrained('maldives_room_types')->nullOnDelete();
            // Chốt lại lúc đặt: nhân viên sửa combo sau này không làm đổi đơn cũ
            $t->string('combo_name')->nullable();
            $t->date('start_date')->nullable();
            $t->string('room_type_name')->nullable();
            $t->string('departure_city')->nullable();
            $t->integer('surcharge_usd')->nullable();
            $t->unsignedSmallInteger('adults')->default(1);
            $t->unsignedSmallInteger('children')->default(0);
            $t->unsignedSmallInteger('infants')->default(0);
            $t->unsignedSmallInteger('rooms')->nullable();
            $t->integer('unit_price_usd')->nullable();
            $t->unsignedTinyInteger('child_percent')->nullable();
            $t->integer('total_usd')->nullable();
            $t->integer('exchange_rate')->nullable();
            $t->bigInteger('total_vnd')->nullable();
            $t->string('customer_name');
            $t->string('customer_phone', 30);
            $t->string('customer_email')->nullable();
            $t->text('note')->nullable();
            $t->string('status', 20)->default('pending');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->bigInteger('paid_vnd')->default(0);
            $t->text('admin_note')->nullable();
            $t->string('source', 20)->default('web');
            $t->string('kenh', 50)->nullable();
            $t->json('utm')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['status', 'created_at']);
        });

        // Cấu hình công khai (nhóm general → website đọc được qua /settings)
        if (Schema::hasTable('settings')) {
            foreach ([
                ['maldives_ty_gia', 'Maldives: tỷ giá USD tạm tính (VNĐ cho 1 USD, VD 26300) — website hiện "≈ … triệu đồng"', 'text', 100],
                ['maldives_gioi_thieu', 'Maldives: đoạn giới thiệu đầu trang /maldives (để trống = câu mặc định)', 'textarea', 101],
            ] as [$key, $label, $type, $sort]) {
                if (! DB::table('settings')->where('key', $key)->exists()) {
                    DB::table('settings')->insert(['key' => $key, 'label' => $label, 'group' => 'general', 'type' => $type,
                        'value' => null, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maldives_bookings');
        Schema::dropIfExists('maldives_combo_dates');
        Schema::dropIfExists('maldives_combo_days');
        Schema::dropIfExists('maldives_combos');
        Schema::dropIfExists('maldives_room_types');
        Schema::dropIfExists('maldives_resorts');
        DB::table('settings')->whereIn('key', ['maldives_ty_gia', 'maldives_gioi_thieu'])->delete();
    }
};
