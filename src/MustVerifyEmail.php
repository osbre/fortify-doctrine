<?php

namespace Laravel\Fortify;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

trait MustVerifyEmail
{
    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markEmailAsVerified(): bool
    {
        $this->emailVerifiedAt = new \DateTime();

        app(EntityManagerInterface::class)->flush();

        return true;
    }

    public function sendEmailVerificationNotification(): void
    {
        Notification::send($this, new VerifyEmail);
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}
