<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\XsrfToken;

use ampf\Service\Session\SessionServiceInterface;
use ampf\Service\XsrfToken\XsrfTokenService;
use ampf\Tests\Support\ArraySessionService;
use ampf\Tests\Support\CopyingSessionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplQueue;

#[CoversClass(XsrfTokenService::class)]
final class XsrfTokenServiceTest extends TestCase
{
    private ArraySessionService $session;

    public function testATokenCarries128RandomBitsAsHex(): void
    {
        $tokens = [];

        for ($request = 0; $request < 50; $request++) {
            $token = $this->newRequest()->getNewToken();

            // 16 random bytes in hex: 32 characters, every one of them random
            self::assertSame(32, strlen($token));
            self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
            $tokens[] = $token;
        }

        self::assertCount(50, array_unique($tokens), 'every request is handed a token of its own');
    }

    public function testARequestCarriesItsTokenAsStkn(): void
    {
        self::assertSame('stkn', $this->newRequest()->getTokenIDForRequest());
    }

    public function testATokenIsAcceptedOnce(): void
    {
        $token = $this->newRequest()->getNewToken();

        self::assertTrue($this->newRequest()->isCorrectToken($token));
        self::assertFalse($this->newRequest()->isCorrectToken($token), 'a used token is spent');
    }

    public function testATokenIsAcceptedOnceByASessionThatKeepsCopies(): void
    {
        $session = new CopyingSessionService();
        $token = $this->newRequest($session)->getNewToken();

        self::assertTrue($this->newRequest($session)->isCorrectToken($token));
        self::assertFalse($this->newRequest($session)->isCorrectToken($token), 'the queue without it was stored');
    }

    public function testAServiceMayKeepTheTokensItsOwnWay(): void
    {
        $service = new class extends XsrfTokenService {
            /**
             * @var list<string>
             */
            private array $calls = [];

            /**
             * @return list<string>
             */
            public function getCalls(): array
            {
                return $this->calls;
            }

            protected function issueToken(): string
            {
                $this->calls[] = __FUNCTION__;

                return parent::issueToken();
            }

            protected function getTokenQueue(): SplQueue
            {
                $this->calls[] = __FUNCTION__;

                return parent::getTokenQueue();
            }

            protected function setTokenQueue(): void
            {
                $this->calls[] = __FUNCTION__;

                parent::setTokenQueue();
            }
        };
        $service->setSessionService($this->session);

        $service->isCorrectToken($service->getNewToken());

        self::assertSame(
            ['issueToken', 'getTokenQueue', 'setTokenQueue', 'getTokenQueue', 'setTokenQueue'],
            $service->getCalls(),
        );
    }

    public function testOneRequestHandsOutOneToken(): void
    {
        $request = $this->newRequest();

        self::assertSame($request->getNewToken(), $request->getNewToken());
    }

    public function testATokenThatWasNeverIssuedIsRefused(): void
    {
        $token = $this->newRequest()->getNewToken();
        $other = $token === str_repeat('0', 32)
            ? str_repeat('1', 32)
            : str_repeat('0', 32);

        foreach (['', ' ', $other, strtoupper($token), substr($token, 0, 31), $token . '0', ' ' . $token] as $given) {
            self::assertFalse($this->newRequest()->isCorrectToken($given), '"' . $given . '"');
        }

        self::assertTrue($this->newRequest()->isCorrectToken($token), 'the refusals spent nothing');
    }

    /** A session from before the change still holds the short tokens of the time: they fail once, as a stale form. */
    public function testATokenOfTheOldLengthIsRefused(): void
    {
        $queue = new SplQueue();
        $queue->enqueue('a1b2c3');
        $this->session->setAttribute('_xsrfToken', $queue);

        self::assertFalse($this->newRequest()->isCorrectToken('a1b2c3'));
    }

    public function testOnlyTheLastFifteenTokensAreKept(): void
    {
        $tokens = [];

        for ($request = 0; $request < 16; $request++) {
            $tokens[] = $this->newRequest()->getNewToken();
        }

        self::assertFalse($this->newRequest()->isCorrectToken($tokens[0]), 'the oldest one fell out of the queue');

        foreach (array_slice($tokens, 1) as $token) {
            self::assertTrue($this->newRequest()->isCorrectToken($token));
        }
    }

    protected function setUp(): void
    {
        $this->session = new ArraySessionService();
    }

    /** A service as a request has one: its own instance over the shared session. */
    private function newRequest(?SessionServiceInterface $session = null): XsrfTokenService
    {
        $service = new XsrfTokenService();
        $service->setSessionService($session ?? $this->session);

        return $service;
    }
}
