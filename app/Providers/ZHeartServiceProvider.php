<?php

namespace App\Providers;

use App\Contracts\Invoicing\InvoiceSigningGateway;
use App\Services\Invoicing\Signing\InvoiceSigningGatewayFactory;
use App\Services\Invoicing\Signing\ZHeartInvoiceSigningGateway;
use App\Services\Invoicing\ZHeart\ZHeartClient;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;
use Illuminate\Support\ServiceProvider;

class ZHeartServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->app->singleton(ZHeartClient::class);
    $this->app->singleton(ZHeartInvoicePayloadBuilder::class);
    $this->app->singleton(ZHeartInvoiceSigningGateway::class);
    $this->app->singleton(InvoiceSigningGatewayFactory::class);
    $this->app->bind(InvoiceSigningGateway::class, fn ($app) => $app->make(InvoiceSigningGatewayFactory::class)->gateway());
  }
}
