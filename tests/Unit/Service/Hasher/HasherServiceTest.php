<?php

declare(strict_types=1);

namespace ampf\Tests\Unit\Service\Hasher;

use ampf\Service\Hasher\HasherService;
use ampf\Tests\Support\CheapHasherService;
use ampf\Tests\Support\CountingHasherService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(HasherService::class)]
final class HasherServiceTest extends TestCase
{
    /** The hash a check without anything to compare verifies against. */
    private const string DUMMY = '$2y$12$7bXzdUEuvvooZkWPLBbTCux4VdVOJfTv2uLCS2ysoHhDOgVFRE3Q2';

    /**
     * @return array<string, array{string}>
     */
    public static function provideStoredValuesThatAreNoBcryptHash(): array
    {
        return [
            'empty' => [''],
            'plain text' => ['secret'],
            'an md5 digest' => [md5('secret')],
            'a sha-256 digest' => [hash('sha256', 'secret')],
            'a bcrypt hash cut short' => [substr(self::bcrypt('secret'), 0, 59)],
            'a bcrypt hash with a character too many' => [self::bcrypt('secret') . 'x'],
            'a bcrypt hash with a line feed after it' => [self::bcrypt('secret') . "\n"],
            'sixty characters of something else' => [str_repeat('x', 60)],
            'a bcrypt prefix with an impossible cost' => ['$2y$99$' . substr(self::bcrypt('secret'), 7)],
            'an argon2 hash' => [password_hash('secret', PASSWORD_ARGON2ID)],
            'a bcrypt hash after something else' => ['x' . self::bcrypt('secret')],
        ];
    }

    private static function bcrypt(string $string): string
    {
        return password_hash($string, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    public function testAHashIsBcryptAtCostTwelveAndChecksItsStringOnly(): void
    {
        $hasher = new CountingHasherService();
        $hash = $hasher->hash('correct horse');

        self::assertSame(60, strlen($hash));
        self::assertStringStartsWith('$2y$12$', $hash);
        self::assertTrue($hasher->check('correct horse', $hash));
        self::assertFalse($hasher->check('correct horsf', $hash));
        self::assertFalse($hasher->needsRehash($hash));
    }

    public function testABlankStringIsAWrongOneAfterAFullCheck(): void
    {
        $hasher = new CountingHasherService();
        $hash = self::bcrypt('secret');

        foreach (['', ' ', "\t\n"] as $blank) {
            self::assertFalse($hasher->check($blank, $hash), json_encode($blank, JSON_THROW_ON_ERROR));
        }

        self::assertSame(
            [self::DUMMY, self::DUMMY, self::DUMMY],
            $hasher->getVerifiedHashes(),
            'one check\'s time each',
        );
    }

    #[DataProvider('provideStoredValuesThatAreNoBcryptHash')]
    public function testAStoredValueThatIsNoBcryptHashIsAWrongPasswordAfterAFullCheck(string $stored): void
    {
        $hasher = new CountingHasherService();

        // Even the stored value itself does not open it: it is not compared as text
        self::assertFalse($hasher->check('secret', $stored));
        self::assertFalse($hasher->check($stored === '' ? 'x' : $stored, $stored));
        self::assertSame(
            [self::DUMMY, self::DUMMY],
            $hasher->getVerifiedHashes(),
            'each refusal took one check\'s time',
        );
        self::assertSame([$stored, $stored], $hasher->getCheckedShapes());
        self::assertTrue($hasher->needsRehash($stored));
    }

    public function testAHashOfALowerCostChecksAndNeedsARehash(): void
    {
        $hasher = new HasherService();
        $old = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 10]);

        self::assertTrue($hasher->check('secret', $old));
        self::assertTrue($hasher->needsRehash($old));
        self::assertFalse($hasher->needsRehash($hasher->hash('secret')));
    }

    public function testAStringWithANulByteIsNotHashed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bcrypt cannot hash a string with a NUL byte.');

        new HasherService()->hash("secret\0tail");
    }

    public function testABlankStringIsNotHashed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A blank string is no secret to hash.');

        new HasherService()->hash(" \n");
    }

    public function testAnApplicationsCostIsTheCostOfItsHashes(): void
    {
        $hasher = new CheapHasherService();
        $hash = $hasher->hash('secret');

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertTrue($hasher->check('secret', $hash));
        self::assertFalse($hasher->needsRehash($hash));
        self::assertTrue($hasher->needsRehash(password_hash('secret', PASSWORD_BCRYPT, ['cost' => 12])));
    }

    public function testEveryCallWaitsAMillisecondAtLeast(): void
    {
        $hasher = new CountingHasherService();
        $hasher->check('secret', self::bcrypt('secret'));
        $hasher->hash('secret');

        self::assertCount(2, $hasher->getSleeps(), 'a check and a hash wait once each');

        foreach ($hasher->getSleeps() as $nanoseconds) {
            self::assertGreaterThanOrEqual(1_000_000, $nanoseconds);
        }
    }

    public function testAvoidingATimingAttackSpendsOneCheck(): void
    {
        $hasher = new CountingHasherService();
        $hasher->avoidTimingAttack('anything');

        self::assertSame(1, $hasher->getVerifications());
    }
}
