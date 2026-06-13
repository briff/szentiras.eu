<?php

namespace SzentirasHu\Test;

use SzentirasHu\Test\Common\TestCase;

class SubdomainRedirectTest extends TestCase
{
    public function test_ujszov_subdomain_root_redirects_to_gnt(): void
    {
        $response = $this->get('http://ujszov.szentiras.eu/');

        $response->assertStatus(302);
        $response->assertRedirect('https://szentiras.eu/GNT');
    }

    public function test_ujszov_subdomain_any_path_redirects_to_gnt(): void
    {
        $response = $this->get('http://ujszov.szentiras.eu/some/path');

        $response->assertStatus(302);
        $response->assertRedirect('https://szentiras.eu/GNT');
    }

}
