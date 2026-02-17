<?php

namespace Laravel\Fortify\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\RecoveryCodesGenerated;

class RecoveryCodeControllerTest extends OrchestraTestCase
{
    public function test_new_recovery_codes_can_be_generated()
    {
        Event::fake();

        $user = $this->createUser([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => bcrypt('secret'),
        ]);

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson(
            '/user/two-factor-recovery-codes'
        );

        $response->assertStatus(200);

        Event::assertDispatched(RecoveryCodesGenerated::class);

        $this->em->refresh($user);

        $this->assertNotNull($user->twoFactorRecoveryCodes);
        $this->assertIsArray(json_decode(decrypt($user->twoFactorRecoveryCodes), true));
    }
}
