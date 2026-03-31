<?php

declare(strict_types=1);

namespace Langfuse\Prism;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Langfuse\Contracts\LangfuseClientInterface;
use Prism\Prism\Enums\Provider as ProviderEnum;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Provider;

class TracingPrismManager extends PrismManager
{
    public function __construct(
        Application $app,
        private readonly PrismManager $inner,
        private readonly LangfuseClientInterface $langfuse,
    ) {
        parent::__construct($app);
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    public function resolve(ProviderEnum|string $name, array $providerConfig = []): Provider
    {
        $provider = $this->inner->resolve($name, $providerConfig);

        return new TracingProvider($provider, $this->langfuse);
    }

    public function extend(string $provider, Closure $callback): PrismManager
    {
        $this->inner->extend($provider, $callback);

        return $this;
    }
}
