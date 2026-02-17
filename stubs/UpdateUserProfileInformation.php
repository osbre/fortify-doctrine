<?php

namespace App\Actions\Fortify;

use App\Entities\User;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        protected EntityManagerInterface $em,
    ) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        $user->name = $input['name'];

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $user->email = $input['email'];
            $user->emailVerifiedAt = null;
            $this->em->flush();
            $user->sendEmailVerificationNotification();
        } else {
            $user->email = $input['email'];
            $this->em->flush();
        }
    }
}
