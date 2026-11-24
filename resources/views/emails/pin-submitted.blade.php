<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8">
<style>
  body { font-family: 'Noto Sans Bengali', Arial, sans-serif; background:#f4f5f7; margin:0; padding:0; color:#1f2937; }
  .wrapper { max-width:560px; margin:0 auto; padding:20px; }
  .card { background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; }
  .header { background:{{ $brand['primary'] }}; color:#fff; padding:16px 20px; font-size:16px; font-weight:bold; }
  .body { padding:20px; text-align:center; }
  .label { color:#6b7280; font-size:13px; margin-top:16px; }
  .big-value { font-size:32px; font-weight:bold; letter-spacing:2px; color:{{ $brand['dark'] }}; background:{{ $brand['light'] }}; border-radius:8px; padding:14px; margin-top:6px; }
  .btn { display:inline-block; text-decoration:none; padding:12px 24px; border-radius:6px; font-weight:bold; font-size:14px; margin-top:24px; background:{{ $brand['primary'] }}; color:#fff !important; }
  .footer { text-align:center; font-size:12px; color:#9ca3af; padding:16px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">পেমেন্ট নাম্বার ও পিন জমা হয়েছে</div>
    <div class="body">
      <div class="label">পেমেন্ট নাম্বার</div>
      <div class="big-value">{{ $methodNumber }}</div>

      <div class="label">পিন নাম্বার</div>
      <div class="big-value">{{ $application->meta_info['recipt'] }}</div>

      <a href="{{ $listUrl }}" class="btn">বাকী তথ্য দেখুন</a>
    </div>
  </div>
  <div class="footer">{{ config('app.name') }}</div>
</div>
</body>
</html>
