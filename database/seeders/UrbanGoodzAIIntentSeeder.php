<?php

namespace Database\Seeders;

use App\Models\UrbanGoodzAIIntent;
use Illuminate\Database\Seeder;

class UrbanGoodzAIIntentSeeder extends Seeder
{
    public function run(): void
    {
        $intents = [
            [
                'slug' => 'order-status',
                'name' => 'Order Status Inquiry',
                'keywords' => ['order status', 'where is my order', 'track order', 'order tracking', 'my order', 'order update'],
                'response_template' => 'You can check your order status by visiting the Orders section in your Urban Goodz account. If you need further help, a support agent can assist.',
                'capability_slug' => 'order-anywhere',
                'admin_section_key' => 'order-anywhere',
                'sort_order' => 1,
            ],
            [
                'slug' => 'fashion-fit',
                'name' => 'Fashion Fit Inquiry',
                'keywords' => ['fashion fit', 'measurement', 'tailor', 'alteration', 'custom clothing', 'sizing', 'fashion'],
                'response_template' => 'Urban Goodz Fashion Fit connects you with local tailors and stylists. You can submit a measurement request from the Fashion Fit section in your account.',
                'capability_slug' => 'fashion-fit',
                'admin_section_key' => 'fashion-fit',
                'sort_order' => 2,
            ],
            [
                'slug' => 'order-anywhere',
                'name' => 'Order Anywhere Help',
                'keywords' => ['order anywhere', 'request item', 'can i get', 'i want to buy', 'special order', 'custom order', 'request a product'],
                'response_template' => 'Order Anywhere lets you request items from local businesses! Just tell us what you are looking for and we will find it for you. Submit a request in the Order Anywhere section.',
                'capability_slug' => 'order-anywhere',
                'admin_section_key' => 'order-anywhere',
                'sort_order' => 3,
            ],
            [
                'slug' => 'become-partner',
                'name' => 'Become a Partner',
                'keywords' => ['become a partner', 'list my business', 'join urban goodz', 'vendor application', 'sell on urban goodz', 'partner application'],
                'response_template' => 'We are excited you want to partner with Urban Goodz! Please contact our team through the Partner section or email partnerships@urbangoodz.com.',
                'capability_slug' => null,
                'admin_section_key' => null,
                'sort_order' => 4,
            ],
            [
                'slug' => 'earn-money',
                'name' => 'Earn Money Inquiry',
                'keywords' => ['earn money', 'make money', 'referral', 'affiliate', 'gig', 'opportunity', 'earn'],
                'response_template' => 'Urban Goodz offers several ways to earn money! Check out the Earn Money section for referral programs, affiliate opportunities, and local gigs.',
                'capability_slug' => 'earn-money',
                'admin_section_key' => 'earn-money',
                'sort_order' => 5,
            ],
            [
                'slug' => 'community',
                'name' => 'Community & Marketplace',
                'keywords' => ['community', 'marketplace', 'sell my items', 'local posts', 'buy local', 'neighborhood'],
                'response_template' => 'The Urban Goodz Community Marketplace lets you buy and sell within your local area. Check out the Community section to see posts near you!',
                'capability_slug' => 'community-marketplace',
                'admin_section_key' => 'community',
                'sort_order' => 6,
            ],
            [
                'slug' => 'creator-commerce',
                'name' => 'Creator Commerce',
                'keywords' => ['creator', 'influencer', 'merch', 'merchandise', 'promote my brand', 'creator application'],
                'response_template' => 'Urban Goodz Creator Commerce helps influencers and creators sell merchandise to their audience. Apply in the Creator Commerce section to get started!',
                'capability_slug' => 'creator-commerce',
                'admin_section_key' => 'creators',
                'sort_order' => 7,
            ],
            [
                'slug' => 'book-services',
                'name' => 'Book Services',
                'keywords' => ['book service', 'appointment', 'service request', 'hire', 'professional', 'book appointment'],
                'response_template' => 'You can book professional services through Urban Goodz! Go to the Book Anything section to find and request services near you.',
                'capability_slug' => 'book-anything',
                'admin_section_key' => 'book-anything',
                'sort_order' => 8,
            ],
            [
                'slug' => 'delivery-help',
                'name' => 'Delivery Support',
                'keywords' => ['delivery', 'driver', 'courier', 'shipping', 'pickup', 'drop off', 'delivery time'],
                'response_template' => 'Need help with a delivery? You can track your delivery status in your account or contact the driver directly from the order details page.',
                'capability_slug' => null,
                'admin_section_key' => null,
                'sort_order' => 9,
            ],
            [
                'slug' => 'payment-help',
                'name' => 'Payment Support',
                'keywords' => ['payment', 'refund', 'charge', 'billing', 'invoice', 'receipt', 'paid', 'transaction'],
                'response_template' => 'For payment inquiries, please check your Payment History in your account. If you need a refund or have a billing question, contact our support team.',
                'capability_slug' => null,
                'admin_section_key' => null,
                'sort_order' => 10,
            ],
        ];

        foreach ($intents as $data) {
            UrbanGoodzAIIntent::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
