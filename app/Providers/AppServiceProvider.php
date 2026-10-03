<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\KycVerification;
use App\Models\StandingOrder;
use App\Models\TellerTill;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ChequeBook;
use App\Models\Loan;
use App\Models\Vault;
use App\Observers\TransactionObserver;
use App\Policies\AccountPolicy;
use App\Policies\ChequePolicy;
use App\Policies\LoanPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\KycPolicy;
use App\Policies\StandingOrderPolicy;
use App\Policies\TellerPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use App\Policies\VaultPolicy;
use App\Repositories\AccountRepository;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CustomerRepository;
use App\Repositories\UserRepository;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Email as SymfonyEmail;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File as MimeFile;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(KycVerification::class, KycPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(StandingOrder::class, StandingOrderPolicy::class);
        Gate::policy(TellerTill::class, TellerPolicy::class);
        Gate::policy(Vault::class, VaultPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(ChequeBook::class, ChequePolicy::class);

        Transaction::observe(TransactionObserver::class);

        $this->embedMailLogo();
    }

    /**
     * Every outgoing email's header logo is rendered as a fixed cid: placeholder
     * (see resources/views/vendor/mail/html/header.blade.php) because the real
     * Symfony message object doesn't exist yet at Blade-render time — Symfony
     * only assigns a Content-ID once a part is attached. So we attach the logo
     * here, right before send, and swap the placeholder for the real cid.
     */
    private function embedMailLogo(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $message = $event->message;

            if (!$message instanceof SymfonyEmail) return;

            $html = (string) $message->getHtmlBody();
            if (!str_contains($html, 'cid:nabaad-bank-logo-placeholder')) return;

            $logoPath = public_path('images/logo-email.png');
            if (!is_file($logoPath)) return;

            $part = (new DataPart(new MimeFile($logoPath), 'logo.png', 'image/png'))->asInline();
            $message->addPart($part);

            $message->html(str_replace(
                'cid:nabaad-bank-logo-placeholder',
                'cid:' . $part->getContentId(),
                $html
            ));
        });
    }
}
