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
        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->enum('role', ['system', 'user', 'assistant']);
            $table->longText('content');
            $table->string('model', 64)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
