<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-request messaging between a stranded customer and their responder.
 *
 * Deliberately its own table rather than the shared `conversations` /
 * `messages` pair. A Stranded thread is scoped to one request, opens when a
 * responder is selected, and closes when the job ends. Bending the shared
 * tables to carry that lifecycle would put every other chat in the platform
 * at risk for no benefit.
 *
 * The common case this exists for is "I cannot find you": a dark car park, an
 * unlit shoulder, a wrong pin. So a message can carry a precise coordinate or
 * a photo of the surroundings, not just text.
 *
 * No phone numbers are stored here. Numbers are never exchanged between
 * parties -- see the note on masked calling in the controller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_stranded_messages')) {
            Schema::create('urban_goodz_stranded_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id');

                // customer | responder | system
                $table->string('sender_role', 20);
                $table->unsignedBigInteger('sender_id')->nullable();

                // text | location | photo | system
                $table->string('type', 20)->default('text');
                $table->text('body')->nullable();

                // Attached to a `location` message: a precise fix, sent when
                // the original pin is not good enough to find someone.
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy_meters', 8, 2)->nullable();

                // Private disk path, as with identity documents. Never a
                // public URL.
                $table->string('photo_path', 500)->nullable();

                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['request_id', 'id'], 'ug_st_msg_request_idx');
                $table->index(['request_id', 'read_at'], 'ug_st_msg_unread_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_messages');
    }
};
