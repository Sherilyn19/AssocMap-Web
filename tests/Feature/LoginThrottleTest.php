<?php

namespace Tests\Feature;

use Tests\Support\UserManagementDatabaseTestCase;
use Tests\Support\UserManagementFixture;

class LoginThrottleTest extends UserManagementDatabaseTestCase
{
    public function test_five_failures_block_correct_password_then_expiry_allows_login(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['email' => 'officer@example.test', 'password' => 'Incorrect-password'])
                ->assertSessionHas('error', 'Invalid email or password. Please try again.');
        }
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])
            ->assertRedirect('/login')->assertHeader('Retry-After')->assertSessionMissing('auth_user')
            ->assertSessionMissing('_old_input.password');
        $this->get('/login')->assertSee('Too many login attempts. Please try again in');
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/officer/dashboard');
    }

    public function test_case_and_whitespace_variations_share_the_failure_limit(): void
    {
        foreach (['officer@example.test', 'OFFICER@example.test', ' officer@example.test ', 'Officer@example.test', 'officer@EXAMPLE.TEST'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'Incorrect-password']);
        }
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertHeader('Retry-After');
    }

    public function test_success_clears_account_failures_without_blocking_another_account(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'officer@example.test', 'password' => 'Incorrect-password']);
        }
        $this->post('/login', ['email' => 'officer@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/officer/dashboard');
        $this->post('/logout');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'officer@example.test', 'password' => 'Incorrect-password'])->assertHeaderMissing('Retry-After');
        }
        $this->post('/login', ['email' => 'admin1@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/admin/dashboard');
    }

    public function test_ip_limit_covers_rotating_accounts_and_malformed_requests(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $payload = $i % 2 ? ['email' => 'missing'.$i.'@example.test', 'password' => 'Incorrect-password'] : ['email' => ['invalid']];
            $this->post('/login', $payload)->assertHeaderMissing('Retry-After');
        }
        $this->post('/login', ['email' => 'admin1@example.test', 'password' => UserManagementFixture::PASSWORD])
            ->assertHeader('Retry-After')->assertSessionMissing('auth_user');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/login', ['email' => 'admin1@example.test', 'password' => UserManagementFixture::PASSWORD])->assertRedirect('/admin/dashboard');
    }
}
