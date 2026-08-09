<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visa_countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('flag_image')->nullable();
            $table->string('visa_type')->default('tourist');   // tourist | business | study
            $table->unsignedBigInteger('price')->default(0);
            $table->string('processing_time')->nullable();     // "7-10 ngày làm việc"
            $table->unsignedTinyInteger('success_rate')->nullable(); // %
            $table->json('required_documents')->nullable();
            $table->longText('description')->nullable();
            $table->string('status')->default('published');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_countries');
    }
};
