<?php

namespace Emuniq\FilamentBrowserNotifications\Tests\Feature;

use Emuniq\FilamentBrowserNotifications\Tests\TestCase;
use Filament\Panel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

final class ExistingPwaTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('browser-notifications.existing_pwa', ['panel' => 'app']);
        $app['config']->set('webpush.vapid.public_key', 'test-key');
    }

    public function test_the_application_owns_delivery_routes_and_panel_registration(): void
    {
        $this->assertFalse(Route::has('webpush.sw'));
        $this->assertFalse(Route::has('webpush.manifest'));
        $this->assertFalse(Route::has('webpush.subscribe'));
        $this->assertFalse(Event::hasListeners('eloquent.created: '.DatabaseNotification::class));
        $this->assertFalse(Panel::make()->id('app')->hasPlugin('browser-notifications'));
        $this->assertFalse(Panel::make()->id('incidents')->hasPlugin('browser-notifications'));
    }
}
