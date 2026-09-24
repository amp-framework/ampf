<?php

declare(strict_types=1);

namespace ampfTest\Services;

use ampf\services\hasher\impl\DefaultHasherService;
use ampfTest\Support\CountingHasherService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \ampf\services\hasher\impl\DefaultHasherService
 */
final class DefaultHasherServiceTest extends TestCase
{
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
            $before = $hasher->getVerifications();
            self::assertFalse($hasher->check($blank, $hash), json_encode($blank, JSON_THROW_ON_ERROR));
            self::assertSame($before + 1, $hasher->getVerifications(), 'the time of one check all the same');
        }
    }

    #[DataProvider('provideStoredValuesThatAreNoBcryptHash')]
    public function testAStoredValueThatIsNoBcryptHashIsAWrongPasswordAfterAFullCheck(string $stored): void
    {
        $hasher = new CountingHasherService();

        // Even the stored value itself does not open it: it is not compared as text
        self::assertFalse($hasher->check('secret', $stored));
        self::assertFalse($hasher->check($stored === '' ? 'x' : $stored, $stored));
        self::assertSame(2, $hasher->getVerifications(), 'each refusal took one check\'s time');
        self::assertTrue($hasher->needsRehash($stored));
    }

    public function testAHashOfALowerCostChecksAndNeedsARehash(): void
    {
        $hasher = new DefaultHasherService();
        $old = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 10]);

        self::assertTrue($hasher->check('secret', $old));
        self::assertTrue($hasher->needsRehash($old));
        self::assertFalse($hasher->needsRehash($hasher->hash('secret')));
    }

    public function testAStringWithANulByteIsNotHashed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DefaultHasherService()->hash("secret\0tail");
    }

    public function testAvoidingATimingAttackSpendsOneCheck(): void
    {
        $hasher = new CountingHasherService();
        $hasher->avoidTimingAttack('anything');

        self::assertSame(1, $hasher->getVerifications());
    }
}
