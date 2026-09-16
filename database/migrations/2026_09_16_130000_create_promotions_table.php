<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('discount_label')->nullable(); // vd: -30%, Giảm 2 triệu
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('old_price')->nullable();
            $table->string('link_url')->nullable();        // dẫn tới tour/vé/visa
            $table->string('badge')->nullable();           // vd: HOT, Sắp hết
            $table->string('status')->default('published'); // published | hidden
            $table->unsignedInteger('sort_order')->default(0);
            $table->date('ends_at')->nullable();           // hạn khuyến mãi
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
