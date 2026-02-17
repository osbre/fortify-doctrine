<?php

namespace Laravel\Fortify\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

class EnableTwoFactorAuthentication
{
    /**
     * The two factor authentication provider.
     *
     * @var \Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider
     */
    protected $provider;

    /**
     * The entity manager instance.
     *
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * Create a new action instance.
     *
     * @param  \Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider  $provider
     * @param  \Doctrine\ORM\EntityManagerInterface  $em
     * @return void
     */
    public function __construct(TwoFactorAuthenticationProvider $provider, EntityManagerInterface $em)
    {
        $this->provider = $provider;
        $this->em = $em;
    }

    /**
     * Enable two factor authentication for the user.
     *
     * @param  mixed  $user
     * @param  bool  $force
     * @return void
     */
    public function __invoke($user, $force = false)
    {
        if (empty($user->twoFactorSecret) || $force === true) {
            $secretLength = (int) config('fortify-options.two-factor-authentication.secret-length', 16);

            $user->twoFactorSecret = Fortify::currentEncrypter()->encrypt(
                $this->provider->generateSecretKey($secretLength)
            );

            $user->twoFactorRecoveryCodes = Fortify::currentEncrypter()->encrypt(
                json_encode(Collection::times(8, function () {
                    return RecoveryCode::generate();
                })->all())
            );

            $this->em->flush();

            TwoFactorAuthenticationEnabled::dispatch($user);
        }
    }
}
