<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzSourcingRecord;
use Carbon\Carbon;

class HoustonEventSeeder extends Seeder
{
    public function run(): void
    {
        $venues = [
            ['name' => 'Discovery Green', 'address' => '1500 McKinney St, Houston, TX 77010', 'lat' => 29.7525, 'lng' => -95.3597],
            ['name' => 'POST Houston', 'address' => '401 Franklin St, Houston, TX 77201', 'lat' => 29.7644, 'lng' => -95.3621],
            ['name' => '713 Music Hall', 'address' => '401 Franklin St Suite 1600, Houston, TX 77201', 'lat' => 29.7644, 'lng' => -95.3621],
            ['name' => 'NRG Center', 'address' => '1 NRG Park, Houston, TX 77054', 'lat' => 29.6848, 'lng' => -95.4057],
            ['name' => 'George R. Brown', 'address' => '1001 Avenida De Las Americas, Houston, TX 77010', 'lat' => 29.7518, 'lng' => -95.3585],
            ['name' => 'Warehouse Live', 'address' => '813 St Emanuel St, Houston, TX 77003', 'lat' => 29.7523, 'lng' => -95.3540],
            ['name' => 'House of Blues', 'address' => '1204 Caroline St, Houston, TX 77002', 'lat' => 29.7538, 'lng' => -95.3637],
            ['name' => 'Market Square Park', 'address' => '301 Milam St, Houston, TX 77002', 'lat' => 29.7621, 'lng' => -95.3618],
            ['name' => 'Memorial Park', 'address' => '6501 Memorial Dr, Houston, TX 77007', 'lat' => 29.7641, 'lng' => -95.4402],
            ['name' => 'Buffalo Bayou Park', 'address' => '1800 Allen Pkwy, Houston, TX 77019', 'lat' => 29.7610, 'lng' => -95.3855]
        ];

        $categories = ['food', 'fashion', 'beauty', 'music', 'fitness', 'comedy', 'lifestyle', 'business', 'community', 'nightlife', 'art', 'health'];
        
        $titles = [
            'Houston Food Truck Rally', 'EaDo Art Walk', 'Sneaker Pop-Up Market', 
            'R&B Under the Stars', 'Midtown Mixer', 'Third Ward Community Fair',
            'Barber & Beauty Showcase', 'HTX Comedy Night', 'Memorial 5K Run', 
            'Neon Nightlife Party', 'Taste of H-Town', 'Local Makers Market',
            'Hip-Hop Showcase', 'Entrepreneur Networking Hour', 'Wellness & Yoga in the Park',
            'Streetwear Convention', 'Latin Dance Festival', 'Downtown Tech Meetup',
            'Summer Vibes Concert', 'Vintage Clothing Swap', 'Houston Coffee Festival',
            'Gospel Brunch & Concert', 'Startup Pitch Day', 'Fitness Bootcamp',
            'Artisan Craft Fair', 'Afrobeats Night', 'Neighborhood Cleanup & Grill',
            'Women in Business Mixer', 'Skate & Create Jam', 'Independent Film Screening'
        ];

        $events = [];

        // Generate 30 realistic events for August-September 2026
        for ($i = 0; $i < 30; $i++) {
            $venue = $venues[array_rand($venues)];
            $isFree = rand(0, 1) == 1;
            $price = $isFree ? null : rand(15, 75);
            $category = $categories[array_rand($categories)];
            
            // Random date in Aug-Sept 2026
            $month = rand(8, 9);
            $day = rand(1, 28);
            $hour = rand(10, 20);
            
            $start = Carbon::create(2026, $month, $day, $hour, 0, 0);
            $end = (clone $start)->addHours(rand(2, 6));

            $restrictions = [null, '18+', '21+'];

            $events[] = [
                'title' => $titles[$i] . ' 2026',
                'description' => 'Come out to ' . $venue['name'] . ' for the best ' . $category . ' event in the city! Join the community for a great time.',
                'category' => $category,
                'venue_name' => $venue['name'],
                'venue_address' => $venue['address'],
                'latitude' => $venue['lat'],
                'longitude' => $venue['lng'],
                'city' => 'Houston',
                'starts_at' => $start,
                'ends_at' => $end,
                'is_free' => $isFree,
                'ticket_price' => $price,
                'ticket_url' => null,
                'age_restriction' => $restrictions[array_rand($restrictions)],
                'organiser_type' => 'community',
                'source' => 'seeder',
                'validation_state' => 'valid',
                'duplicate_state' => 'none',
                'approval_state' => 'pending',
                'visibility_state' => 'hidden',
                'status' => 'active'
            ];
        }

        try {
            foreach ($events as $data) {
                $existing = UrbanGoodzEvent::where('title', $data['title'])
                    ->where('starts_at', $data['starts_at'])
                    ->first();
                
                if (!$existing) {
                    $event = UrbanGoodzEvent::create($data);
                    
                    UrbanGoodzSourcingRecord::create([
                        'sourceable_type' => UrbanGoodzEvent::class,
                        'sourceable_id' => $event->id,
                        'source' => 'seeder',
                        'metadata' => json_encode(['seeded_at' => now()->toDateTimeString()])
                    ]);
                }
            }
            if (isset($this->command)) {
                $this->command->info('Successfully seeded 30 Houston events.');
            }
        } catch (\Exception $e) {
            if (isset($this->command)) {
                $this->command->error('Error seeding events: ' . $e->getMessage());
            } else {
                echo 'Error seeding events: ' . $e->getMessage() . "\n";
            }
        }
    }
}
