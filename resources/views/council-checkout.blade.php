<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark"><title>{{ $selection['name'] }} · Parallax Nexus</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;500;600;700&family=Orbitron:wght@400;500;600&display=swap"><style>
*{box-sizing:border-box}body{margin:0;background:#070f1a;color:#cbd5e1;font-family:'Exo 2',system-ui,sans-serif;line-height:1.6}main{max-width:760px;margin:6vh auto;padding:28px}header{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:60px}header img{width:150px;max-width:50%}a{color:#fde68a}h1{font-family:'Orbitron',sans-serif;font-weight:400;font-size:clamp(32px,6vw,52px);line-height:1.15;margin:14px 0 24px}.eyebrow{color:#fbbf24;font-size:11px;letter-spacing:.25em;text-transform:uppercase}.card{padding:30px;border:1px solid #ffffff1a;border-radius:16px;background:linear-gradient(135deg,#111a21,#050b14)}ul{padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:8px}li{background:#ffffff08;border:1px solid #ffffff1a;border-radius:30px;padding:6px 12px;font-size:13px}.price{font-size:42px;line-height:1.2;margin:26px 0 12px}.price small{font-size:16px;color:#94a3b8}.note{color:#94a3b8;font-size:13px}.button{display:inline-block;padding:13px 22px;border:1px solid #fbbf24;border-radius:7px;background:#fbbf24;color:#020617;font:600 14px system-ui;text-decoration:none;cursor:pointer}button:disabled{background:#332438;color:#b6a6bf;cursor:default}.status{padding:16px;border-left:3px solid #d3ab67;background:#d3ab670c;margin:20px 0}footer{margin-top:26px;color:#94a3b8;font-size:12px}:focus-visible{outline:2px solid #efcd9a;outline-offset:5px}
</style></head><body><main><header><img src="/assets/Parallax.svg" alt="Parallax"><span class="eyebrow">Slipstream × Continuum / Account</span></header><div class="eyebrow">Review your council</div><h1>{{ $selection['name'] }}</h1>
<div class="card"><ul>
@foreach($selection['executives'] as $executive)<li>{{ app(\App\Support\CouncilCatalog::class)->executiveName($executive) }}</li>
@endforeach</ul>
<div class="price">${{ number_format($selection['amount'] / 100, 0) }} <small>/ {{ $selection['interval'] === 'annual' ? 'year' : 'month' }}</small></div>
<p>{{ $selection['interval'] === 'annual' ? 'Billed annually. Two months free compared with monthly billing.' : 'Billed monthly.' }}</p><p class="note">Your council subscription renews automatically. Manage or cancel in the billing portal. AI execution, usage and external services are additional.</p>

@if(request('checkout') === 'pending')<div class="status">Checkout returned. Nexus will activate your executive access once Stripe confirms your subscription.</div><a class="button" href="{{ route('billing.show') }}">View account &amp; billing</a>
@else

@if(! $ready)<div class="status">Council checkout is being connected. Your selection is saved in this link.</div>
@endif

@if($errors->any())<div class="status" role="alert">{{ $errors->first() }}</div>
@endif
@guest
<a class="button" href="{{ route('login') }}">Sign in to your account</a>
@else
<form method="post" action="{{ route('council.purchase') }}">@csrf
<input type="hidden" name="plan" value="{{ $selection['key'] }}"><input type="hidden" name="interval" value="{{ $selection['interval'] }}">

@if($selection['key'] === 'custom')
@foreach($selection['executives'] as $executive)<input type="hidden" name="executives[]" value="{{ $executive }}">
@endforeach
@endif
<button class="button" type="submit" @disabled(! $ready)>Continue to secure checkout</button></form>
@endguest

@endif</div><footer>One Nexus identity. One council subscription. Access follows the executives in your plan.</footer></main></body></html>
