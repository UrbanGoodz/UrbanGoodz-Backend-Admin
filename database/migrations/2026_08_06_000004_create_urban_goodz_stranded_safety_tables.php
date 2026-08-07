<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity verification, consent, and disclosure logging for Stranded.
 *
 * Both sides of a rescue must be verified before they can take part. A
 * stranded customer is inviting a stranger to their location, and a Goodz
 * Samaritan is driving to meet one. Neither is safe if only one is known.
 *
 * Three tables:
 *
 *   verifications   a driver's licence on file, per user, per role
 *   consents        exactly what someone agreed to, and when
 *   disclosure_log  every time identity data was released to anyone
 *
 * The disclosure log is what makes the privacy promise real rather than
 * marketing. If the product tells people their information is released only
 * on a lawful request, there has to be a record proving when that happened
 * and under what authority.
 *
 * Licence images are deliberately NOT given a public path column. They belong
 * on the private `local` disk and must be served through an authorising
 * controller, never linked directly. A public URL to a government ID is a
 * permanent, un-revocable leak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_stranded_verifications')) {
            Schema::create('urban_goodz_stranded_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                // customer | samaritan -- somebody may be both, and each role
                // is verified on its own terms.
                $table->string('role', 20)->default('customer');

                // Path on the PRIVATE disk. Never a public URL.
                $table->string('license_front_path', 500)->nullable();
                $table->string('license_back_path', 500)->nullable();
                $table->string('selfie_path', 500)->nullable();

                // Encrypted at rest by the model cast. Only the last four are
                // stored in the clear, which is all any screen needs to show.
                $table->text('license_number_encrypted')->nullable();
                $table->string('license_last_four', 4)->nullable();
                $table->string('license_state', 10)->nullable();
                $table->date('license_expires_on')->nullable();

                $table->string('full_name', 160)->nullable();
                $table->date('date_of_birth')->nullable();

                // pending | approved | rejected | expired
                $table->string('status', 20)->default('pending');
                $table->string('rejection_reason', 500)->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->boolean('phone_verified')->default(false);
                $table->boolean('background_check_passed')->nullable();
                $table->timestamp('background_checked_at')->nullable();

                $table->timestamps();

                $table->unique(['user_id', 'role'], 'ug_st_verif_user_role_unique');
                $table->index(['status', 'role'], 'ug_st_verif_status_role_idx');
                $table->index('license_expires_on', 'ug_st_verif_expiry_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_stranded_consents')) {
            Schema::create('urban_goodz_stranded_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('role', 20)->default('customer');

                // Which document, and which revision of it. Consent to v1 is
                // not consent to v2, so the version must be recorded rather
                // than assumed.
                $table->string('document', 60);
                $table->string('version', 20);

                $table->timestamp('accepted_at');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();

                $table->timestamps();

                $table->index(['user_id', 'document'], 'ug_st_consent_user_doc_idx');
                $table->unique(['user_id', 'role', 'document', 'version'], 'ug_st_consent_unique_version');
            });
        }

        if (!Schema::hasTable('urban_goodz_stranded_disclosure_log')) {
            Schema::create('urban_goodz_stranded_disclosure_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subject_user_id');
                $table->unsignedBigInteger('request_id')->nullable();

                // law_enforcement | subpoena | court_order | safety_incident |
                // user_request | internal_review
                $table->string('basis', 40);
                $table->string('requesting_authority', 200)->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->text('fields_disclosed')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedBigInteger('authorised_by')->nullable();
                $table->timestamp('disclosed_at');

                // Whether the subject was told. Some lawful requests forbid
                // notification; that is a fact worth recording either way.
                $table->boolean('subject_notified')->default(false);
                $table->string('notification_withheld_reason', 300)->nullable();

                $table->timestamps();

                $table->index(['subject_user_id', 'disclosed_at'], 'ug_st_disclosure_subject_idx');
                $table->index('basis', 'ug_st_disclosure_basis_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_stranded_disclosure_log');
        Schema::dropIfExists('urban_goodz_stranded_consents');
        Schema::dropIfExists('urban_goodz_stranded_verifications');
    }
};
