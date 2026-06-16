<?php

namespace SzentirasHu\Test\Feature;

use Illuminate\Support\Facades\Session;
use SzentirasHu\Test\Common\TestCase;

class LoggedInMetaTest extends TestCase
{
    public function test_meta_marks_anonymous_visitor(): void
    {
        Session::forget('anonymous_token');

        $html = view('partials.loggedInMeta')->render();

        $this->assertStringContainsString('name="anonymous-logged-in"', $html);
        $this->assertStringContainsString('content="0"', $html);
    }

    public function test_meta_marks_logged_in_visitor(): void
    {
        Session::put('anonymous_token', 'some-token');

        $html = view('partials.loggedInMeta')->render();

        $this->assertStringContainsString('content="1"', $html);
    }
}
