<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default('domestic');   // domestic | abroad
            $table->string('region')->nullable();
            $table->string('country')->nullable();          // chỉ tour nước ngoài
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedSmallInteger('duration_nights')->default(0);
            $table->string('departure_from')->nullable();

            $table->unsignedBigInteger('adult_price')->default(0); // VND
            $table->unsignedBigInteger('child_price')->nullable();
            $table->unsignedBigInteger('old_price')->nullable();

            $table->string('tag')->nullable();              // Bán chạy / Mới...
            $table->string('cover_image')->nullable();

            $table->json('highlights')->nullable();         // điểm nổi bật
            $table->json('included')->nullable();           // dịch vụ bao gồm
            $table->json('excluded')->nullable();           // không bao gồm
            $table->longText('cancellation_policy')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('rating', 2, 1)->nullable();    // nhập tay tạm thời
            $table->unsignedInteger('review_count')->default(0);

            $table->string('status')->default('draft');     // draft | published | hidden
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};