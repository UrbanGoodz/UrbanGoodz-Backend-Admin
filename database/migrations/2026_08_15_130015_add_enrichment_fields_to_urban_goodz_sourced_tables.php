<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('urban_goodz_sourced_businesses')) {
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'hours')) $table->text('hours')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'hours_source_url')) $table->string('hours_source_url')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'hours_verified_at')) $table->timestamp('hours_verified_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'completeness_score')) $table->unsignedTinyInteger('completeness_score')->default(0);
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'completeness_breakdown')) $table->text('completeness_breakdown')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'enrichment_status')) $table->string('enrichment_status')->default('never_enriched');
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'next_enrichment_at')) $table->timestamp('next_enrichment_at')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'field_provenance')) $table->text('field_provenance')->nullable();
            });
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_sourced_businesses', 'completeness_score')) return;
                $indexes = collect(Schema::getIndexes('urban_goodz_sourced_businesses'))->pluck('name');
                if (!$indexes->contains('ug_sourced_businesses_completeness_idx')) {
                    $table->index('completeness_score', 'ug_sourced_businesses_completeness_idx');
                }
                if (!$indexes->contains('ug_sourced_businesses_next_enrichment_idx')) {
                    $table->index('next_enrichment_at', 'ug_sourced_businesses_next_enrichment_idx');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_sourced_products')) {
            Schema::table('urban_goodz_sourced_products', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_sourced_products', 'sku')) $table->string('sku')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_products', 'external_product_id')) $table->string('external_product_id')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_products', 'canonical_url')) $table->text('canonical_url')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_products', 'brand')) $table->string('brand')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_products', 'last_verified_at')) $table->timestamp('last_verified_at')->nullable();
            });
            Schema::table('urban_goodz_sourced_products', function (Blueprint $table) {
                $indexes = collect(Schema::getIndexes('urban_goodz_sourced_products'))->pluck('name');
                if (!$indexes->contains('ug_sourced_products_sku_idx')) {
                    $table->index('sku', 'ug_sourced_products_sku_idx');
                }
                if (!$indexes->contains('ug_sourced_products_external_id_idx')) {
                    $table->index('external_product_id', 'ug_sourced_products_external_id_idx');
                }
                if (!$indexes->contains('ug_sourced_products_business_external_idx')) {
                    $table->index(['sourced_business_id', 'external_product_id'], 'ug_sourced_products_business_external_idx');
                }
            });
        }

        if (Schema::hasTable('urban_goodz_sourced_images')) {
            Schema::table('urban_goodz_sourced_images', function (Blueprint $table) {
                if (!Schema::hasColumn('urban_goodz_sourced_images', 'width')) $table->unsignedInteger('width')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_images', 'height')) $table->unsignedInteger('height')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_images', 'format')) $table->string('format', 10)->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_images', 'file_size_bytes')) $table->unsignedInteger('file_size_bytes')->nullable();
                if (!Schema::hasColumn('urban_goodz_sourced_images', 'content_hash')) $table->string('content_hash', 64)->nullable();
            });
            Schema::table('urban_goodz_sourced_images', function (Blueprint $table) {
                $indexes = collect(Schema::getIndexes('urban_goodz_sourced_images'))->pluck('name');
                if (!$indexes->contains('ug_sourced_images_content_hash_idx')) {
                    $table->index('content_hash', 'ug_sourced_images_content_hash_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('urban_goodz_sourced_businesses')) {
            Schema::table('urban_goodz_sourced_businesses', function (Blueprint $table) {
                $table->dropColumn([
                    'hours', 'hours_source_url', 'hours_verified_at',
                    'completeness_score', 'completeness_breakdown',
                    'enrichment_status', 'next_enrichment_at', 'field_provenance',
                ]);
            });
        }

        if (Schema::hasTable('urban_goodz_sourced_products')) {
            Schema::table('urban_goodz_sourced_products', function (Blueprint $table) {
                $table->dropColumn(['sku', 'external_product_id', 'canonical_url', 'brand', 'last_verified_at']);
            });
        }

        if (Schema::hasTable('urban_goodz_sourced_images')) {
            Schema::table('urban_goodz_sourced_images', function (Blueprint $table) {
                $table->dropColumn(['width', 'height', 'format', 'file_size_bytes', 'content_hash']);
            });
        }
    }
};
