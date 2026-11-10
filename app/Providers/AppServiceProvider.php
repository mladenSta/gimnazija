<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\SiteMenu;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        SiteMenu::registerCacheInvalidation();

        $this->configureMail();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Store short names in polymorphic columns (mediables.mediable_type).
        Relation::enforceMorphMap([
            'post' => Post::class,
            'page' => Page::class,
            'user' => User::class,
        ]);

        // Catch N+1 queries and mass-assignment typos during development.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Account emails in Serbian (written here so translation-package
     * updates can't change them).
     */
    protected function configureMail(): void
    {
        ResetPassword::toMailUsing(function (CanResetPassword $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Nova lozinka – '.config('app.name'))
                ->greeting('Pozdrav!')
                ->line('Dobili ste ovaj email jer je zatražena nova lozinka za vaš nalog na sajtu Gimnazije.')
                ->action('Postavi novu lozinku', $url)
                ->line("Link važi {$minutes} minuta.")
                ->line('Ako niste vi tražili novu lozinku, slobodno zanemarite ovaj email.')
                ->salutation('Srdačan pozdrav,'.PHP_EOL.config('app.name'));
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Potvrdite email adresu – '.config('app.name'))
                ->greeting('Pozdrav!')
                ->line('Kliknite na dugme ispod da potvrdite svoju email adresu.')
                ->action('Potvrdi email adresu', $url)
                ->line('Ako niste vi napravili nalog, slobodno zanemarite ovaj email.')
                ->salutation('Srdačan pozdrav,'.PHP_EOL.config('app.name'));
        });
    }
}
