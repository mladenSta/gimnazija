<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SerbianMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_login_shows_serbian_error(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'pogresna'])
            ->assertSessionHasErrors(['email' => 'Pogrešna email adresa ili lozinka.']);
    }

    public function test_password_reset_email_is_in_serbian(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'Poslali smo vam email sa linkom za novu lozinku.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_starts_with((string) $mail->subject, 'Nova lozinka')
                && $mail->actionText === 'Postavi novu lozinku'
                && str_contains((string) $mail->actionUrl, '/reset-password/')
                && in_array('Link važi 60 minuta.', $mail->outroLines, true);
        });
    }
}
