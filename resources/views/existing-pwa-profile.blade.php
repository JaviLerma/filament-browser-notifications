<x-filament::section icon="heroicon-o-bell">
    <x-slot name="heading">
        {{ __('filament-browser-notifications::profile.existing_pwa.title') }}
    </x-slot>

    <div x-data="emuniqExistingPwaProfile()" x-on:online.window="refresh()" style="display: grid; gap: .75rem;">
        <div role="status">
            <x-filament::badge color="success" icon="heroicon-m-check-circle" x-show="active && !blocked" x-text="status" x-cloak />
            <p x-show="!active || blocked" x-text="status"></p>
        </div>
        <p x-show="!installed" x-cloak>
            {{ __('filament-browser-notifications::profile.existing_pwa.install_help') }}
        </p>
        <div x-show="ready && (!blocked || active)" x-cloak>
            <x-filament::button
                type="button"
                icon="heroicon-m-bell-alert"
                x-show="!active"
                x-bind:disabled="busy"
                x-on:click="subscribe()"
            >
                {{ __('filament-browser-notifications::profile.existing_pwa.enable') }}
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-m-bell-slash"
                x-show="active"
                x-bind:disabled="busy"
                x-on:click="unsubscribe()"
            >
                {{ __('filament-browser-notifications::profile.existing_pwa.disable') }}
            </x-filament::button>
        </div>
    </div>
</x-filament::section>
