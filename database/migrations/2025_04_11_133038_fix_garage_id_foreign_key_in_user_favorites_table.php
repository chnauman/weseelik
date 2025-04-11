<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixGarageIdForeignKeyInUserFavoritesTable extends Migration
{
    public function up()
    {
        Schema::table('user_favorites', function (Blueprint $table) {
            // Drop the incorrect foreign key first
            $table->dropForeign('garage_fk_10450115');

            // Add the correct foreign key referencing garages table
            $table->foreign('garage_id', 'garage_fk_10450115')
                ->references('id')
                ->on('garages')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('user_favorites', function (Blueprint $table) {
            // Drop the corrected foreign key
            $table->dropForeign('garage_fk_10450115');

            // Restore the old one (if needed)
            $table->foreign('garage_id', 'garage_fk_10450115')
                ->references('id')
                ->on('users');
        });
    }
}
