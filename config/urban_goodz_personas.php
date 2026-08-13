<?php

/*
|--------------------------------------------------------------------------
| Urban Goodz AI Personalities Configuration
|--------------------------------------------------------------------------
|
| One platform, specialized digital human personalities.
|
| Monique: Official AI Concierge (Customer-facing, Website, Stranded, Marketplace)
| Skylar: Executive AI Assistant & Chief of Staff (Vendor App, Business Portal, Admin)
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
                'display_name' => env('UG_PERSONA_COS_NAME', 'Skylar'),
                'role_title' => 'Executive AI Assistant & Strategic Operations Partner',
                'tagline' => 'We have goals. We have a plan. Let\'s execute.',
                'avatar' => env('UG_PERSONA_COS_AVATAR', 'assets/digital_human/skylar/skylar_avatar_headshot.jpg'),
                'portrait' => env('UG_PERSONA_COS_PORTRAIT', 'assets/digital_human/skylar/skylar_fullbody.jpg'),
                'character_sheet' => 'assets/digital_human/skylar/character_bible.md',
                'greeting' => env('UG_PERSONA_COS_GREETING', 'Good to see you. Let\'s get focused.'),
                'initials' => 'S',
                'accent' => '#1D4ED8',
                'accent_soft' => '#E8EDF4',
                'digital_human' => [
                    'voice_id' => env('SKYLAR_ELEVENLABS_VOICE_ID', env('UG_PERSONA_COS_VOICE_ID', 'VUxdWMTconXKENnxAwCg')),
                    'voice_name' => 'Skylar Voice Live',
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
                'display_name' => env('UG_PERSONA_CONCIERGE_NAME', 'Monique'),
                'role_title' => 'Urban Goodz AI Concierge — The Face of Urban Goodz',
                'tagline' => 'Your Connection to Local Everything',
                'avatar' => env('UG_PERSONA_CONCIERGE_AVATAR', 'assets/digital_human/monique/monique_avatar_headshot.jpg'),
                'portrait' => env('UG_PERSONA_CONCIERGE_PORTRAIT', 'assets/digital_human/monique/monique_fullbody.jpg'),
                'character_sheet' => 'assets/digital_human/monique/character_bible.md',
                'greeting' => env('UG_PERSONA_CONCIERGE_GREETING', 'How you doin\'? What\'s GOOD?'),
                'signoff' => env('UG_PERSONA_CONCIERGE_SIGNOFF', 'I\'ll holla at you later, baby'),
                'initials' => 'M',
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
        'environment_variables' => [
            'ELEVENLABS_API_KEY',
            'MONIQUE_ELEVENLABS_VOICE_ID',
            'SKYLAR_ELEVENLABS_VOICE_ID',
        ],
    ],
];
