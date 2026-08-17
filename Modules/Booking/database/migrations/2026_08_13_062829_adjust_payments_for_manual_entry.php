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
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('gateway', 'method');
            $table->string('transaction_ref')->nullable()->change();
            $table->foreignId('received_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $table->text('note')->nullable()->after('received_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('method', 'gateway');
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('note');
        });
    }
};
