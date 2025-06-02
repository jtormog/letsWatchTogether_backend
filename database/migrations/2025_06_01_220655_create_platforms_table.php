<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->unique();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('platforms')->insert([
            ['name' => 'Netflix', 'code' => 1796],
            ['name' => 'HBO', 'code' => 384],
            ['name' => 'Disney+', 'code' => 337],
            ['name' => 'Amazon Prime Video', 'code' => 2100],
            ['name' => 'Apple Tv+', 'code' => 350 ],
            ['name' => 'Crunchyroll', 'code' => 283],
        ]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
