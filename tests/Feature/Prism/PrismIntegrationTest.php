<?php

declare(strict_types=1);

use Langfuse\Prism\TracingPrismManager;
use Prism\Prism\PrismManager;

it('does not wrap PrismManager when disabled', function () {
    config(['langfuse.prism_enabled' => false]);

    $manager = $this->app->make(PrismManager::class);

    expect($manager)->not->toBeInstanceOf(TracingPrismManager::class);
});

it('wraps PrismManager when enabled', function () {
    config(['langfuse.prism_enabled' => true]);

    // Re-bootstrap with prism enabled
    $this->app->forgetInstance(\Langfuse\Config\LangfuseConfig::class);
    $this->app->forgetInstance(\Langfuse\Contracts\LangfuseClientInterface::class);
    $this->app->forgetInstance(\Langfuse\Contracts\EventBatcherInterface::class);
    $this->app->forgetInstance(\Langfuse\Prompt\PromptManager::class);

    // Manually call the prism integration registration
    $provider = new \Langfuse\LangfuseServiceProvider($this->app);
    $provider->register();
    $provider->boot();

    $manager = $this->app->make(PrismManager::class);

    expect($manager)->toBeInstanceOf(TracingPrismManager::class);
});
