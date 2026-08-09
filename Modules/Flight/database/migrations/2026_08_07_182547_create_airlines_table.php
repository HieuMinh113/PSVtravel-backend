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
        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();          // VN, VJ, QH...
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('country')->nullable();
            $table->string('website')->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('published'); // published | hidden
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airlines');
    }
};
