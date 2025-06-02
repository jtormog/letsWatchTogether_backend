<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction wrapping for this migration.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('watch_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('friendship_id')->constrained('friendships')->onDelete('cascade');
            $table->integer('tmdb_id');
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['friendship_id', 'status']);
            $table->index(['tmdb_id']);
            
            // Evitar invitaciones duplicadas para el mismo contenido y amistad
            $table->unique(['friendship_id', 'tmdb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watch_invitations');
    }
};
