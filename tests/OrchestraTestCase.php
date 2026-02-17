<?php

namespace Laravel\Fortify\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Laravel\Fortify\Features;
use Laravel\Fortify\Tests\Entities\User;
use Laravel\Fortify\Tests\Traits\DoctrineDatabaseTransactions;
use Orchestra\Testbench\TestCase;

abstract class OrchestraTestCase extends TestCase
{
    use DoctrineDatabaseTransactions;

    protected EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        if (! class_exists(\App\Entities\User::class, false)) {
            class_alias(User::class, \App\Entities\User::class);
        }

        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = app(EntityManagerInterface::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            \LaravelDoctrine\ORM\DoctrineServiceProvider::class,
            \Laravel\Fortify\FortifyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('doctrine.managers.default.connection', 'testing');
        $app['config']->set('doctrine.managers.default.paths', [
            __DIR__.'/Entities',
        ]);
        $app['config']->set('doctrine.managers.default.meta', 'attributes');

        $app['config']->set('auth.providers.users', [
            'driver' => 'doctrine',
            'model' => User::class,
        ]);
    }

    protected function withTwoFactorAuthentication($app)
    {
        $app['config']->set('fortify.features', [
            Features::twoFactorAuthentication(),
        ]);
    }

    protected function withConfirmedTwoFactorAuthentication($app)
    {
        $app['config']->set('fortify.features', [
            Features::twoFactorAuthentication(['confirm' => true]),
        ]);
    }

    protected function withoutTwoFactorAuthentication($app)
    {
        tap($app['config'], function ($config) {
            $features = $config->get('fortify.features');

            unset($features[array_search(Features::twoFactorAuthentication(), $features)]);

            $config->set('fortify.features', $features);
        });
    }

    protected function createUser(array $attributes = []): User
    {
        $user = new User();
        $user->name = $attributes['name'] ?? 'Taylor Otwell';
        $user->email = $attributes['email'] ?? 'taylor@laravel.com';
        $user->password = $attributes['password'] ?? bcrypt('secret');

        if (array_key_exists('two_factor_secret', $attributes)) {
            $user->twoFactorSecret = $attributes['two_factor_secret'];
        }
        if (array_key_exists('two_factor_recovery_codes', $attributes)) {
            $user->twoFactorRecoveryCodes = $attributes['two_factor_recovery_codes'];
        }
        if (array_key_exists('two_factor_confirmed_at', $attributes)) {
            $user->twoFactorConfirmedAt = $attributes['two_factor_confirmed_at'];
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
