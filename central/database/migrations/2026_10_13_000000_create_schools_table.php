<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('site_url');
            $table->string('theme')->default('blue');
            $table->string('address');
            $table->string('postal_code');
            $table->string('city');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('office_hours')->nullable();
            $table->string('map_embed_url', 2048)->nullable();
            $table->text('contact_note')->nullable();
            $table->json('social_links')->nullable();
            $table->string('old_site_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
