<?php

namespace App\Modules\Payment;

use App\Modules\Payment\Http\Middleware\EnsurePaymentSessionExists;
use App\Modules\Payment\Support\GatewayManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    private const MODULE_PATH = __DIR__;

    public function register(): void
    {
        $this->mergeConfigFrom(self::MODULE_PATH.'/config/payment.php', 'payment');

        $this->app->singleton(GatewayManager::class);
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(self::MODULE_PATH.'/resources/views', 'payment');
        $this->loadTranslationsFrom(self::MODULE_PATH.'/resources/lang', 'payment');

        $router->aliasMiddleware('apay-payment.session', EnsurePaymentSessionExists::class);

        $router->prefix(config('payment.route_prefix'))
            ->middleware('web')
            ->name(config('payment.route_name_prefix').'.')
            ->group(self::MODULE_PATH.'/routes/web.php');
    }
}
