<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Import Batches
        Schema::create('urban_goodz_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('category')->nullable();
            $table->string('module')->nullable();
            $table->text('source_query')->nullable();
            $table->text('source_platforms')->nullable(); // JSON string of platforms searched
            $table->integer('total_found')->default(0);
            $table->integer('total_imported')->default(0);
            $table->integer('total_needs_review')->default(0);
            $table->string('status')->default('pending'); // pending, running, completed, failed, partially_completed
            $table->foreignId('admin_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 2. Sourced Businesses
        Schema::create('urban_goodz_sourced_businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('legal_name')->nullable();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('business_type')->nullable(); // food_truck, retail, beauty, events, etc.
            $table->foreignId('module_id')->nullable();
            $table->string('module_name')->nullable();
            $table->text('category_ids')->nullable(); // JSON array
            $table->text('tags')->nullable(); // JSON array
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('social_links')->nullable(); // JSON object
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->foreignId('zone_id')->nullable();
            $table->string('zone_name')->nullable();
            $table->boolean('is_launch_market')->default(false);
            $table->boolean('is_nationwide')->default(false);
            $table->boolean('is_worldwide')->default(false);
            $table->boolean('is_black_owned')->nullable();
            $table->boolean('is_woman_owned')->nullable();
            $table->boolean('is_local_business')->default(true);
            $table->text('fulfillment_modes')->nullable(); // JSON array (delivery, pickup, quote_required, etc.)
            $table->string('onboarding_status')->default('public_sourced'); // public_sourced, pending_review, invited, claimed, verified, active, rejected, archived
            $table->string('source_status')->default('ai_sourced'); // ai_sourced, customer_requested, vendor_submitted, admin_created
            $table->text('source_urls')->nullable(); // JSON array of source pages
            $table->integer('data_confidence_score')->default(0);
            $table->integer('demand_score')->default(0);
            $table->timestamp('last_verified_at')->nullable();
            $table->string('admin_review_status')->default('pending'); // pending, approved, rejected, merge_required
            $table->string('created_by_source')->nullable(); // source platform, eg. google_maps, instagram, web
            $table->timestamps();
        });

        // 3. Sourced Products
        Schema::create('urban_goodz_sourced_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sourced_business_id')->nullable(); // references urban_goodz_sourced_businesses
            $table->unsignedBigInteger('store_id')->nullable(); // references active stores table if claimed
            $table->foreignId('module_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->foreignId('subcategory_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->decimal('price', 24, 2)->nullable();
            $table->string('price_type')->default('fixed'); // fixed, starting_at, quote_required, market_price, unknown
            $table->string('currency', 10)->default('USD');
            $table->string('stock_status')->default('unknown'); // unknown, in_stock, out_of_stock, limited, seasonal
            $table->string('item_type')->default('product'); // product, food, service, rental, event, fashion, beauty, pharmacy, courier, custom_request
            $table->text('images')->nullable(); // JSON array
            $table->string('thumbnail')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_type')->nullable(); // website, social, catalog
            $table->integer('source_confidence')->default(0);
            $table->string('fulfillment_type')->nullable();
            $table->boolean('requires_quote')->default(false);
            $table->boolean('requires_admin_review')->default(true);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->foreignId('import_batch_id')->nullable(); // references urban_goodz_import_batches
            $table->timestamps();
        });

        // 4. Sourced Images
        Schema::create('urban_goodz_sourced_images', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // business, product, category, etc.
            $table->unsignedBigInteger('entity_id');
            $table->text('image_url');
            $table->text('local_path')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_platform')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('rights_status')->default('unknown_review_required'); // vendor_owned, public_official, customer_uploaded, unknown_review_required, generated_placeholder
            $table->string('review_status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
        });

        // 5. Demand Signals
        Schema::create('urban_goodz_demand_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable();
            $table->text('query_text')->nullable();
            $table->text('requested_item')->nullable();
            $table->text('requested_vendor')->nullable();
            $table->string('source')->default('search'); // ask_urban_goodz, order_anywhere, search, admin_import
            $table->unsignedBigInteger('matched_entity_id')->nullable(); // matches urban_goodz_sourced_businesses or stores
            $table->unsignedBigInteger('matched_product_id')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->foreignId('zone_id')->nullable();
            $table->integer('demand_count')->default(1);
            $table->integer('opportunity_score')->default(0);
            $table->unsignedBigInteger('converted_to_business_id')->nullable(); // once onboarded/promoted
            $table->unsignedBigInteger('converted_to_product_id')->nullable();
            $table->unsignedBigInteger('converted_to_order_anywhere_request_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('urban_goodz_demand_signals');
        Schema::dropIfExists('urban_goodz_sourced_images');
        Schema::dropIfExists('urban_goodz_sourced_products');
        Schema::dropIfExists('urban_goodz_sourced_businesses');
        Schema::dropIfExists('urban_goodz_import_batches');
    }
};
