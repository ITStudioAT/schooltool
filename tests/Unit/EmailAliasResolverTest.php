<?php

use App\Providers\AppServiceProvider;
use App\Services\EmailAliasResolver;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

uses(TestCase::class);

test('it resolves configured virtual email addresses case insensitively', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
        ],
    ]);

    $resolver = new EmailAliasResolver;

    expect($resolver->resolve('A@A.AT'))->toBe('kron@naturwelt.at')
        ->and($resolver->resolve('other@example.at'))->toBe('other@example.at');
});

test('it rewrites outgoing message recipients while preserving names', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
            'cc@a.at' => 'real-cc@example.at',
            'bcc@a.at' => 'real-bcc@example.at',
        ],
    ]);

    $message = (new Email)
        ->to(new Address('a@a.at', 'Virtuelle Adresse'))
        ->cc('cc@a.at')
        ->bcc('bcc@a.at');

    (new EmailAliasResolver)->rewriteMessageRecipients($message);

    expect($message->getTo()[0]->getAddress())->toBe('kron@naturwelt.at')
        ->and($message->getTo()[0]->getName())->toBe('Virtuelle Adresse')
        ->and($message->getCc()[0]->getAddress())->toBe('real-cc@example.at')
        ->and($message->getBcc()[0]->getAddress())->toBe('real-bcc@example.at');
});

test('app service provider rewrites recipients before mail is sent', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
        ],
    ]);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    $message = (new Email)->to('a@a.at');

    Event::dispatch(new MessageSending($message));

    expect($message->getTo()[0]->getAddress())->toBe('kron@naturwelt.at');
});

test('it rewrites on demand notification mail routes', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
            'team@a.at' => 'team-real@example.at',
        ],
    ]);

    $notifiable = new AnonymousNotifiable;
    $notifiable->route('mail', ['a@a.at' => 'Virtuelle Adresse', 'team@a.at']);

    (new EmailAliasResolver)->rewriteNotificationMailRoute($notifiable);

    expect($notifiable->routes['mail'])->toBe([
        'kron@naturwelt.at' => 'Virtuelle Adresse',
        'team-real@example.at',
    ]);
});

test('app service provider rewrites queued notification mail routes before delivery', function () {
    config([
        'schooltool.email_aliases' => [
            'a@a.at' => 'kron@naturwelt.at',
        ],
    ]);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    $notifiable = new AnonymousNotifiable;
    $notifiable->route('mail', 'a@a.at');

    Event::dispatch(new NotificationSending($notifiable, new stdClass, 'mail'));

    expect($notifiable->routes['mail'])->toBe('kron@naturwelt.at');
});
