<?php

namespace App\Services;

use App\Models\UrbanGoodzStrandedConsent;
use App\Models\UrbanGoodzStrandedVerification;
use Illuminate\Http\Request;

/**
 * The safety gate for Urban Goodz Stranded.
 *
 * Both sides must be verified before either can take part. A stranded
 * customer is inviting a stranger to their location; a Goodz Samaritan is
 * driving alone to meet one. Verifying only the responder would protect one
 * party and expose the other, so the same bar applies to both.
 *
 * IMPORTANT -- the wording in DOCUMENTS below is drafted, not lawyered. It
 * describes what the system actually does, which is the right starting point,
 * but anything asserting how a company responds to law enforcement, retains
 * government ID, or limits liability needs review by counsel before launch,
 * and the version string bumped when it changes.
 */
class UrbanGoodzStrandedSafety
{
    public const DOC_SAFETY_TERMS = 'stranded_safety_terms';
    public const DOC_PRIVACY = 'stranded_identity_privacy';
    public const DOC_SAMARITAN_PLEDGE = 'stranded_samaritan_pledge';

    /**
     * Bump the version whenever the text changes. Consent is recorded against
     * a version, so a change means everybody is asked again rather than
     * silently assumed to have agreed to something they never saw.
     */
    public const VERSIONS = [
        self::DOC_SAFETY_TERMS => '1.0',
        self::DOC_PRIVACY => '1.0',
        self::DOC_SAMARITAN_PLEDGE => '1.0',
    ];

    public static function documents(): array
    {
        return [
            self::DOC_SAFETY_TERMS => [
                'version' => self::VERSIONS[self::DOC_SAFETY_TERMS],
                'title' => 'Safety requirements',
                'body' => implode("\n\n", [
                    'Everyone who uses Urban Goodz Stranded must verify their identity with a valid driver\'s licence before requesting or providing help. This applies to both sides. The person asking for help and the person arriving to give it are each verified.',
                    'You must be the licence holder. Uploading someone else\'s licence, or helping another person use your account, ends access to Stranded permanently.',
                    'A Goodz Samaritan is a verified community member, not a licensed professional. Samaritans may assist with jump starts, tyre changes, fuel delivery, lockouts and battery help only. Towing, recovery, mechanical repair and anything at an accident scene is handled by professional providers.',
                    'Do not accept or provide help if the location is unsafe. If you feel unsafe at any point, use the emergency button. Your live location can be shared with your emergency contact for the duration of a request.',
                    'Both parties are rated after every request. Location, arrival and completion times are recorded for safety and dispute resolution.',
                ]),
            ],

            self::DOC_PRIVACY => [
                'version' => self::VERSIONS[self::DOC_PRIVACY],
                'title' => 'How your identity information is protected',
                'body' => implode("\n\n", [
                    'Your driver\'s licence is stored encrypted and is not visible to other users. The person you help, or who helps you, never sees your licence. They see your first name, your photo, your rating and your verification badge.',
                    'Only your last four licence digits are retained in readable form. The full number is encrypted at rest.',
                    'Your licence images are held on private storage. They are not published, not indexed, and not reachable by a link.',
                    'We do not sell your identity information, and we do not share it with other users, advertisers or data brokers.',
                    'We release identity information outside Urban Goodz only when we are legally required to: a valid law enforcement request, a subpoena, or a court order. We also may release it where there is a genuine and immediate risk to someone\'s safety.',
                    'Every such disclosure is logged, including who asked, what was provided, and the date. Where the law permits us to tell you, we will.',
                    'You can request deletion of your Stranded verification at any time. Deleting it removes your ability to request or provide help until you verify again. Records we are required to keep for legal, safety or financial reasons are retained for as long as the law requires.',
                ]),
            ],

            self::DOC_SAMARITAN_PLEDGE => [
                'version' => self::VERSIONS[self::DOC_SAMARITAN_PLEDGE],
                'title' => 'Goodz Samaritan pledge',
                'body' => implode("\n\n", [
                    'I am helping a neighbour, not performing licensed work. I will only accept requests I can complete safely and competently.',
                    'I will not attempt towing, recovery, mechanical repair, or anything at an accident scene.',
                    'I will not ask for payment outside Urban Goodz, and I will not pressure anyone for a tip or a higher amount than I offered.',
                    'I will treat the person I am helping with respect, and I will leave if either of us feels unsafe.',
                    'I understand that my identity is verified and on record, and that misuse of this network is reported.',
                ]),
            ],
        ];
    }

