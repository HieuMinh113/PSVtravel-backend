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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();       // hotline, email, address, facebook...
            $table->text('value')->nullable();
            $table->string('group')->default('general'); // general | contact | social | seo
            $table->string('label');               // tên hiển thị tiếng Việt
            $table->string('type')->default('text'); // text | textarea | image | url
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
