<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Open your Parallax answer</title><style>body{margin:0;background:#10071b;color:#f7eefb;font:17px/1.65 system-ui}main{max-width:560px;margin:12vh auto;padding:35px;border:1px solid #634778;border-radius:16px}h1{font:400 42px/1.1 Georgia}p{color:#cebfd8}button{font:600 17px system-ui;padding:15px 24px;border:1px solid #c798f3;border-radius:7px;background:#7c26c5;color:white;cursor:pointer}@media(max-width:600px){main{margin:30px 16px;padding:25px}}</style></head>
<body><main><p>PARALLAX / YOUR ANSWER</p><h1>Your first answer has a home.</h1><p>You’re signed in as {{ $launch->user->email }}.</p>
@if($trial)
<p>{{ $trial->expires_at->isFuture() ? 'Your current trial keeps its original end date.' : 'Your previous trial has ended. You can still open your saved answer.' }} No new trial will be started.</p>
@else
<p>Activate your {{ $days }}-day trial to continue from your question and first answer in your workspace.</p><p>No card is required. The trial ends without an automatic charge.</p>
@endif
<form method="post" action="{{ request()->fullUrl() }}">@csrf<button type="submit">{{ $trial ? 'Open my saved answer →' : 'Activate my trial and open my answer →' }}</button></form></main></body></html>
