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
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->text('objective')->nullable()->after('soul');
            $table->text('tone')->nullable()->after('objective');
            $table->text('rules')->nullable()->after('tone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_configs', function (Blueprint $table) {
            $table->dropColumn(['objective', 'tone', 'rules']);
        });
    }
};
