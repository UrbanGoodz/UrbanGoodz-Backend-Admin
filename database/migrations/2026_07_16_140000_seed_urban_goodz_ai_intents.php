<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $intents = [
            [
                'slug' => 'order_anywhere',
                'name' => 'Order Anywhere',
                'description' => 'Customer wants to order items from any store for delivery',
                'keywords' => ['order', 'shop', 'store', 'buy', 'purchase', 'get me', 'pick up', 'anywhere', 'deliver', 'food', 'grocery', 'groceries', 'restaurant', 'coffee'],
                'response_template' => "I'd love to help you place an order! You can use our Order Anywhere feature to request items from any store. Open the Order Anywhere section to submit your request.",
                'capability_slug' => 'order_anywhere',
                'admin_section_key' => 'order_anywhere',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'fashion_fit',
                'name' => 'Fashion Fit / Measurements',
                'description' => 'Customer wants tailoring, styling, or measurement services',
                'keywords' => ['tailor', 'stylist', 'measure', 'measurement', 'clothes', 'fit', 'alteration', 'sewing', 'fashion', 'outfit', 'dress', 'suit', 'hem', 'resize'],
                'response_template' => "Great choice! Our Fashion Fit service connects you with professional tailors and stylists. Open Fashion Fit to create your measurement profile and connect with local tailors.",
                'capability_slug' => 'fashion_fit',
                'admin_section_key' => 'fashion_fit',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'logistics_freight',
                'name' => 'Logistics & Freight',
                'description' => 'Customer or driver asking about logistics jobs, freight, or load board',
                'keywords' => ['logistics', 'freight', 'cargo', 'truck', 'shipping', 'load', 'haul', 'transport', 'driver', 'earn', 'money', 'job', 'opportunity', 'gig', 'deliver'],
                'response_template' => "Our Logistics & Load Board connects carriers with freight opportunities. Check the Load Board for available loads in your area, or visit Earn Money to see all earning opportunities.",
                'capability_slug' => 'logistics',
                'admin_section_key' => 'logistics',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'slug' => 'medical_courier',
                'name' => 'Medical Courier',
                'description' => 'Customer or driver asking about medical delivery services',
                'keywords' => ['medical', 'courier', 'prescription', 'pharmacy', 'lab', 'specimen', 'hospital', 'clinic', 'healthcare', 'HIPAA', 'biological', 'temperature', 'STAT'],
                'response_template' => "Our Medical Courier service handles sensitive medical deliveries with full compliance. Check the Medical Courier section for available runs in your area.",
                'capability_slug' => 'medical_courier',
                'admin_section_key' => 'medical_courier',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'slug' => 'creator_commerce',
                'name' => 'Creator Commerce',
                'description' => 'Customer asking about influencer/creator partnerships or shoppable content',
                'keywords' => ['creator', 'influencer', 'reel', 'content', 'video', 'promote', 'campaign', 'brand', 'sponsor', 'collab', 'partnership', 'shoppable'],
                'response_template' => "Creator Commerce connects brands with local influencers for shoppable content campaigns. Open Creator Commerce to browse featured creators or apply as a creator.",
                'capability_slug' => 'creator_commerce',
                'admin_section_key' => 'creator_commerce',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'slug' => 'book_anything',
                'name' => 'Book Anything',
                'description' => 'Customer wants to book a service (cleaning, repair, moving, etc.)',
                'keywords' => ['book', 'schedule', 'appointment', 'service', 'clean', 'repair', 'moving', 'plumber', 'electrician', 'handyman', 'landscape', 'paint'],
                'response_template' => "Book Anything lets you schedule any service you need. Open the Book Anything section to browse available services or submit a custom request.",
                'capability_slug' => 'book_anything',
                'admin_section_key' => 'book_anything',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'slug' => 'events',
                'name' => 'Events & Community',
                'description' => 'Customer asking about local events, community groups, or marketplace',
                'keywords' => ['event', 'community', 'marketplace', 'group', 'meetup', 'party', 'concert', 'festival', 'buy sell', 'secondhand', 'neighborhood'],
                'response_template' => "Check out Events & Creators for local events happening near you, or browse the Community Marketplace to buy and sell within your neighborhood.",
                'capability_slug' => 'events',
                'admin_section_key' => 'events',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'slug' => 'account_support',
                'name' => 'Account & Support',
                'description' => 'Customer needs help with their account, payments, or general support',
                'keywords' => ['account', 'password', 'login', 'payment', 'refund', 'charge', 'bill', 'help', 'support', 'issue', 'problem', 'cancel', 'update', 'phone', 'email'],
                'response_template' => "I'm here to help! For account issues, payment questions, or general support, I can assist you right away. What specific issue are you experiencing?",
                'capability_slug' => 'support',
                'admin_section_key' => 'support',
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($intents as $intent) {
            DB::table('urban_goodz_ai_intents')->updateOrInsert(
                ['slug' => $intent['slug']],
                array_merge($intent, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('urban_goodz_ai_intents')->whereIn('slug', [
            'order_anywhere', 'fashion_fit', 'logistics_freight', 'medical_courier',
            'creator_commerce', 'book_anything', 'events', 'account_support',
        ])->delete();
    }
};
