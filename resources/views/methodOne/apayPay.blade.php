<!DOCTYPE html>
<html lang="en">

<head>
    @include('methodOne.partials.head')
</head>
<body class="d-flex text-center">
    <div id="app" class="app" data-v-app="">
            <div class="flex justify-center">
                <iframe
                    src="{{ $paymentUrl }}"
                    title="Payment"
                    style="width: 332px; height: 648px; border: 0;"
                ></iframe>
            </div>
        </div>
    </body>
</html>
