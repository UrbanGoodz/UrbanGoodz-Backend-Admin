<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_verifications')) {
            Schema::create('email_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('email', 100)->unique();
                $table->string('code', 10);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};