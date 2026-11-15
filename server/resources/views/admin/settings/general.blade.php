<x-layouts.app :title="__('Settings')">
    @include('admin.settings.partials.nav')
    <x-settings.layout :section="$section">
        <form method="POST" action="{{ route('admin.settings.general') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('Instance')" :description="__('How your server presents itself.')" icon="sparkles">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="app_name" :label="__('Name')" :value="$settings->string('app.name')" required maxlength="60" />
                        <x-ui.select name="app_locale" :label="__('Default language')" :options="$locales" :value="$settings->string('app.locale')" />
                    </div>
                    <x-ui.input name="app_url" type="url" :label="__('Public URL')" :value="$settings->get('app.url') ?: url('/')" required icon="globe"
                        :hint="__('Used in emails, invitation links and by the SDKs of your apps to reach this server.')" />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Access')" :description="__('Carillon is private: people join by invitation only.')" icon="shield">
                <x-ui.toggle name="users_can_create_projects" :checked="$settings->boolean('projects.users_can_create')" :label="__('Members can create projects')"
                    :description="__('When disabled, only administrators create projects and add members to them.')" />
            </x-ui.card>

            <x-ui.card :title="__('History')" :description="__('Delivery details kept on the server.')" icon="inbox">
                <x-ui.input name="retention_days" type="number" min="1" max="3650" :label="__('Retention (days)')" :value="$settings->integer('messages.retention_days')"
                    :hint="__('Per-device delivery and click details older than this are deleted every night. Message totals are kept.')" />
                <x-slot:footer><x-ui.button>{{ __('Save settings') }}</x-ui.button></x-slot:footer>
            </x-ui.card>
        </form>
    </x-settings.layout>
</x-layouts.app>
