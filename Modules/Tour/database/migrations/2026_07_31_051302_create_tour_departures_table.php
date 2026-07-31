<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->unsignedBigInteger('price_override')->nullable();
            $table->unsignedInteger('seats_total')->default(0);
            $table->unsignedInteger('seats_left')->default(0);
            $table->string('status')->default('open');      // open | closed | full
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_departures');
    }
};