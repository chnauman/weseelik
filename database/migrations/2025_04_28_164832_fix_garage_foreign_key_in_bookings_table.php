<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Drop the wrong foreign key
            $table->dropForeign('garage_fk_10450133');

            // Add the correct foreign key
            $table->foreign('garage_id', 'garage_fk_10450133')
                ->references('id')
                ->on('garages')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Rollback: drop the corrected FK and recreate the old FK
            $table->dropForeign('garage_fk_10450133');

            $table->foreign('garage_id', 'garage_fk_10450133')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }
};
