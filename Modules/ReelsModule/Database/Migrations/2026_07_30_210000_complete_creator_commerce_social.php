<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reels', 'total_comments')) {
            Schema::table('reels', function (Blueprint $table) {
                $table->unsignedBigInteger('total_comments')->default(0)->after('total_likes');
            });
        }

        if (! Schema::hasTable('creator_reel_comments')) {
            Schema::create('creator_reel_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()
                    ->constrained('creator_reel_comments')->cascadeOnDelete();
                $table->text('body');
                $table->string('status', 24)->default('published')->index();
                $table->foreignId('moderated_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('moderated_at')->nullable();
                $table->timestamps();

                $table->index(['reel_id', 'parent_id', 'status'], 'creator_reel_comment_thread_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_reel_comments');

        if (Schema::hasColumn('reels', 'total_comments')) {
            Schema::table('reels', function (Blueprint $table) {
                $table->dropColumn('total_comments');
            });
        }
    }
};
