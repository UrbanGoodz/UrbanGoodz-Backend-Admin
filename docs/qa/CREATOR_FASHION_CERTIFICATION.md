# Creator / social and fashion-fit ownership pass

## Defects fixed
1. **`fashion/stylist-requests/{id}/status`**: any signed-in customer could set ANY measurement request to ANY review
   status (approve their own, close someone else's). Now owner-only and limited to `cancelled` (the customer app never calls it).
   Test: `StylistRequestOwnershipTest`.
2. **Creator campaign assignments (`PUT customer/creator/campaigns/{id}`)**: a creator could move their own assignment
   from `pending` or `rejected` straight to `accepted`/`completed`, activating work the brand never approved (analytics count
   `accepted` as active). Now: `approved -> accepted|declined`, `accepted -> completed|declined`; everything else is 409.
   Test: `CreatorAssignmentTransitionTest`. The old `CreatorCampaignTest` was an `assertTrue(true)` placeholder.

## Verified
- `CreatorCommerceController` derives identity from the authenticated account (email/phone), never from the request body.
- Campaign acceptance (`firstOrCreate` on campaign + profile) is idempotent; vendor review is scoped to the vendor's own campaign.

## Open
- Brand review lets a vendor set `completed` directly from `pending`; confirm that is intended (no creator delivery proof).
- No payout is attached to `completed` in this module; if creator payouts are planned they must reuse the guarded payout helper.
- Reel moderation/publication flow not re-audited here (existing `ReelSocialTest`, `CreatorReelTest`, `CreatorSpaceReelWorkflowTest` pass).
