<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Update</title>
</head>

<body style="margin:0; padding:0; background:#f3f4f6;">

    <table width="100%" cellpadding="0" cellspacing="0"
           style="background:#f3f4f6; padding:30px 10px;">

        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="width:100%; max-width:600px;
                              background:#ffffff;
                              border-radius:12px;
                              overflow:hidden;
                              font-family:Arial, sans-serif;">
                    {{-- CONTENT --}}
                    <tr>
                        <td style="padding:35px;">
                            <table width="100%" cellpadding="12"
                                   cellspacing="0"
                                   style="border-collapse:collapse;">
                                <tr>
                                    <td style="border-bottom:1px solid #e5e7eb;">
                                        <strong>নাম</strong>
                                    </td>

                                    <td style="border-bottom:1px solid #e5e7eb;">
                                        {{ $application->name }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="border-bottom:1px solid #e5e7eb;">
                                        <strong>আবেদন ID</strong>
                                    </td>

                                    <td style="border-bottom:1px solid #e5e7eb;">
                                        #{{ $application->id }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border-bottom:1px solid #e5e7eb;">
                                        {{ $application->payment_number ?? 'N/A' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Contact Number</strong>
                                    </td>

                                    <td>
                                        {{ $application->phone ?? 'N/A' }}
                                    </td>
                                </tr>

                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>

    </table>

</body>
</html>
