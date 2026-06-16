<?php

namespace SzentirasHu\Test\Cdn;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use SzentirasHu\Test\Common\TestCase;

class PurgeCdnCacheCommandTest extends TestCase
{
    public function test_command_purges_when_configured(): void
    {
        Config::set('services.cloudflare.zone_id', 'zone123');
        Config::set('services.cloudflare.api_token', 'token456');
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->artisan('cdn:purge')
            ->expectsOutputToContain('purged successfully')
            ->assertExitCode(0);
    }

    public function test_command_skips_when_not_configured(): void
    {
        Config::set('services.cloudflare.zone_id', null);
        Config::set('services.cloudflare.api_token', null);
        Http::fake();

        $this->artisan('cdn:purge')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
