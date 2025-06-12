<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('watch_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('friendship_id')->constrained('friendships')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->integer('tmdb_id');
            $table->enum('type', ['movie', 'tv']);
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            $table->index(['friendship_id', 'status']);
            $table->index(['tmdb_id']);
            $table->index(['sender_id']);
            $table->index(['type']);
            
            $table->unique(['friendship_id', 'tmdb_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_invitations');
    }
};
