<?php

namespace SzentirasHu\Test\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SzentirasHu\Rules\LocalRedirectRule;

class LocalRedirectRuleTest extends TestCase
{
    #[DataProvider('validLocalPaths')]
    public function testAcceptsValidLocalPaths(string $path): void
    {
        $this->assertTrue(LocalRedirectRule::isLocalPath($path));
    }

    public static function validLocalPaths(): array
    {
        return [
            'simple path'              => ['/SZIT/Mt4,2-6'],
            'path with query string'   => ['/SZIT/Mt4,2-6?media'],
            'root path'                => ['/'],
            'nested path'              => ['/foo/bar/baz'],
            'path with fragment'       => ['/page#section'],
        ];
    }

    #[DataProvider('invalidLocalPaths')]
    public function testRejectsInvalidPaths(string $path): void
    {
        $this->assertFalse(LocalRedirectRule::isLocalPath($path));
    }

    public static function invalidLocalPaths(): array
    {
        return [
            'protocol-relative URL'         => ['//evil.com'],
            'http URL'                       => ['http://evil.com/path'],
            'https URL'                      => ['https://evil.com/path'],
            'javascript protocol'            => ['javascript:alert(1)'],
            'empty string'                   => [''],
            'relative path no slash'         => ['foo/bar'],
            'backslash second hop'           => ['/\\evil.com'],
            'double-encoded protocol-rel'    => ['/%2F%2Fevil.com'],
            'double-encoded backslash hop'   => ['/%2F%5Cevil.com'],
            'triple-encoded protocol-rel'    => ['/%252F%252Fevil.com'],
        ];
    }

    public function testValidatePassesForEmptyValue(): void
    {
        $rule = new LocalRedirectRule();
        $failed = false;
        $rule->validate('redirect', '', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function testValidatePassesForValidLocalPath(): void
    {
        $rule = new LocalRedirectRule();
        $failed = false;
        $rule->validate('redirect', '/SZIT/Mt4,2-6?media', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function testValidateFailsForProtocolRelativeUrl(): void
    {
        $rule = new LocalRedirectRule();
        $failed = false;
        $rule->validate('redirect', '//evil.com', function () use (&$failed) {
            $failed = true;
        });
        $this->assertTrue($failed);
    }

    public function testValidateFailsForAbsoluteUrl(): void
    {
        $rule = new LocalRedirectRule();
        $failed = false;
        $rule->validate('redirect', 'https://evil.com', function () use (&$failed) {
            $failed = true;
        });
        $this->assertTrue($failed);
    }
}
