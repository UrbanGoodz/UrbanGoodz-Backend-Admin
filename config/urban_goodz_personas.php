<?php

/*
| Urban Goodz AI personalities.
|
| One platform, specialized personalities. Intelligence, memory, permissions and
| knowledge are shared; presentation and voice are not. See
| docs/URBAN_GOODZ_AI_PERSONALITIES.md for the spec these values implement.
|
| Presentation is config-driven on purpose: display names and avatar art can
| change without a code deploy. When `avatar` is null the UI renders an initials
| monogram in the accent color, so every surface is complete before final art
| exists.
*/

return [
    'default' => 'concierge',

    /*
    | Which persona owns which surface. Anything unlisted falls back to
    | 'default', so a new surface is never silently voiceless.
    */
    'surfaces' => [
        'admin' => 'chief_of_staff',
        'admin_test' => 'chief_of_staff',
        'business_portal' => 'chief_of_staff',
        'executive_dashboard' => 'chief_of_staff',
        'dispatch' => 'chief_of_staff',
        'operations' => 'chief_of_staff',

        'customer_api' => 'concierge',
        'customer_app' => 'concierge',
        'marketplace' => 'concierge',
        'creator_commerce' => 'concierge',
    ],

    'personas' => [
        'chief_of_staff' => [
            'presentation' => [
                'display_name' => env('UG_PERSONA_COS_NAME', 'Skylar'),
                'role_title' => 'Chief of Staff',
                'tagline' => 'Operations, strategy, and everything before it becomes an emergency.',
                'avatar' => env('UG_PERSONA_COS_AVATAR', 'assets/image/personas/skylar_avatar.png'),
                'portrait' => env('UG_PERSONA_COS_PORTRAIT', 'assets/image/personas/skylar_portrait.png'),
                'character_sheet' => 'assets/image/personas/skylar_character_sheet.png',
                // Null lets her open naturally ("Good morning, D'Andre.").
                // Set a value here to force a fixed opener.
                'greeting' => env('UG_PERSONA_COS_GREETING'),
                'initials' => 'S',
                'accent' => '#1F3A5F',
                'accent_soft' => '#E8EDF4',
                'digital_human' => [
                    'voice_id' => env('UG_PERSONA_COS_VOICE_ID', 'skylar_executive_v1'),
                    'rive_asset' => 'assets/digital_human/models/skylar_state_machine.riv',
                    'environment' => 'executive_operations_center',
                    'environment_asset' => 'assets/digital_human/environments/executive_center.png',
                    'default_mood' => 'poised_executive',
                    'supports_voice_stream' => true,
                ],
            ],
        ],

        'concierge' => [
            'presentation' => [
                'display_name' => env('UG_PERSONA_CONCIERGE_NAME', 'Monique'),
                'role_title' => 'Local Lifestyle Concierge',
                'tagline' => 'Knows where to eat, what to buy, and how to get it to your door.',
                'avatar' => env('UG_PERSONA_CONCIERGE_AVATAR', 'assets/image/personas/monique_avatar.png'),
                'portrait' => env('UG_PERSONA_CONCIERGE_PORTRAIT', 'assets/image/personas/monique_portrait.png'),
                'character_sheet' => 'assets/image/personas/monique_character_sheet.png',
                // Her signature bookends. Spoken verbatim to open and close a
                // conversation.
                'greeting' => env('UG_PERSONA_CONCIERGE_GREETING', 'Hello, how you doing? Whats GOOD'),
                'signoff' => env('UG_PERSONA_CONCIERGE_SIGNOFF', "I'll holla at you later"),
                'initials' => 'M',
                'accent' => '#ED9914',
                'accent_soft' => '#FDF3E3',
                'digital_human' => [
                    'voice_id' => env('UG_PERSONA_CONCIERGE_VOICE_ID', 'monique_concierge_v1'),
                    'rive_asset' => 'assets/digital_human/models/monique_state_machine.riv',
                    'environment' => 'houston_loft',
                    'environment_asset' => 'assets/digital_human/environments/houston_loft.png',
                    'default_mood' => 'confident_smirk',
                    'supports_voice_stream' => true,
                ],
            ],
        ],
    ],

    'digital_human_global' => [
        'engine' => env('UG_DIGITAL_HUMAN_ENGINE', 'rive'), // rive, lottie, fallback_layers
        'target_fps' => 60,
        'voice_provider' => env('UG_VOICE_PROVIDER', 'elevenlabs'), // elevenlabs, openai_realtime, cartesia
        'viseme_sample_rate_ms' => 60,
    ],
];
