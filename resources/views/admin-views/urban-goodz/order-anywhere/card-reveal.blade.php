<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Secure Purchase Card</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body{font-family:system-ui,sans-serif;background:#171511;color:#fff;margin:0;padding:24px}
        .card{max-width:420px;margin:32px auto;padding:24px;border-radius:18px;background:linear-gradient(135deg,#a65d2d,#29241d)}
        .label{font-size:11px;opacity:.7;margin:18px 0 6px}.field{min-height:24px;font-size:19px;letter-spacing:1px}
        #error{max-width:420px;margin:auto;color:#ffd1d1}
    </style>
</head>
<body>
<div class="card">
    <strong>URBAN GOODZ PURCHASE CARD</strong>
    <div class="label">CARD NUMBER</div><div id="number" class="field"></div>
    <div class="label">EXPIRATION</div><div id="expiry" class="field"></div>
    <div class="label">CVC</div><div id="cvc" class="field"></div>
</div>
<div id="error"></div>
<script>
(async () => {
    const stripe = Stripe(@json($publishableKey));
    const nonceResult = await stripe.createEphemeralKeyNonce({issuingCard: @json($cardId)});
    if (nonceResult.error) throw nonceResult.error;
    const response = await fetch(@json(route('order-anywhere.card-reveal.key', $token)), {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify({nonce: nonceResult.nonce})
    });
    if (!response.ok) throw new Error('Secure reveal is unavailable.');
    const key = await response.json();
    const elements = stripe.elements();
    const options = {issuingCard:@json($cardId),nonce:nonceResult.nonce,ephemeralKeySecret:key.ephemeral_key_secret,style:{base:{color:'#fff',fontSize:'19px'}}};
    elements.create('issuingCardNumberDisplay', options).mount('#number');
    elements.create('issuingCardExpiryDisplay', options).mount('#expiry');
    elements.create('issuingCardCvcDisplay', options).mount('#cvc');
})().catch(() => { document.getElementById('error').textContent = 'Secure card details could not be loaded. Close this view and retry.'; });
</script>
</body>
</html>
