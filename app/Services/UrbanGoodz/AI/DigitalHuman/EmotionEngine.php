<?php

namespace App\Services\UrbanGoodz\AI\DigitalHuman;

class EmotionEngine
{
    /**
     * Map a persona, domain event, and intent into a full digital human reactive state.
     *
     * @param string $personaKey 'concierge' or 'chief_of_staff'
     * @param string $domain 'food', 'fashion', 'medical', 'logistics', 'support', 'finance', 'general'
     * @param string|null $eventType Specific application event (e.g. 'order_delayed', 'cart_abandoned', 'revenue_surge')
     * @param string|null $intent User intent (e.g. 'track_order', 'find_outfit', 'check_revenue')
     * @param bool $hasError Whether an error/issue has occurred
     * @return array<string, mixed>
     */
    public function evaluate(
        string $personaKey,
        string $domain = 'general',
        ?string $eventType = null,
        ?string $intent = null,
        bool $hasError = false
    ): array {
        if ($hasError) {
            return $this->handleErrorState($personaKey);
        }

        if ($personaKey === 'chief_of_staff') {
            return $this->evaluateSkylarState($domain, $eventType, $intent);
        }

        return $this->evaluateEbonyState($domain, $eventType, $intent);
    }

    /**
     * Ebony (Customer Concierge) emotion mapping.
     */
    private function evaluateEbonyState(string $domain, ?string $eventType, ?string $intent): array
    {
        if ($domain === 'food' || $intent === 'find_food') {
            return [
                'mood' => 'food_excited',
                'expression' => 'knowing_smirk_smile',
                'gesture' => 'taste_approval_gesture',
                'posture' => 'relaxed_leaning_forward',
                'lighting_theme' => 'warm_golden_amber',
                'voice_tone' => 'playful_sarcastic',
                'environment' => 'houston_loft',
            ];
        }

        if ($domain === 'fashion' || $intent === 'find_outfit') {
            return [
                'mood' => 'fashion_reveal',
                'expression' => 'confident_trendsetter',
                'gesture' => 'outfit_presentation_gesture',
                'posture' => 'high_fashion_pose',
                'lighting_theme' => 'sunset_gold',
                'voice_tone' => 'stylish_confident',
                'environment' => 'houston_loft',
            ];
        }

        if ($eventType === 'order_placed' || $eventType === 'deal_closed') {
            return [
                'mood' => 'celebrating',
                'expression' => 'radiant_full_smile',
                'gesture' => 'hands_up_celebrate',
                'posture' => 'upright_energetic',
                'lighting_theme' => 'vibrant_gold',
                'voice_tone' => 'enthusiastic_warm',
                'environment' => 'houston_loft',
            ];
        }

        if ($eventType === 'inactivity_prompt') {
            return [
                'mood' => 'playful_tease',
                'expression' => 'side_eye_smirk',
                'gesture' => 'tap_chin_waiting',
                'posture' => 'one_hand_on_hip',
                'lighting_theme' => 'warm_golden_amber',
                'voice_tone' => 'spicy_teasing',
                'environment' => 'houston_loft',
            ];
        }

        return [
            'mood' => 'confident_smirk',
            'expression' => 'witty_intelligent_smirk',
            'gesture' => 'subtle_hand_gesture',
            'posture' => 'relaxed_confident',
            'lighting_theme' => 'warm_golden_amber',
            'voice_tone' => 'urban_authentic',
            'environment' => 'houston_loft',
        ];
    }

    /**
     * Skylar (Chief of Staff) emotion mapping.
     */
    private function evaluateSkylarState(string $domain, ?string $eventType, ?string $intent): array
    {
        if ($eventType === 'risk_flagged' || $eventType === 'traffic_delay') {
            return [
                'mood' => 'risk_alert',
                'expression' => 'focused_direct_look',
                'gesture' => 'open_issue_ticket_gesture',
                'posture' => 'authoritative_lean_forward',
                'lighting_theme' => 'alert_slate_blue',
                'voice_tone' => 'direct_urgent_executive',
                'environment' => 'executive_operations_center',
            ];
        }

        if ($domain === 'finance' || $intent === 'check_revenue' || $eventType === 'revenue_surge') {
            return [
                'mood' => 'executive_insight',
                'expression' => 'poised_confident_smile',
                'gesture' => 'point_to_revenue_chart',
                'posture' => 'upright_executive',
                'lighting_theme' => 'deep_executive_navy',
                'voice_tone' => 'authoritative_reassuring',
                'environment' => 'executive_operations_center',
            ];
        }

        if ($domain === 'logistics' || $domain === 'dispatch') {
            return [
                'mood' => 'operations_monitoring',
                'expression' => 'analytical_nod',
                'gesture' => 'map_route_overlay_gesture',
                'posture' => 'hands_holding_tablet',
                'lighting_theme' => 'deep_executive_navy',
                'voice_tone' => 'measured_executive',
                'environment' => 'executive_operations_center',
            ];
        }

        return [
            'mood' => 'poised_executive',
            'expression' => 'intelligent_calm_smile',
            'gesture' => 'hands_clasped_professional',
            'posture' => 'upright_executive',
            'lighting_theme' => 'deep_executive_navy',
            'voice_tone' => 'measured_executive',
            'environment' => 'executive_operations_center',
        ];
    }

    /**
     * Error / Failure fallback state.
     */
    private function handleErrorState(string $personaKey): array
    {
        if ($personaKey === 'chief_of_staff') {
            return [
                'mood' => 'problem_resolution',
                'expression' => 'serious_concerned_brow',
                'gesture' => 'ticket_escalation_gesture',
                'posture' => 'attentive_upright',
                'lighting_theme' => 'focused_slate',
                'voice_tone' => 'calm_reassuring_executive',
                'environment' => 'executive_operations_center',
            ];
        }

        return [
            'mood' => 'empathetic_support',
            'expression' => 'concerned_direct_eye_contact',
            'gesture' => 'open_support_hands',
            'posture' => 'attentive_leaning_in',
            'lighting_theme' => 'soft_warm_neutral',
            'voice_tone' => 'empathetic_direct',
            'environment' => 'houston_loft',
        ];
    }
}
