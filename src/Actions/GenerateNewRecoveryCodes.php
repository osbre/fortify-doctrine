<?php

namespace Laravel\Fortify\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Collection;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

class GenerateNewRecoveryCodes
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
     * Generate new recovery codes for the user.
     *
     * @param  mixed  $user
     * @return void
     */
    public function __invoke($user)
    {
        $user->twoFactorRecoveryCodes = Fortify::currentEncrypter()->encrypt(
            json_encode(Collection::times(8, function () {
                return RecoveryCode::generate();
            })->all())
        );

        $this->em->flush();

        RecoveryCodesGenerated::dispatch($user);
    }
}
