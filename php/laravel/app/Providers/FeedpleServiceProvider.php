<?php

namespace App\Providers;

use Feedple\Sdk\Laravel\FeedpleServiceProvider as BaseFeedpleServiceProvider;

/**
 * Feedple Service Provider for Laravel
 *
 * This class extends the official built-in FeedpleServiceProvider provided by feedple/feedple-sdk.
 *
 * NOTE: In your own Laravel application, you do NOT even need to create or register this file!
 * Laravel Package Auto-Discovery automatically discovers and registers
 * `Feedple\Sdk\Laravel\FeedpleServiceProvider` when you install the package.
 */
class FeedpleServiceProvider extends BaseFeedpleServiceProvider
{
    //
}
