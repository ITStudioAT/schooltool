<?php

use App\Enums\RouteResult;
use App\Enums\TwoFaResult;
use App\Enums\VerificationResult;

describe('RouteResult Enum', function () {
    it('has all expected cases', function () {
        $cases = RouteResult::cases();

        expect($cases)->toHaveCount(4)
            ->and(array_column($cases, 'name'))->toContain('ALLOWED', 'NOT_ALLOWED', 'NOT_EXISTS', 'NOT_FOUND');
    });

    it('has correct string values', function () {
        expect(RouteResult::ALLOWED->value)->toBe('allowed')
            ->and(RouteResult::NOT_ALLOWED->value)->toBe('not_allowed')
            ->and(RouteResult::NOT_EXISTS->value)->toBe('not_exists')
            ->and(RouteResult::NOT_FOUND->value)->toBe('not_found');
    });

    it('can be instantiated from string value', function () {
        expect(RouteResult::from('allowed'))->toBe(RouteResult::ALLOWED)
            ->and(RouteResult::from('not_allowed'))->toBe(RouteResult::NOT_ALLOWED)
            ->and(RouteResult::from('not_exists'))->toBe(RouteResult::NOT_EXISTS)
            ->and(RouteResult::from('not_found'))->toBe(RouteResult::NOT_FOUND);
    });

    it('throws exception for invalid value', function () {
        RouteResult::from('invalid');
    })->throws(ValueError::class);

    it('can use tryFrom for safe value retrieval', function () {
        expect(RouteResult::tryFrom('allowed'))->toBe(RouteResult::ALLOWED)
            ->and(RouteResult::tryFrom('invalid'))->toBeNull();
    });

    it('can be compared', function () {
        $result1 = RouteResult::ALLOWED;
        $result2 = RouteResult::ALLOWED;
        $result3 = RouteResult::NOT_ALLOWED;

        expect($result1 === $result2)->toBeTrue()
            ->and($result1 === $result3)->toBeFalse();
    });

    it('can be used in match expressions', function () {
        $result = RouteResult::ALLOWED;

        $message = match ($result) {
            RouteResult::ALLOWED => 'access granted',
            RouteResult::NOT_ALLOWED => 'access denied',
            RouteResult::NOT_EXISTS => 'route not exists',
            RouteResult::NOT_FOUND => 'route not found',
        };

        expect($message)->toBe('access granted');
    });
});

describe('TwoFaResult Enum', function () {
    it('has all expected cases', function () {
        $cases = TwoFaResult::cases();

        expect($cases)->toHaveCount(7)
            ->and(array_column($cases, 'name'))->toContain(
                'TWO_FA_DELETE',
                'TWO_FA_OK',
                'TWO_FA_EMAIL_MUST_BE_VERIFIED',
                'TWO_FA_EMAIL_IS_NEW',
                'TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL',
                'TWO_FA_ERROR',
                'TWO_FA_SET'
            );
    });

    it('has correct string values', function () {
        expect(TwoFaResult::TWO_FA_DELETE->value)->toBe('TWO_FA_DELETE')
            ->and(TwoFaResult::TWO_FA_OK->value)->toBe('TWO_FA_OK')
            ->and(TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED->value)->toBe('TWO_FA_EMAIL_MUST_BE_VERIFIED')
            ->and(TwoFaResult::TWO_FA_EMAIL_IS_NEW->value)->toBe('TWO_FA_EMAIL_IS_NEW')
            ->and(TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL->value)->toBe('TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL')
            ->and(TwoFaResult::TWO_FA_ERROR->value)->toBe('TWO_FA_ERROR')
            ->and(TwoFaResult::TWO_FA_SET->value)->toBe('TWO_FA_SET');
    });

    it('can be instantiated from string value', function () {
        expect(TwoFaResult::from('TWO_FA_OK'))->toBe(TwoFaResult::TWO_FA_OK)
            ->and(TwoFaResult::from('TWO_FA_DELETE'))->toBe(TwoFaResult::TWO_FA_DELETE)
            ->and(TwoFaResult::from('TWO_FA_ERROR'))->toBe(TwoFaResult::TWO_FA_ERROR);
    });

    it('throws exception for invalid value', function () {
        TwoFaResult::from('INVALID_STATUS');
    })->throws(ValueError::class);

    it('can use tryFrom for safe value retrieval', function () {
        expect(TwoFaResult::tryFrom('TWO_FA_OK'))->toBe(TwoFaResult::TWO_FA_OK)
            ->and(TwoFaResult::tryFrom('INVALID'))->toBeNull();
    });

    it('can be compared', function () {
        $result1 = TwoFaResult::TWO_FA_OK;
        $result2 = TwoFaResult::TWO_FA_OK;
        $result3 = TwoFaResult::TWO_FA_ERROR;

        expect($result1 === $result2)->toBeTrue()
            ->and($result1 === $result3)->toBeFalse();
    });

    it('can be used in match expressions', function () {
        $result = TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED;

        $message = match ($result) {
            TwoFaResult::TWO_FA_DELETE => 'delete 2fa',
            TwoFaResult::TWO_FA_OK => '2fa ok',
            TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED => 'must verify email',
            TwoFaResult::TWO_FA_EMAIL_IS_NEW => 'new email',
            TwoFaResult::TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL => 'emails must differ',
            TwoFaResult::TWO_FA_ERROR => 'error',
            TwoFaResult::TWO_FA_SET => '2fa set',
        };

        expect($message)->toBe('must verify email');
    });

    it('distinguishes between email verification states', function () {
        $isNew = TwoFaResult::TWO_FA_EMAIL_IS_NEW;
        $mustVerify = TwoFaResult::TWO_FA_EMAIL_MUST_BE_VERIFIED;
        $ok = TwoFaResult::TWO_FA_OK;

        expect($isNew)->not->toBe($mustVerify)
            ->and($isNew)->not->toBe($ok)
            ->and($mustVerify)->not->toBe($ok);
    });
});

