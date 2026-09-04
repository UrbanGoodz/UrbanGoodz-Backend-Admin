<?php

/*
|--------------------------------------------------------------------------
| Urban Goodz AI Personalities Configuration
|--------------------------------------------------------------------------
|
| One platform, specialized digital human personalities.
|
| Skylar: Official AI Concierge (Customer-facing, Website, Stranded, Marketplace)
| Monique: Executive AI Assistant & Chief of Staff (Vendor App, Business Portal, Admin)
|
| Display names only were swapped from the original build -- the underlying
| persona keys (concierge / chief_of_staff), personalities, voice audio, and
| character art all still map to the exact same role as before. "concierge"
| is still the sassy customer-facing persona (now displayed as "Skylar");
| "chief_of_staff" is still the executive business persona (now displayed
| as "Monique"). Never assume the persona key matches the display name.
|
*/

return [
    'default' => 'concierge',

    /*
    | Surface to Persona Mapping
    */
    'surfaces' => [
        'admin' => 'chief_of_staff',
        'admin_test' => 'chief_of_staff',
        'business_portal' => 'chief_of_staff',
        'executive_dashboard' => 'chief_of_staff',
        'dispatch' => 'chief_of_staff',
        'operations' => 'chief_of_staff',
        'vendor' => 'chief_of_staff',
        'driver_support' => 'chief_of_staff',

        'customer_api' => 'concierge',
        'customer_app' => 'concierge',
        'marketplace' => 'concierge',
        'creator_commerce' => 'concierge',
        'website' => 'concierge',
        'stranded' => 'concierge',
    ],

    'personas' => [
        'chief_of_staff' => [
            'presentation' => [
                'display_name' => env('UG_PERSONA_COS_NAME', 'Monique'),
                'role_title' => 'Executive AI Assistant & Strategic Operations Partner',
                'tagline' => 'We have goals. We have a plan. Let\'s execute.',
                'avatar' => env('UG_PERSONA_COS_AVATAR', 'assets/digital_human/skylar/skylar_avatar_headshot.jpg'),
                'portrait' => env('UG_PERSONA_COS_PORTRAIT', 'assets/digital_human/skylar/skylar_fullbody.jpg'),
                'character_sheet' => 'assets/digital_human/skylar/character_bible.md',
                'greeting' => env('UG_PERSONA_COS_GREETING', 'What\'s GOOD! I\'m Monique, your Chief of Staff. I\'m here to know your business from the inside out. I\'ll show you where you stand, what\'s working, what needs attention, what you\'re missing, and where the real opportunities are. I\'m not just looking at today\'s numbers. I\'m looking at what they\'re telling us about tomorrow. And when I see a better direction, I\'m going to tell you. No fluff, no guessing, just smart moves. So… let\'s see where you are and figure out where you need to go.'),
                'initials' => 'M',
                'accent' => '#1D4ED8',
                'accent_soft' => '#E8EDF4',
                'digital_human' => [
                    'voice_id' => env('SKYLAR_ELEVENLABS_VOICE_ID', env('UG_PERSONA_COS_VOICE_ID', 'VUxdWMTconXKENnxAwCg')),
                    'voice_name' => 'Monique Voice Live',
                    'rive_asset' => 'assets/digital_human/skylar.riv',
                    'environment' => 'executive_operations_center',
                    'default_mood' => 'executive',
                    'supports_voice_stream' => true,
                    'voice_settings' => [
                        'stability' => 0.65,
                        'similarity_boost' => 0.78,
                        'style' => 0.40,
                        'use_speaker_boost' => true,
                    ],
                ],
            ],
        ],

        'concierge' => [
            'presentation' => [
                'display_name' => env('UG_PERSONA_CONCIERGE_NAME', 'Skylar'),
                'role_title' => 'Urban Goodz AI Concierge — The Face of Urban Goodz',
                'tagline' => 'Your Connection to Local Everything',
                'avatar' => env('UG_PERSONA_CONCIERGE_AVATAR', 'assets/digital_human/monique/monique_avatar_headshot.jpg'),
                'portrait' => env('UG_PERSONA_CONCIERGE_PORTRAIT', 'assets/digital_human/monique/monique_fullbody.jpg'),
                'character_sheet' => 'assets/digital_human/monique/character_bible.md',
                'greeting' => env('UG_PERSONA_CONCIERGE_GREETING', 'What\'s GOOD! I\'m Skylar, your Urban Goodz concierge. Baby, I\'m plugged into all the GOOD stuff around you. Shopping, food, local businesses, services, events, hidden gems… if it\'s happening around you, I\'m trying to know about it. You bring me the mission, I\'ll handle the hunt. Now tell me, what are we getting into?'),
                'signoff' => env('UG_PERSONA_CONCIERGE_SIGNOFF', 'I\'ll holla at you later, baby'),
                'initials' => 'S',
                'accent' => '#B45309',
                'accent_soft' => '#FDF3E3',
                'digital_human' => [
                    'voice_id' => env('MONIQUE_ELEVENLABS_VOICE_ID', env('UG_PERSONA_CONCIERGE_VOICE_ID', '03vEurziQfq3V8WZhQvn')),
                    'voice_name' => 'Sassy Aeristia',
                    'rive_asset' => 'assets/digital_human/monique.riv',
                    'environment' => 'houston_loft',
                    'default_mood' => 'sassy',
                    'supports_voice_stream' => true,
                    'voice_settings' => [
                        'stability' => 0.35,
                        'similarity_boost' => 0.82,
                        'style' => 0.75,
                        'use_speaker_boost' => true,
                    ],
                ],
            ],
        ],
    ],

    'digital_human_global' => [
        'engine' => env('UG_DIGITAL_HUMAN_ENGINE', 'rive'),
        'target_fps' => 60,
        'voice_provider' => env('UG_VOICE_PROVIDER', 'elevenlabs'),
        'viseme_sample_rate_ms' => 60,
        'elevenlabs_api_key' => env('ELEVENLABS_API_KEY'),
        'elevenlabs_model_id' => env('ELEVENLABS_MODEL_ID', 'eleven_turbo_v2_5'),
        'elevenlabs_output_format' => env('ELEVENLABS_OUTPUT_FORMAT', 'mp3_44100_128'),
        'elevenlabs_base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),
        'qwen_base_url' => env('UG_QWEN_BASE_URL', ''),
        'qwen_api_key' => env('UG_QWEN_API_KEY', ''),
        'environment_variables' => [
            'ELEVENLABS_API_KEY',
            'MONIQUE_ELEVENLABS_VOICE_ID',
            'SKYLAR_ELEVENLABS_VOICE_ID',
        ],
    ],
];
