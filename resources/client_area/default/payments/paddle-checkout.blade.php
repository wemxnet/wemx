@extends('theme::auth.wrapper')

@section('title', 'Complete Payment')

@section('content')
    <div class="flex flex-col items-center justify-center" id="paddle-checkout-status">
        <x-theme::text.h4 class="mb-4 text-center">Preparing your payment...</x-theme::text.h4>
        <x-theme::text.p class="mb-8 text-center">
            Please wait while we load the secure checkout. If nothing appears, make sure your browser isn't blocking pop-ups or third-party scripts.
        </x-theme::text.p>
    </div>

    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <script>
        (function () {
            @if($sandbox)
                Paddle.Environment.set('sandbox');
            @endif

            Paddle.Initialize({
                token: @json($clientSideToken),
                eventCallback: function (event) {
                    if (event.name === 'checkout.completed') {
                        window.location.href = @json($callbackUrl);
                    }
                },
            });

            Paddle.Checkout.open({
                transactionId: @json($transactionId),
            });
        })();
    </script>
@endsection
