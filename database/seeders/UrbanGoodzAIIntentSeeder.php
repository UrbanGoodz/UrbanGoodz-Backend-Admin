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
            [
                'slug' => 'load-board',
                'name' => 'Load Board Inquiry',
                'keywords' => ['load board', 'available loads', 'freight', 'truckload', 'find a load', 'load listing', 'rate per mile', 'lane'],
                'response_template' => 'The Urban Goodz Load Board has available loads across multiple lanes. Check the Load Board section for current listings, rates, and equipment requirements. For bulk or dedicated lane pricing, contact our logistics team.',
                'capability_slug' => 'logistics',
                'admin_section_key' => 'logistics',
                'sort_order' => 11,
            ],
            [
                'slug' => 'operations',
                'name' => 'Internal Operations',
                'keywords' => ['failed job', 'retry job', 'queue', 'requeue', 'stuck job', 'clear the queue', 'operational issues'],
                'response_template' => 'Operational queue actions are available to administrators. Failed jobs can be inspected and retried from the operations view.',
                'capability_slug' => 'operations',
                'admin_section_key' => 'operations',
                'sort_order' => 90,
            ],
            [
                'slug' => 'package-route',
                'name' => 'Package Route Tracking',
                'keywords' => ['package route', 'route tracking', 'dedicated route', 'route status', 'delivery route', 'stop tracking'],
                'response_template' => 'You can track your dedicated route status in the Routes section. Each route shows real-time package scanning, stop completion, and driver location. For route optimization or scheduling changes, contact your account manager.',
                'capability_slug' => 'logistics',
                'admin_section_key' => 'logistics',
                'sort_order' => 12,
            ],
            [
                'slug' => 'medical-courier',
                'name' => 'Medical Courier Service',
                'keywords' => ['medical courier', 'healthcare delivery', 'pharmaceutical', 'lab specimens', 'medical supplies', 'hipaa', 'temperature controlled medical'],
                'response_template' => 'Urban Goodz Medical Courier provides HIPAA-compliant, temperature-controlled deliveries for healthcare facilities. All couriers are background-checked and trained in medical transport protocols. Contact our medical logistics team for urgent deliveries.',
                'capability_slug' => 'logistics',
                'admin_section_key' => 'medical-courier',
                'sort_order' => 13,
            ],
            [
                'slug' => 'business-courier',
                'name' => 'Business Courier & Logistics',
                'keywords' => ['business courier', 'corporate logistics', 'business delivery', 'enterprise shipping', 'bulk delivery', 'scheduled delivery'],
                'response_template' => 'Urban Goodz Business Courier offers scheduled and on-demand deliveries for enterprises. Volume discounts, dedicated drivers, and real-time tracking available. Contact sales@urbangoodz.com for a custom logistics plan.',
                'capability_slug' => 'logistics',
                'admin_section_key' => 'logistics',
                'sort_order' => 14,
            ],
            [
                'slug' => 'driver-dispatch',
                'name' => 'Driver Dispatch Status',
                'keywords' => ['driver dispatch', 'assign driver', 'driver available', 'dispatch status', 'driver assigned', 'fleet status'],
                'response_template' => 'Driver dispatch status is managed in real-time. Available drivers are matched to loads based on equipment, location, and capacity. For urgent dispatch needs, contact dispatch@urbangoodz.com.',
                'capability_slug' => 'logistics',
                'admin_section_key' => 'logistics',
                'sort_order' => 15,
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
