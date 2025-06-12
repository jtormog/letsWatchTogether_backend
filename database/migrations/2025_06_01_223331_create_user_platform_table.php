<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('user_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'platform_id']);
            $table->index('user_id');
            $table->index('platform_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platform');
    }
};
