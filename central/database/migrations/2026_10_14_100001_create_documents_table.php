<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // No action on delete: a category with documents cannot be deleted, while
            // deleting a school still cascades through its categories and documents.
            $table->foreignId('document_category_id')->constrained();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('document_date')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'document_category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
