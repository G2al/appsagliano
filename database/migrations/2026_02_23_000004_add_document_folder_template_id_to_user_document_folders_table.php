<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_document_folders', function (Blueprint $table) {
            $table->foreignId('document_folder_template_id')
                ->nullable()
                ->after('user_id')
                ->constrained('document_folder_templates')
                ->nullOnDelete();

            $table->unique(['user_id', 'document_folder_template_id'], 'user_folder_template_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_document_folders', function (Blueprint $table) {
            $table->dropUnique('user_folder_template_unique');
            $table->dropConstrainedForeignId('document_folder_template_id');
        });
    }
};
