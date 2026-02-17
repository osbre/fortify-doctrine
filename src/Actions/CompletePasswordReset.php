<?php

namespace Laravel\Fortify\Actions;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Str;

class CompletePasswordReset
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
     * Complete the password reset process for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\StatefulGuard  $guard
     * @param  mixed  $user
     * @return void
     */
    public function __invoke(StatefulGuard $guard, $user)
    {
        $user->rememberToken = Str::random(60);

        $this->em->flush();

        event(new PasswordReset($user));
    }
}
