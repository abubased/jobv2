<!DOCTYPE html>
<html lang="bn">
<body style="font-family:sans-serif; max-width:420px; margin:60px auto; text-align:center;">
    <p>আপনি কি নিশ্চিত <strong>{{ $application->name }}</strong>-এর আবেদনটি মুছে ফেলতে চান?</p>
    <form method="POST" action="{{ route('applications.delete', $application) }}">
        @csrf
        <button type="submit" style="background:#dc2626;color:#fff;padding:10px 24px;border:none;border-radius:6px;">হ্যাঁ, মুছে ফেলুন</button>
    </form>
</body>
</html>
