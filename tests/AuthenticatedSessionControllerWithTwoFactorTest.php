<?php

namespace Laravel\Fortify\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Features;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PragmaRX\Google2FA\Google2FA;

#[DefineEnvironment('withTwoFactorAuthentication')]
class AuthenticatedSessionControllerWithTwoFactorTest extends OrchestraTestCase
{
    public function test_user_is_redirected_to_challenge_when_using_two_factor_authentication()
    {
        Event::fake();

        $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/two-factor-challenge');

        Event::assertDispatched(TwoFactorAuthenticationChallenged::class);
    }

    #[DefineEnvironment('withConfirmedTwoFactorAuthentication')]
    public function test_user_is_not_redirected_to_challenge_when_using_two_factor_authentication_that_has_not_been_confirmed_and_confirmation_is_enabled()
    {
        Event::fake();

        $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/home');
    }

    #[DefineEnvironment('withConfirmedTwoFactorAuthentication')]
    public function test_user_is_redirected_to_challenge_when_using_two_factor_authentication_that_has_been_confirmed_and_confirmation_is_enabled()
    {
        Event::fake();

        $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => 'test-secret',
            'two_factor_confirmed_at' => new \DateTime(),
        ]);

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/two-factor-challenge');
    }

    #[DefineEnvironment('withoutTwoFactorAuthentication')]
    public function test_user_can_authenticate_when_two_factor_challenge_is_disabled()
    {
        $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/home');
    }

    public function test_rehash_user_password_when_redirecting_to_two_factor_challenge_if_rehashing_on_login_is_enabled()
    {
        if (version_compare(Application::VERSION, '11.0.0', '<')) {
            $this->markTestSkipped('Only on Laravel 11 and later');
        }

        $this->app['config']->set('hashing.rehash_on_login', true);

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => Hash::make('secret', ['rounds' => 6]),
            'two_factor_secret' => 'test-secret',
        ]);

        $oldPassword = $user->password;

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/two-factor-challenge');

        $this->em->refresh($user);
        $this->assertNotSame($oldPassword, $user->password);
        $this->assertTrue(Hash::check('secret', $user->password));
    }

    public function test_does_not_rehash_user_password_when_redirecting_to_two_factor_challenge_if_rehashing_on_login_is_disabled()
    {
        if (version_compare(Application::VERSION, '11.0.0', '<')) {
            $this->markTestSkipped('Only on Laravel 11 and later');
        }

        $this->app['config']->set('hashing.rehash_on_login', false);

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => Hash::make('secret', ['rounds' => 6]),
            'two_factor_secret' => 'test-secret',
        ]);

        $oldPassword = $user->password;

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect('/two-factor-challenge');

        $this->em->refresh($user);
        $this->assertSame($oldPassword, $user->password);
    }

    public function test_two_factor_challenge_can_be_passed_via_code()
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
        ]);

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->withoutExceptionHandling()->post('/two-factor-challenge', [
            'code' => $validOtp,
        ]);

        Event::assertDispatched(ValidTwoFactorAuthenticationCodeProvided::class);

        $response->assertRedirect('/home')
            ->assertSessionMissing('login.id');
    }

    public function test_two_factor_authentication_preserves_remember_me_selection(): void
    {
        Event::fake();

        $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->withoutExceptionHandling()->post('/login', [
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
            'remember' => false,
        ]);

        $response->assertRedirect('/two-factor-challenge')
            ->assertSessionHas('login.remember', false);
    }

    public function test_two_factor_challenge_fails_for_old_otp_and_zero_window()
    {
        Event::fake();

        // Setting window to 0 should mean any old OTP is instantly invalid
        Features::twoFactorAuthentication(['window' => 0]);

        $tfaEngine = app(Google2FA::class);
        $userSecret = $tfaEngine->generateSecretKey();
        $currentTs = $tfaEngine->getTimestamp();
        $previousOtp = $tfaEngine->oathTotp($userSecret, $currentTs - 1);

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_secret' => encrypt($userSecret),
        ]);

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->withoutExceptionHandling()->post('/two-factor-challenge', [
            'code' => $previousOtp,
        ]);

        Event::assertDispatched(TwoFactorAuthenticationFailed::class);

        $response->assertRedirect('/two-factor-challenge')
                 ->assertSessionHas('login.id')
                 ->assertSessionHasErrors(['code']);
    }

    public function test_two_factor_challenge_can_be_passed_via_recovery_code()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['invalid-code', 'valid-code'])),
        ]);

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->withoutExceptionHandling()->post('/two-factor-challenge', [
            'recovery_code' => 'valid-code',
        ]);

        Event::assertDispatched(ValidTwoFactorAuthenticationCodeProvided::class);

        $response->assertRedirect('/home')
            ->assertSessionMissing('login.id');
        $this->assertNotNull(Auth::getUser());
        $this->em->refresh($user);
        $this->assertNotContains('valid-code', json_decode(decrypt($user->twoFactorRecoveryCodes), true));
    }

    public function test_two_factor_challenge_can_fail_via_recovery_code()
    {
        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['invalid-code', 'valid-code'])),
        ]);

        $response = $this->withSession([
            'login.id' => $user->id,
            'login.remember' => false,
        ])->withoutExceptionHandling()->post('/two-factor-challenge', [
            'recovery_code' => 'missing-code',
        ]);

        $response->assertRedirect('/two-factor-challenge')
            ->assertSessionHas('login.id')
            ->assertSessionHasErrors(['recovery_code']);
        $this->assertNull(Auth::getUser());
    }

    public function test_two_factor_challenge_requires_a_challenged_user()
    {
        $response = $this->withSession([])->withoutExceptionHandling()->get('/two-factor-challenge');

        $response->assertRedirect('/login');
        $this->assertNull(Auth::getUser());
    }
}
