<?php

namespace Laravel\Fortify\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationControllerTest extends OrchestraTestCase
{
    public function test_two_factor_authentication_can_be_enabled()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-authentication'
        );

        $response->assertStatus(200);

        Event::assertDispatched(TwoFactorAuthenticationEnabled::class);

        $this->em->refresh($user);

        $this->assertNotNull($user->twoFactorSecret);
        $this->assertNotNull($user->twoFactorRecoveryCodes);
        $this->assertNull($user->twoFactorConfirmedAt);
        $this->assertIsArray(json_decode(decrypt($user->twoFactorRecoveryCodes), true));
        $this->assertNotNull($user->twoFactorQrCodeSvg());
    }

    public function test_calling_two_factor_authentication_endpoint_will_not_overwrite_without_force_parameter()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-authentication'
        );

        $response->assertStatus(200);

        Event::assertDispatched(TwoFactorAuthenticationEnabled::class);

        $this->em->refresh($user);

        $old_value = $user->twoFactorSecret;

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-authentication'
        );

        $response->assertStatus(200);

        $this->assertNotNull($user->twoFactorSecret);
        $this->assertNotNull($user->twoFactorRecoveryCodes);
        $this->em->refresh($user);
        $this->assertEquals($old_value, $user->twoFactorSecret);
        $this->assertNull($user->twoFactorConfirmedAt);
        $this->assertIsArray(json_decode(decrypt($user->twoFactorRecoveryCodes), true));
        $this->assertNotNull($user->twoFactorQrCodeSvg());
    }

    public function test_calling_two_factor_authentication_endpoint_will_overwrite_with_force_parameter()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-authentication',
            [
                'force' => true,
            ]
        );

        $response->assertStatus(200);

        Event::assertDispatched(TwoFactorAuthenticationEnabled::class);

        $this->em->refresh($user);

        $old_value = $user->twoFactorSecret;

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-authentication',
            [
                'force' => true,
            ]
        );

        $response->assertStatus(200);

        $this->em->refresh($user);

        $this->assertNotNull($user->twoFactorSecret);
        $this->assertNotNull($user->twoFactorRecoveryCodes);
        $this->assertNotEquals($old_value, $user->twoFactorSecret);
        $this->assertNull($user->twoFactorConfirmedAt);
        $this->assertIsArray(json_decode(decrypt($user->twoFactorRecoveryCodes), true));
        $this->assertNotNull($user->twoFactorQrCodeSvg());
    }

    public function test_two_factor_authentication_secret_key_can_be_retrieved()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => encrypt('foo'),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->getJson(
            '/user/two-factor-secret-key'
        );

        $response->assertStatus(200);

        $this->assertEquals('foo', $response->original['secretKey']);
    }

    #[DefineEnvironment('withConfirmedTwoFactorAuthentication')]
    public function test_two_factor_authentication_can_be_confirmed()
    {
        Event::fake();

        $tfaEngine = app(Google2FA::class);
        $userSecret = $tfaEngine->generateSecretKey();
        $validOtp = $tfaEngine->getCurrentOtp($userSecret);

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => encrypt($userSecret),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/confirmed-two-factor-authentication', ['code' => $validOtp],
        );

        $response->assertStatus(200);

        Event::assertDispatched(TwoFactorAuthenticationConfirmed::class);

        $this->em->refresh($user);

        $this->assertNotNull($user->twoFactorConfirmedAt);
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());

        // Ensure two factor authentication not considered enabled if not confirmed...
        $user->twoFactorConfirmedAt = null;
        $this->em->flush();

        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
    }

    #[DefineEnvironment('withConfirmedTwoFactorAuthentication')]
    public function test_two_factor_authentication_can_not_be_confirmed_with_invalid_code()
    {
        Event::fake();

        $tfaEngine = app(Google2FA::class);
        $userSecret = $tfaEngine->generateSecretKey();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => encrypt($userSecret),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->withExceptionHandling()->actingAs($user)->postJson(
            '/user/confirmed-two-factor-authentication', ['code' => 'invalid-otp'],
        );

        $response->assertStatus(422);

        Event::assertNotDispatched(TwoFactorAuthenticationConfirmed::class);

        $this->em->refresh($user);

        $this->assertNull($user->twoFactorConfirmedAt);
    }

    public function test_two_factor_authentication_can_be_disabled()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => encrypt('foo'),
            'two_factor_recovery_codes' => encrypt(json_encode([])),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->deleteJson(
            '/user/two-factor-authentication'
        );

        $response->assertStatus(200);

        Event::assertDispatched(TwoFactorAuthenticationDisabled::class);

        $this->em->refresh($user);

        $this->assertNull($user->twoFactorSecret);
        $this->assertNull($user->twoFactorRecoveryCodes);
    }
}
