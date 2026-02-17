<?php

namespace Laravel\Fortify\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Fortify;

class ConfirmTwoFactorAuthentication
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
     * Confirm the two factor authentication configuration for the user.
     *
     * @param  mixed  $user
     * @param  string  $code
     * @return void
     */
    public function __invoke($user, $code)
    {
        if (empty($user->twoFactorSecret) ||
            empty($code) ||
            ! $this->provider->verify(Fortify::currentEncrypter()->decrypt($user->twoFactorSecret), $code)) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two factor authentication code was invalid.')],
            ])->errorBag('confirmTwoFactorAuthentication');
        }

        $user->twoFactorConfirmedAt = now();

        $this->em->flush();

        TwoFactorAuthenticationConfirmed::dispatch($user);
    }
}
