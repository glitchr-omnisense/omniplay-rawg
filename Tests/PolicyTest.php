<?php

namespace Omniplay\Rawg\Tests;

use Omniplay\Exception\OmniplayException;
use Omniplay\Exception\UsageLimitReachedException;
use Omniplay\Model\GameRef;
use Omniplay\Rawg\RawgFactory;
use Omniplay\Rawg\RawgProvider;
use Omniplay\Registry;
use Omniplay\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** RAWG's usage policy, as policy.json declares it, held through omniplay's registry. */
final class PolicyTest extends TestCase
{
    public function testThePolicyIsTheOneDeclared(): void
    {
        $policy = self::registry(self::answering())->policy('rawg');

        self::assertSame('rawg', $policy->provider);
        self::assertSame(null, null === $policy->rateLimits ? null : array_map(static fn (RateLimit $limit) => $limit->id(), $policy->rateLimits));
        self::assertSame(Permission::Unknown, $policy->rights->commercial);
        self::assertSame(true, $policy->rights->attribution->required);
        self::assertSame(true, $policy->requirements->key);
        self::assertSame(null, $policy->provenance->verifiedAt);
        self::assertSame(UsagePolicy::fromFile(RawgProvider::POLICY)->toArray(), $policy->toArray(), 'the registry runs the policy the package declares');
        self::assertSame($policy->toArray(), self::registry(self::answering())->get('rawg')->capabilities()->policy()->toArray(), 'its Capabilities carry it too');
    }

    public function testASitesLimitIsCountedAndTheCallOverItIsNeverMade(): void
    {
        $http = self::answering();
        $registry = self::registry($http, ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['game']]]]);

        $registry->catalogue('rawg')->game(GameRef::rawg(1));
        $made = \count($http->requests);
        try {
            $registry->catalogue('rawg')->game(GameRef::rawg(1));
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmniplayException::class, $e, 'the family\'s catch holds it');
            self::assertSame('1/minute/application/game', $e->limit?->id());
        }
        self::assertCount($made, $http->requests, 'the refused call reached nothing');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $http = RecordedHttpClient::routes(['~~' => [429, '', ['retry-after' => ['30']]]]);
        $registry = self::registry($http);

        try {
            $registry->catalogue('rawg')->game(GameRef::rawg(1));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        $made = \count($http->requests);
        try {
            $registry->catalogue('rawg')->game(GameRef::rawg(1));
            self::fail('A call went out while RAWG asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(29, $e->retryAfter);
        }
        self::assertCount($made, $http->requests, 'the wait held the next request back');
    }

    /** @param array<string, mixed> $policy */
    private static function registry(RecordedHttpClient $http, array $policy = []): Registry
    {
        return new Registry([new RawgFactory($http)], ['rawg' => ['factory' => 'rawg', 'options' => ['api_key' => 'not-a-real-rawg-key']] + ([] === $policy ? [] : ['policy' => $policy])]);
    }

    private static function answering(): RecordedHttpClient
    {
        return RecordedHttpClient::routes(['~~' => [404, '{"detail": "Not found."}']]);
    }
}
