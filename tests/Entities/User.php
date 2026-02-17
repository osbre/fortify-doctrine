<?php

namespace Laravel\Fortify\Tests\Entities;

use Doctrine\ORM\Mapping as ORM;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Laravel\Fortify\Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements AuthenticatableContract
{
    use Authenticatable;
    use TwoFactorAuthenticatable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    public ?int $id = null;

    #[ORM\Column(type: 'string')]
    public string $name;

    #[ORM\Column(type: 'string', unique: true)]
    public string $email;

    #[ORM\Column(type: 'string')]
    public string $password;

    #[ORM\Column(name: 'remember_token', type: 'string', nullable: true)]
    public ?string $rememberToken = null;

    #[ORM\Column(name: 'two_factor_secret', type: 'text', nullable: true)]
    public ?string $twoFactorSecret = null;

    #[ORM\Column(name: 'two_factor_recovery_codes', type: 'text', nullable: true)]
    public ?string $twoFactorRecoveryCodes = null;

    #[ORM\Column(name: 'two_factor_confirmed_at', type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $twoFactorConfirmedAt = null;
}
