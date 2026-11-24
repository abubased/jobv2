<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8">
<style>
  body { font-family: 'Noto Sans Bengali', Arial, sans-serif; background:#f4f5f7; margin:0; padding:0; color:#1f2937; }
  .wrapper { max-width:560px; margin:0 auto; padding:20px; }
  .card { background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; }
  .header { background:#111827; color:#fff; padding:16px 20px; font-size:16px; font-weight:bold; }
  .body { padding:20px; }
  .btn { display:inline-block; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:bold; font-size:14px; margin-right:8px; margin-top:10px; color:#fff !important; }
  .btn-call { background:#16a34a; }
  .btn-wa { background:#25D366; }
  .btn-copy { background:#2563eb; }
  .btn-confirm { display:block; text-align:center; background:#9ca3af; padding:14px; border-radius:8px; font-weight:bold; font-size:15px; color:#fff !important; text-decoration:none; margin-top:20px; }
  .section { margin-top:18px; padding-top:14px; border-top:1px solid #e5e7eb; }
  .label { color:#6b7280; font-size:12px; }
  .value { font-size:15px; margin-bottom:8px; }
  .footer { text-align:center; font-size:12px; color:#9ca3af; padding:16px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">অর্ডার নাম্বার: {{ substr($otp, 0, 2) }}-{{ substr($otp, 2, 2) }}-{{ substr($otp, 4, 2) }}</div>
    <div class="body">
      <p style="font-size:15px;">অনুগ্রহ করে নিচের তথ্য যাচাই করে <strong>অর্ডারটি কনফার্ম করুন।</strong></p>

      <div class="section">
        <div class="label">নাম</div>
        <div class="value">{{ $application->name }}</div>

        <div class="label">যোগাযোগ নাম্বার</div>
        <div class="value">{{ $application->phone }}</div>

        <div class="label">ঠিকানা</div>
        <div class="value">{{ $application->address ?: '—' }}</div>
      </div>

      <div>
        <a href="{{ $copyUrl }}" class="btn btn-copy">📋 তথ্য কপি করুন</a>
        <a href="tel:{{ $application->phone }}" class="btn btn-call">📞 কল করুন</a>
        <a href="https://wa.me/{{ '88' . ltrim((string) $application->phone, '0') }}" class="btn btn-wa">💬 WhatsApp</a>
      </div>

      <a href="{{ $detailUrl }}" class="btn-confirm">✅ অর্ডার কনফার্ম করুন</a>
    </div>
  </div>
  <div class="footer">{{ config('app.name') }}</div>
</div>
</body>
</html>
