<?php

namespace Laravel\Fortify\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Fortify;

class DisableTwoFactorAuthentication
{
    /**
     * The entity manager instance.
     *
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * Create a new action instance.
     *
     * @param  \Doctrine\ORM\EntityManagerInterface  $em
     * @return void
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Disable two factor authentication for the user.
     *
     * @param  mixed  $user
     * @return void
     */
    public function __invoke($user)
    {
        if (! is_null($user->twoFactorSecret) ||
            ! is_null($user->twoFactorRecoveryCodes) ||
            ! is_null($user->twoFactorConfirmedAt)) {
            $user->twoFactorSecret = null;
            $user->twoFactorRecoveryCodes = null;

            if (Fortify::confirmsTwoFactorAuthentication() || ! is_null($user->twoFactorConfirmedAt)) {
                $user->twoFactorConfirmedAt = null;
            }

            $this->em->flush();

            TwoFactorAuthenticationDisabled::dispatch($user);
        }
    }
}
