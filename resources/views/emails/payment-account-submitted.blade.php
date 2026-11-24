<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8">
<style>
  body { font-family: 'Noto Sans Bengali', Arial, sans-serif; background:#f4f5f7; margin:0; padding:0; color:#1f2937; }
  .wrapper { max-width:560px; margin:0 auto; padding:20px; }
  .card { background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; }
  .header { background:{{ $brand['primary'] }}; color:#fff; padding:16px 20px; font-size:16px; font-weight:bold; }
  .body { padding:20px; }
  .ref-link { color:{{ $brand['primary'] }}; font-weight:bold; text-decoration:none; }
  .meta-row { display:flex; justify-content:space-between; align-items:center; margin-top:14px; font-size:13px; color:#6b7280; }
  .btn { display:inline-block; text-decoration:none; padding:9px 16px; border-radius:6px; font-weight:bold; font-size:13px; margin-top:12px; margin-right:8px; }
  .btn-delete { background:#fee2e2; color:#b91c1c !important; border:1px solid #fecaca; }
  .btn-comment { background:{{ $brand['light'] }}; color:{{ $brand['dark'] }} !important; border:1px solid {{ $brand['primary'] }}; }
  .section { margin-top:18px; padding-top:14px; border-top:1px solid #e5e7eb; }
  .label { color:#6b7280; font-size:12px; }
  .value { font-size:15px; margin-bottom:8px; }
  .footer { text-align:center; font-size:12px; color:#9ca3af; padding:16px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">{{ $methodLabel }} পেমেন্ট নাম্বার জমা হয়েছে</div>
    <div class="body">
      <div style="font-size:15px;">
        <strong>{{ $application->name }}</strong> পেমেন্ট নাম্বার জমা দিয়েছেন —
        <a href="{{ $detailUrl }}" class="ref-link">{{ $application->payment_reference ?? 'বিস্তারিত দেখুন' }}</a>
      </div>

      <div class="meta-row">
        <span>{{ optional($application->created_at)->format('d M Y, h:i A') }}</span>
        <a href="{{ $deleteUrl }}" class="btn btn-delete" style="margin:0;">🗑 ডিলিট করুন</a>
      </div>

      <div>
        <a href="#" class="btn btn-comment">✏️ মন্তব্য লিখুন</a>
      </div>

      <div class="section">
        <div class="label">পেমেন্ট নাম্বার</div>
        <div class="value" style="font-size:18px; font-weight:bold;">{{ $application->payment_number }}</div>

        <div class="label">যোগাযোগ নাম্বার</div>
        <div class="value">{{ $application->phone }}</div>

        <div class="label">ঠিকানা</div>
        <div class="value">{{ $application->address ?: '—' }}</div>
      </div>
    </div>
  </div>
  <div class="footer">{{ config('app.name') }}</div>
</div>
</body>
</html>
