<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UrbanGoodzCreatorProfile;
use App\Models\UrbanGoodzSourcingRecord;
use Carbon\Carbon;

class HoustonCreatorSeeder extends Seeder
{
    public function run(): void
    {
        $zones = ['Third Ward', 'Montrose', 'Heights', 'Midtown', 'EaDo', 'River Oaks', 'Sugar Land', 'Katy', 'Pearland', 'Downtown', 'Medical Center', 'Galleria', 'Memorial', 'Spring Branch'];
        $categoriesPool = ['food', 'fashion', 'beauty', 'music', 'fitness', 'comedy', 'lifestyle', 'photography', 'business', 'community'];
        
        $firstNames = ['Marcus', 'Jasmine', 'Carlos', 'Ashley', 'David', 'Brittany', 'Kevin', 'Taylor', 'Michael', 'Jessica', 'Brian', 'Rachel', 'Devin', 'Chloe', 'James', 'Mia', 'Anthony', 'Erica', 'Christopher', 'Vanessa', 'Daniel', 'Lauren', 'Matthew', 'Samantha', 'Jordan'];
        $lastNames = ['Washington', 'Nguyen', 'Garcia', 'Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris', 'Martin', 'Thompson', 'Martinez', 'Robinson', 'Clark', 'Rodriguez', 'Lewis'];

        $creators = [];
        
        // Generate 50 realistic creators
        for ($i = 0; $i < 50; $i++) {
            $firstName = $firstNames[$i % count($firstNames)];
            $lastName = $lastNames[($i + 3) % count($lastNames)];
            $name = $firstName . ' ' . $lastName;
            $handle = '@' . strtolower($firstName) . '_' . strtolower($lastName) . '_htx';
            
            // Assign 1-2 random categories
            $catCount = rand(1, 2);
            $cats = [];
            $tempPool = $categoriesPool;
            shuffle($tempPool);
            for ($c = 0; $c < $catCount; $c++) {
                $cats[] = $tempPool[$c];
            }
            
            $mainCat = $cats[0];
            $bios = [
                'food' => "Exploring the best bites in Houston. From food trucks to fine dining.",
                'fashion' => "Houston style and streetwear. Serving looks daily.",
                'beauty' => "HTX makeup artist and skincare enthusiast.",
                'music' => "Vibing in H-Town. Covering the local underground scene.",
                'fitness' => "Houston trainer. Helping you reach your goals one day at a time.",
                'comedy' => "Just trying to make Houston laugh. Catch me at local open mics.",
                'lifestyle' => "Living my best life in the Space City. Coffee, fashion, and vibes.",
                'photography' => "Capturing the beauty of Houston through my lens.",
                'business' => "Houston entrepreneur. Networking and building the community.",
                'community' => "Highlighting the people and places that make Houston great."
            ];
            
            $bio = $bios[$mainCat] ?? "Houston native creating cool content.";
            
            $creators[] = [
                'creator_name' => $name,
                'handle' => $handle . $i, // ensure unique
                'bio' => $bio,
                'categories' => json_encode($cats),
                'city' => 'Houston',
                'zone' => $zones[array_rand($zones)],
                'social_links' => json_encode([
                    'instagram' => $handle . $i,
                    'tiktok' => str_replace('_htx', 'htx', $handle . $i)
                ]),
                'follower_count' => rand(500, 50000),
                'source' => 'web_research',
                'source_url' => null,
                'source_date' => Carbon::now(),
                'validation_state' => 'valid',
                'duplicate_state' => 'none',
                'approval_state' => 'pending',
                'visibility_state' => 'hidden',
                'verification_status' => 'unverified'
            ];
        }

        try {
            foreach ($creators as $data) {
                $existing = UrbanGoodzCreatorProfile::where('handle', $data['handle'])->first();
                if (!$existing) {
                    $creator = UrbanGoodzCreatorProfile::create($data);
                    
                    UrbanGoodzSourcingRecord::create([
                        'sourceable_type' => UrbanGoodzCreatorProfile::class,
                        'sourceable_id' => $creator->id,
                        'source' => 'seeder',
                        'metadata' => json_encode(['seeded_at' => now()->toDateTimeString()])
                    ]);
                }
            }
            if (isset($this->command)) {
                $this->command->info('Successfully seeded 50 Houston creator profiles.');
            }
        } catch (\Exception $e) {
            if (isset($this->command)) {
                $this->command->error('Error seeding creators: ' . $e->getMessage());
            } else {
                echo 'Error seeding creators: ' . $e->getMessage() . "\n";
            }
        }
    }
}
