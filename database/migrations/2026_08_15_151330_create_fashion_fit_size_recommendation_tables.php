<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('urban_goodz_garment_attributes')) {
            Schema::create('urban_goodz_garment_attributes', function (Blueprint $table) {
                $table->id();
                $table->string('product_type'); // 'sourced_product' | 'item'
                $table->unsignedBigInteger('product_id');
                $table->string('garment_category')->nullable(); // top, bottom, dress, outerwear, footwear, accessory, other
                $table->string('fit_type')->nullable(); // tailored, slim, regular, relaxed, oversized
                $table->string('stretch')->nullable(); // none, low, moderate, high
                $table->string('gender_category')->nullable(); // mens, womens, unisex, kids
                $table->string('brand')->nullable();
                $table->text('source_url')->nullable();
                $table->integer('source_confidence')->default(0);
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();

                $table->unique(['product_type', 'product_id'], 'ug_garment_attrs_product_unique');
            });
        }

        if (!Schema::hasTable('urban_goodz_brand_size_charts')) {
            Schema::create('urban_goodz_brand_size_charts', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('garment_category');
                $table->string('region')->default('US');
                $table->string('size_label'); // 'M', '10', '32R', etc
                $table->decimal('chest_bust_min', 6, 2)->nullable();
                $table->decimal('chest_bust_max', 6, 2)->nullable();
                $table->decimal('waist_min', 6, 2)->nullable();
                $table->decimal('waist_max', 6, 2)->nullable();
                $table->decimal('hip_min', 6, 2)->nullable();
                $table->decimal('hip_max', 6, 2)->nullable();
                $table->decimal('inseam_min', 6, 2)->nullable();
                $table->decimal('inseam_max', 6, 2)->nullable();
                $table->decimal('sleeve_length_min', 6, 2)->nullable();
                $table->decimal('sleeve_length_max', 6, 2)->nullable();
                $table->string('unit')->default('in');
                $table->text('source_url')->nullable();
                $table->integer('source_confidence')->default(0);
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();

                $table->index(['brand', 'garment_category', 'region'], 'ug_brand_size_charts_lookup_idx');
            });
        }

        if (!Schema::hasTable('urban_goodz_fit_recommendations')) {
            Schema::create('urban_goodz_fit_recommendations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fashion_fit_profile_id');
                $table->string('product_type');
                $table->unsignedBigInteger('product_id');
                $table->string('recommended_size')->nullable();
                $table->integer('confidence')->nullable();
                $table->string('data_sufficiency'); // 'sufficient' | 'insufficient'
                $table->text('explanation')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();

                $table->index(['fashion_fit_profile_id', 'product_type', 'product_id'], 'ug_fit_recs_lookup_idx');
                $table->foreign('fashion_fit_profile_id')->references('id')->on('fashion_fit_profiles')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_fit_recommendations');
        Schema::dropIfExists('urban_goodz_brand_size_charts');
        Schema::dropIfExists('urban_goodz_garment_attributes');
    }
};
