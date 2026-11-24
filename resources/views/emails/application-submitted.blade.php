<!DOCTYPE html>
<html lang="bn">
<head><meta charset="UTF-8">
<style>
  body { font-family: 'Noto Sans Bengali', Arial, sans-serif; background:#f4f5f7; margin:0; padding:0; color:#1f2937; }
  .wrapper { max-width:560px; margin:0 auto; padding:20px; }
  .card { background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; }
  .header { background:#111827; color:#fff; padding:16px 20px; font-size:16px; font-weight:bold; }
  .body { padding:20px; }
  .phone-line { font-size:20px; font-weight:bold; letter-spacing:1px; margin-bottom:10px; }
  .btn { display:inline-block; text-decoration:none; padding:10px 18px; border-radius:6px; font-weight:bold; font-size:14px; margin-right:8px; color:#fff !important; }
  .btn-call { background:#16a34a; }
  .btn-wa { background:#25D366; }
  .section { margin-top:18px; padding-top:14px; border-top:1px solid #e5e7eb; }
  .label { color:#6b7280; font-size:12px; }
  .value { font-size:15px; margin-bottom:8px; }
  table.meta td { padding:4px 0; font-size:14px; vertical-align:top; }
  .footer { text-align:center; font-size:12px; color:#9ca3af; padding:16px; }
</style>
</head>
<body>
      @php
          $formattedPhone = trim(chunk_split((string) $application->phone, 3, ' '));
          $waNumber = '88' . ltrim((string) $application->phone, '0');

          $meta = $application->meta_info;
          if (is_string($meta)) {
              $decoded = json_decode($meta, true);
              $meta = is_array($decoded) ? $decoded : [];
          } elseif (! is_array($meta)) {
              $meta = [];
          }
      @endphp
<div class="wrapper">
  <div class="card">
    <div class="header phone-line">নাম্বার: {{ $formattedPhone }}</div>
    <div class="body">

      <div class="">{{ $application->name }}</div>

      <div>
        <a href="tel:{{ $application->phone }}" class="btn btn-call">📞 কল করুন</a>
        <a href="https://wa.me/+880{{ $waNumber }}" class="btn btn-wa">💬 WhatsApp</a>
      </div>

      <div class="section">
        <div class="label">আবেদনকারীর নাম</div>
        <div class="value">{{ $application->name }}</div>

        <div class="label">ঠিকানা</div>
        <div class="value">{{ $application->address ?: '—' }}</div>
      </div>

      @if (! empty($meta))
      <div class="section">
        <div class="label" style="margin-bottom:6px;">অন্যান্য তথ্য</div>
        <table class="meta" width="100%">
          @foreach ($meta as $key => $value)
            <tr>
              <td style="width:40%; color:#6b7280;">{{ $key }}</td>
              <td>{{ is_array($value) ? implode(', ', $value) : $value }}</td>
            </tr>
          @endforeach
        </table>
      </div>
      @endif
    </div>
  </div>
  <div class="footer">{{ config('app.name') }}</div>
</div>
</body>
</html>
