<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Hạng phòng "chỉ người lớn" (resort / villa chỉ nhận khách từ 18 tuổi):
 *   website ẩn ô trẻ em, em bé và không nhận đơn có trẻ em cho hạng phòng đó.
 * - Hạng sao resort có nửa sao (4,5*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maldives_room_types', function (Blueprint $t) {
            $t->boolean('adults_only')->default(false)->after('capacity');
        });
        Schema::table('maldives_resorts', function (Blueprint $t) {
            $t->decimal('stars', 2, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maldives_room_types', function (Blueprint $t) {
            $t->dropColumn('adults_only');
        });
        Schema::table('maldives_resorts', function (Blueprint $t) {
            $t->unsignedTinyInteger('stars')->nullable()->change();
        });
    }
};
