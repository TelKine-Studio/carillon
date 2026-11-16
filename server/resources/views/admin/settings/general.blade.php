<x-layouts.app :title="__('Settings')">
    @include('admin.settings.partials.nav')
    <x-settings.layout :section="$section">
        <form method="POST" action="{{ route('admin.settings.general') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-settings.section :title="__('Instance')" :description="__('How your server presents itself.')" icon="sparkles" :fields="['app_name', 'app_locale', 'app_url']">
                <x-slot:summary>
                    <x-settings.summary>
                        <x-settings.value :label="__('Name')" :value="$settings->string('app.name')" />
                        <x-settings.value :label="__('Default language')" :value="$locales[$settings->string('app.locale')] ?? $settings->string('app.locale')" />
                        <x-settings.value :label="__('Public URL')" :value="$settings->get('app.url') ?: url('/')" mono wide />
                    </x-settings.summary>
                </x-slot:summary>
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="app_name" :label="__('Name')" :value="$settings->string('app.name')" required maxlength="60" />
                        <x-ui.select name="app_locale" :label="__('Default language')" :options="$locales" :value="$settings->string('app.locale')" />
                    </div>
                    <x-ui.input name="app_url" type="url" :label="__('Public URL')" :value="$settings->get('app.url') ?: url('/')" required icon="globe"
                        :hint="__('Used in emails, invitation links and by the SDKs of your apps to reach this server.')" />
                </div>
            </x-settings.section>

            <x-settings.section :title="__('Access')" :description="__('Carillon is private: people join by invitation only.')" icon="shield" :fields="['users_can_create_projects']">
                <x-slot:summary>
                    <x-settings.summary :columns="1">
                        <x-settings.value :label="__('Members can create projects')" :on="$settings->boolean('projects.users_can_create')" :value="__('Yes')" :empty="__('No, administrators only')" />
                    </x-settings.summary>
                </x-slot:summary>
                <x-ui.toggle name="users_can_create_projects" :checked="$settings->boolean('projects.users_can_create')" :label="__('Members can create projects')"
                    :description="__('When disabled, only administrators create projects and add members to them.')" />
            </x-settings.section>

            <x-settings.section :title="__('History')" :description="__('How long the details per device (delivered, received, clicked) are kept. Messages, their contents and totals stay for the reports.')" icon="inbox" :fields="['retention_days']">
                <x-slot:summary>
                    <x-settings.summary :columns="1">
                        <x-settings.value :label="__('Retention')" :value="trans_choice(':count day|:count days', $settings->integer('messages.retention_days'))" />
                    </x-settings.summary>
                </x-slot:summary>
                <x-ui.input name="retention_days" type="number" min="1" max="3650" :label="__('Retention (days)')" :value="$settings->integer('messages.retention_days')"
                    :hint="__('Per-device delivery and click details older than this are deleted every night. Message totals are kept.')" />
            </x-settings.section>
        </form>
    </x-settings.layout>
</x-layouts.app>
