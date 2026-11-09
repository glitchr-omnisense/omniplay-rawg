<?php

namespace Omniplay\Rawg\Tests;

use Omniplay\Rawg\RawgProvider;
use Omniplay\Testing\BareTestCase;

/** omniplay/rawg's classes load with omnisense/omniplay and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return RawgProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }
}