describe('VerificationResult Enum', function () {
    it('has all expected cases', function () {
        $cases = VerificationResult::cases();

        expect($cases)->toHaveCount(3)
            ->and(array_column($cases, 'name'))->toContain(
                'VERIFICATION_SUCCESS',
                'EMAIL_SENT',
                'ALREADY_VERIFIED'
            );
    });

    it('has correct string values', function () {
        expect(VerificationResult::VERIFICATION_SUCCESS->value)->toBe('VERIFICATION_SUCCESS')
            ->and(VerificationResult::EMAIL_SENT->value)->toBe('EMAIL_SENT')
            ->and(VerificationResult::ALREADY_VERIFIED->value)->toBe('ALREADY_VERIFIED');
    });

    it('can be instantiated from string value', function () {
        expect(VerificationResult::from('VERIFICATION_SUCCESS'))->toBe(VerificationResult::VERIFICATION_SUCCESS)
            ->and(VerificationResult::from('EMAIL_SENT'))->toBe(VerificationResult::EMAIL_SENT)
            ->and(VerificationResult::from('ALREADY_VERIFIED'))->toBe(VerificationResult::ALREADY_VERIFIED);
    });

    it('throws exception for invalid value', function () {
        VerificationResult::from('INVALID_STATUS');
    })->throws(ValueError::class);

    it('can use tryFrom for safe value retrieval', function () {
        expect(VerificationResult::tryFrom('EMAIL_SENT'))->toBe(VerificationResult::EMAIL_SENT)
            ->and(VerificationResult::tryFrom('INVALID'))->toBeNull();
    });

    it('can be compared', function () {
        $result1 = VerificationResult::VERIFICATION_SUCCESS;
        $result2 = VerificationResult::VERIFICATION_SUCCESS;
        $result3 = VerificationResult::EMAIL_SENT;

        expect($result1 === $result2)->toBeTrue()
            ->and($result1 === $result3)->toBeFalse();
    });

    it('can be used in match expressions', function () {
        $result = VerificationResult::EMAIL_SENT;

        $message = match ($result) {
            VerificationResult::VERIFICATION_SUCCESS => 'verified successfully',
            VerificationResult::EMAIL_SENT => 'verification email sent',
            VerificationResult::ALREADY_VERIFIED => 'already verified',
        };

        expect($message)->toBe('verification email sent');
    });

    it('distinguishes between success and already verified', function () {
        $success = VerificationResult::VERIFICATION_SUCCESS;
        $alreadyVerified = VerificationResult::ALREADY_VERIFIED;

        expect($success)->not->toBe($alreadyVerified);
    });
});

describe('Enum Integration Tests', function () {
    it('all enums are backed by string values', function () {
        expect(RouteResult::ALLOWED)->toBeInstanceOf(\BackedEnum::class)
            ->and(TwoFaResult::TWO_FA_OK)->toBeInstanceOf(\BackedEnum::class)
            ->and(VerificationResult::VERIFICATION_SUCCESS)->toBeInstanceOf(\BackedEnum::class);
    });

    it('all enum values can be serialized to JSON', function () {
        $data = [
            'route' => RouteResult::ALLOWED->value,
            'twofa' => TwoFaResult::TWO_FA_OK->value,
            'verification' => VerificationResult::VERIFICATION_SUCCESS->value,
        ];

        $json = json_encode($data);

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toBe($data);
    });

    it('enums can be used in arrays as keys', function () {
        $statusMessages = [
            RouteResult::ALLOWED->value => 'Access granted',
            RouteResult::NOT_ALLOWED->value => 'Access denied',
        ];

        expect($statusMessages[RouteResult::ALLOWED->value])->toBe('Access granted')
            ->and($statusMessages[RouteResult::NOT_ALLOWED->value])->toBe('Access denied');
    });
});
