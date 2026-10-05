<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Testing;

use ampf\Testing\MemorySessionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

#[CoversClass(MemorySessionService::class)]
final class MemorySessionServiceTest extends TestCase
{
    public function testItKeepsWhatIsSetUntilItIsRemoved(): void
    {
        $session = new MemorySessionService();

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));

        $session->setAttribute('user', 42);
        $session->setAttribute('name', 'Ada');

        self::assertSame(42, $session->getAttribute('user'));
        self::assertTrue($session->hasAttribute('user'));

        $session->removeAttribute('user');

        self::assertNull($session->getAttribute('user'));
        self::assertFalse($session->hasAttribute('user'));
        self::assertSame('Ada', $session->getAttribute('name'), 'the others stay');
    }

    public function testANullValueIsNoAttribute(): void
    {
        $session = new MemorySessionService();
        $session->setAttribute('user', null);

        self::assertFalse($session->hasAttribute('user'), 'as isset() over PHP\'s session');
        self::assertNull($session->getAttribute('user'));
    }

    public function testItKeepsACopyAsPhpsSessionDoes(): void
    {
        $session = new MemorySessionService();
        $user = new stdClass();
        $user->name = 'Ada';
        $session->setAttribute('user', $user);
        $user->name = 'Bob';

        $kept = $session->getAttribute('user');

        self::assertInstanceOf(stdClass::class, $kept);
        self::assertSame('Ada', $kept->name, 'a change after setAttribute() is not kept');
        self::assertNotSame($kept, $session->getAttribute('user'), 'every read is a copy of its own');
        self::assertEquals($kept, $session->getAttribute('user'));
    }

    public function testWhatCannotBeSerializedIsRefusedAsPhpsSessionRefusesIt(): void
    {
        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Serialization of \'Closure\' is not allowed');

        new MemorySessionService()->setAttribute('callback', static fn (): int => 1);
    }

    public function testAnAttributeNeedsAName(): void
    {
        $session = new MemorySessionService();

        foreach (['', ' ', "\t"] as $key) {
            try {
                $session->setAttribute($key, 'x');
                self::fail('accepted "' . $key . '"');
            } catch (RuntimeException $e) {
                self::assertSame('A session attribute needs a name.', $e->getMessage());
            }
        }

        self::assertFalse($session->hasAttribute(''));
    }

    public function testAfterCloseWhatTheRequestWritesIsNotKept(): void
    {
        $session = new MemorySessionService();
        $session->setAttribute('kept', 'before');
        $session->setAttribute('removed', 'before');
        $session->close();

        $session->setAttribute('kept', 'after');
        $session->setAttribute('new', 'after');
        $session->removeAttribute('removed');

        self::assertSame('before', $session->getAttribute('kept'), 'what the session held is read');
        self::assertNull($session->getAttribute('new'));
        self::assertSame('before', $session->getAttribute('removed'));

        $session->open();
        $session->setAttribute('new', 'next request');
        $session->removeAttribute('removed');

        self::assertSame('next request', $session->getAttribute('new'), 'the next request writes again');
        self::assertNull($session->getAttribute('removed'));
    }

    public function testDestroyEmptiesTheSessionForTheRestOfTheRequest(): void
    {
        $session = new MemorySessionService();
        $session->setAttribute('user', 42);
        $session->destroy();

        self::assertNull($session->getAttribute('user'));

        $session->setAttribute('user', 43);

        self::assertNull($session->getAttribute('user'), 'not kept');
        self::assertSame(1, $session->id());

        $session->open();
        $session->setAttribute('user', 44);

        self::assertSame(44, $session->getAttribute('user'));
    }

    public function testRegeneratingTheIdKeepsTheData(): void
    {
        $session = new MemorySessionService();
        $session->setAttribute('user', 42);

        self::assertSame(1, $session->id());

        $session->regenerateId();

        self::assertSame(2, $session->id());
        self::assertSame(42, $session->getAttribute('user'));

        $session->regenerateId();

        self::assertSame(3, $session->id());
    }

    public function testRenewingEmptiesTheSessionUnderANewIdAndKeepsItOpen(): void
    {
        $session = new MemorySessionService();
        $session->setAttribute('user', 42);
        $session->renew();

        self::assertSame(2, $session->id());
        self::assertNull($session->getAttribute('user'));

        $session->setAttribute('message', 'Signed out.');

        self::assertSame('Signed out.', $session->getAttribute('message'), 'what follows the renewal is kept');
    }

    public function testAClosedOrDestroyedSessionGetsNoNewId(): void
    {
        foreach (['close', 'destroy'] as $end) {
            foreach (['regenerateId', 'renew'] as $change) {
                $session = new MemorySessionService();
                $session->setAttribute('user', 42);
                $session->{$end}();

                try {
                    $session->{$change}();
                    self::fail($change . '() after ' . $end . '()');
                } catch (RuntimeException $e) {
                    self::assertSame('Failed to give the session a new id.', $e->getMessage());
                }

                self::assertSame(1, $session->id(), $change . '() after ' . $end . '()');
            }
        }

        $session = new MemorySessionService();
        $session->setAttribute('user', 42);
        $session->close();

        try {
            $session->renew();
        } catch (RuntimeException) {
            self::assertSame(42, $session->getAttribute('user'), 'a refused renewal empties nothing');
        }

        $session->open();
        $session->renew();

        self::assertSame(2, $session->id(), 'the next request opens it again');
    }
}