    /**
     * Whether this user may take part in the given role.
     *
     * Returns a reason rather than a bare false, because "you cannot do this"
     * with no explanation is useless to somebody standing on a hard shoulder.
     */
    public static function gate(?int $userId, string $role): array
    {
        if (!$userId) {
            return self::deny('sign_in_required', 'Sign in to use Urban Goodz Stranded.');
        }

        $verification = UrbanGoodzStrandedVerification::where('user_id', $userId)
            ->where('role', $role)
            ->first();

        if (!$verification) {
            return self::deny('verification_required',
                'Verify your driver\'s licence to continue. Both the person asking for help and the person providing it are verified, for everyone\'s safety.');
        }

        if ($verification->status === 'pending') {
            return self::deny('verification_pending',
                'Your licence is being reviewed. This usually takes a few minutes.');
        }

        if ($verification->status === 'rejected') {
            return self::deny('verification_rejected',
                $verification->rejection_reason ?: 'Your licence could not be verified. Please upload it again.');
        }

        if (!$verification->isUsable()) {
            return self::deny('verification_expired',
                'The licence on file has expired. Upload a current one to continue.');
        }

        foreach (self::requiredDocumentsFor($role) as $document) {
            if (!self::hasConsented($userId, $role, $document)) {
                return self::deny('consent_required',
                    'Please review and accept the Stranded safety terms to continue.',
                    ['document' => $document, 'version' => self::VERSIONS[$document]]);
            }
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    /**
     * May this person ASK for help? Nobody stranded on a roadside should be stopped by paperwork, so the bar is:
     *   - fully ID-verified (licence + terms): allowed and marked verified; or
     *   - a verified phone number and the safety terms/privacy notice accepted: allowed, but marked UNVERIFIED so the
     *     helper is told and can decide before accepting. (Turn this off with STRANDED_LIGHT_REQUESTER=false.)
     * Everything else is refused with the specific reason, so the app can put the right step in front of them.
     */
    public static function requesterGate(?int $userId): array
    {
        $full = self::gate($userId, UrbanGoodzStrandedVerification::ROLE_CUSTOMER);
        if ($full['allowed']) {
            return $full + ['verified' => true];
        }

        $needsId = in_array($full['code'], ['verification_required', 'verification_pending', 'verification_rejected', 'verification_expired'], true);
        if (! $needsId || ! (bool) config('urban_goodz_stranded.light_requester_path', true)) {
            return $full + ['verified' => false];
        }

        $user = \App\Models\User::find($userId);
        if (! $user || empty($user->is_phone_verified)) {
            return self::deny('phone_verification_required', 'Verify your phone number so a helper can reach you. It only takes a moment.') + ['verified' => false];
        }
        foreach (self::requiredDocumentsFor(UrbanGoodzStrandedVerification::ROLE_CUSTOMER) as $document) {
            if (! self::hasConsented($userId, UrbanGoodzStrandedVerification::ROLE_CUSTOMER, $document)) {
                return self::deny('consent_required', 'Please review and accept the Stranded safety terms to continue.',
                    ['document' => $document, 'version' => self::VERSIONS[$document]]) + ['verified' => false];
            }
        }

        return ['allowed' => true, 'code' => 'unverified_requester', 'message' => null, 'verified' => false];
    }

    /** Has this requester cleared the full ID check? Shown to helpers so they can weigh it. */
    public static function isVerifiedRequester(?int $userId): bool
    {
        return $userId ? (bool) self::gate($userId, UrbanGoodzStrandedVerification::ROLE_CUSTOMER)['allowed'] : false;
    }

    /** A regular user helping a neighbour: lighter than a Goodz Samaritan (no licence check), still gated. */
    public const ROLE_COMMUNITY = 'community';

    /**
     * May this ordinary user be sent to a stranger's request? They need a verified phone, an account in good
     * standing, no unresolved safety report from a customer they helped, and the same safety terms, privacy
     * notice and pledge a Samaritan accepts. The stranded person still sees who is coming and confirms them
     * with the help code, so this is a floor, not the only safeguard.
     */
    public static function communityGate(?int $userId): array
    {
        if (!$userId) {
            return self::deny('sign_in_required', 'Sign in to help your neighbours.');
        }
        $user = \App\Models\User::find($userId);
        if (!$user || (isset($user->is_active) && !$user->is_active) || (isset($user->status) && $user->status !== null && (int) $user->status === 0)) {
            return self::deny('account_unavailable', 'This account cannot take Stranded requests.');
        }
        if (empty($user->is_phone_verified)) {
            return self::deny('phone_verification_required', 'Verify your phone number to help neighbours nearby.');
        }

        $helped = \App\Models\UrbanGoodzStrandedOffer::where('responder_id', $userId)->whereNotNull('selected_at')->pluck('request_id');
        if ($helped->isNotEmpty() && \App\Models\UrbanGoodzStrandedSafetyReport::whereIn('request_id', $helped)
            ->where('reporter_role', 'customer')->whereNull('resolved_at')->exists()) {
            return self::deny('safety_review_open', 'A safety report about a recent job is being reviewed, so new requests are paused.');
        }

        foreach (self::requiredDocumentsFor(self::ROLE_COMMUNITY) as $document) {
            if (!self::hasConsented($userId, self::ROLE_COMMUNITY, $document)) {
                return self::deny('consent_required', 'Please review and accept the Stranded safety terms to help neighbours.',
                    ['document' => $document, 'version' => self::VERSIONS[$document]]);
            }
        }

        return ['allowed' => true, 'code' => null, 'message' => null];
    }

    public static function requiredDocumentsFor(string $role): array
    {
        $documents = [self::DOC_SAFETY_TERMS, self::DOC_PRIVACY];

        if ($role === UrbanGoodzStrandedVerification::ROLE_SAMARITAN || $role === self::ROLE_COMMUNITY) {
            $documents[] = self::DOC_SAMARITAN_PLEDGE;
        }

        return $documents;
    }

    public static function hasConsented(int $userId, string $role, string $document): bool
    {
        return UrbanGoodzStrandedConsent::where('user_id', $userId)
            ->where('role', $role)
            ->where('document', $document)
            ->where('version', self::VERSIONS[$document] ?? '')
            ->exists();
    }

    /**
     * Records consent against the current version, with the IP and agent that
     * accepted it. updateOrCreate keeps it idempotent -- somebody tapping
     * Accept twice should not produce two records.
     */
    public static function recordConsent(int $userId, string $role, string $document, ?Request $request = null): void
    {
        if (!array_key_exists($document, self::VERSIONS)) {
            return;
        }

        UrbanGoodzStrandedConsent::updateOrCreate(
            [
                'user_id' => $userId,
                'role' => $role,
                'document' => $document,
                'version' => self::VERSIONS[$document],
            ],
            [
                'accepted_at' => now(),
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 500),
            ]
        );
    }

    private static function deny(string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'allowed' => false,
            'code' => $code,
            'message' => $message,
        ], $extra);
    }
}
