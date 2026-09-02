<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UrbanGoodzHistoricalNamesSeeder extends Seeder
{
    public const CUSTOMER_NAMES = [
        'Aaliyah Thompson', 'Aaron Mitchell', 'Abigail Reyes', 'Adrian Coleman',
        'Aisha Williams', 'Alan Price', 'Alberto Vasquez', 'Alexandra Bennett',
        'Alfredo Torres', 'Alice Howard', 'Alicia Ramirez', 'Amanda Foster',
        'Amber Jenkins', 'Andrea Sullivan', 'Angel Cruz', 'Angela Brooks',
        'Ann Carter', 'Anthony Davis', 'Antonio Watson', 'Aquila Gray',
        'Armando Flores', 'Ashley Morgan', 'Autumn Bailey', 'Barbara Reed',
        'Barry Long', 'Becky Peterson', 'Benjamin Scott', 'Bernard Howard',
        'Beth Hughes', 'Beverly Murphy', 'Bill Turner', 'Billy Jenkins',
        'Blake Robinson', 'Bobby Ross', 'Bonnie Gray', 'Bradley King',
        'Brandon Young', 'Brenda Hall', 'Brent Adams', 'Brian Campbell',
        'Brittany James', 'Bruce Edwards', 'Bryan Rivera', 'Caleb Wood',
        'Calvin Powell', 'Cameron Bell', 'Carl Washington', 'Carlos Wright',
        'Carol Lewis', 'Catherine Walker', 'Cedric Henderson', 'Chad Coleman',
        'Charles Moore', 'Charlotte Baker', 'Cheryl Green', 'Chester Adams',
        'Chris Phillips', 'Christina Evans', 'Claire Russell', 'Clarence Howard',
        'Cody Harris', 'Connie Cooper', 'Corey Richardson', 'Crystal Cox',
        'Curtis Mitchell', 'Cynthia Parker', 'Daisy Sanders', 'Dale Price',
        'Damien Brooks', 'Dana Stewart', 'Daniel Murphy', 'Darla Rogers',
        'Darrell Reed', 'Darwin Cook', 'Dave Morgan', 'David Bailey',
        'Dawn Bell', 'Deacon Sullivan', 'Dean Foster', 'Debbie Turner',
        'Deborah Perry', 'Debra Russell', 'Denise Coleman', 'Dennis Ward',
        'Derek Ross', 'Derrick Howard', 'Destiny Campbell', 'Diana Long',
        'Diane Murphy', 'Dolores Carter', 'Don Hall', 'Donald Barnes',
        'Donna Powell', 'Doris Young', 'Dorothy King', 'Doug Peterson',
        'Douglas Rivera', 'Dwayne Butler', 'Dwight Brooks', 'Earl Watson',
        'Edgar Thompson', 'Edith Sanchez', 'Eduardo Reyes', 'Edward James',
        'Eileen Myers', 'Elaine Wood', 'Eleanor Cole', 'Elijah Howard',
        'Eliza Morgan', 'Elizabeth Baker', 'Emily Bennett', 'Emma Gray',
        'Eric Cooper', 'Erica Stewart', 'Erik Price', 'Erin Simmons',
        'Ernest Collins', 'Esther Morgan', 'Eugene Powell', 'Eva Watson',
        'Evan Foster', 'Fatima Collins', 'Felicia Ross', 'Florence Perry',
        'Frances Gray', 'Frank Howard', 'Franklin Edwards', 'Frederick Bell',
        'Gabrielle Murphy', 'Gail Simmons', 'Gary Russell', 'Genevieve Kelly',
        'George Cox', 'Gerald Barnes', 'Gloria Perry', 'Gordon Foster',
        'Grace Howard', 'Gregory Cox', 'Gwendolyn Murray', 'Harold Long',
        'Heather Perry', 'Helen Howard', 'Henry Russell', 'Herbert Price',
        'Hilda Morgan', 'Holly Barnes', 'Howard Perry', 'Ian Coleman',
    ];

    public const DRIVER_NAMES = [
        'D\'Andre Good',
        'Marcus Williams',
        'Tyrone Jackson',
        'DeShawn Carter',
        'Jamal Robinson',
        'Terrell Mitchell',
        'Andre Brooks',
        'Chris Howard',
        'Brandon Price',
        'Kevin Sanders',
        'Darius Evans',
        'Malik Cooper',
        'Jaylen Thompson',
        'Camden Reed',
        'Isaiah Butler',
        'Lamar Foster',
        'DeAndre Washington',
        'Xavier Coleman',
        'Nathaniel Gray',
        'Terrance Scott',
        'Rashid Abdullah',
        'Elijah Brooks',
        'Marcus Lewis',
        'David Mitchell',
        'Jordan Perry',
        'Omar Hicks',
    ];

    public function run(): void
    {
        $this->command->info('Historical Names Seeder loaded.');
        $this->command->info('Customer names: ' . count(self::CUSTOMER_NAMES));
        $this->command->info('Driver names: ' . count(self::DRIVER_NAMES));
    }

    public static function getCustomerNames(): array
    {
        return self::CUSTOMER_NAMES;
    }

    public static function getDriverNames(): array
    {
        return self::DRIVER_NAMES;
    }

    public static function getRandomCustomer(int $seed): string
    {
        $index = abs($seed) % count(self::CUSTOMER_NAMES);
        return self::CUSTOMER_NAMES[$index];
    }

    public static function getRandomDriver(int $seed): string
    {
        $index = abs($seed) % count(self::DRIVER_NAMES);
        return self::DRIVER_NAMES[$index];
    }
}
