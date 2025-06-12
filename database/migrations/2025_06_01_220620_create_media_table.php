<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('user_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('tmdb_id');
            $table->boolean('recommended')->default(false);
            $table->boolean('liked')->default(false);
            $table->enum('type', ['movie', 'tv']);
            $table->enum('status', ['watching', 'completed', 'planned']);
            $table->string('episode')->nullable();
            $table->integer('watching_with')->nullable();
            $table->boolean('invitation_accepted')->default(false);
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_media');
    }
};
