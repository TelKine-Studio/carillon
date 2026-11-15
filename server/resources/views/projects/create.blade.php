<x-layouts.app :title="__('New project')">
    <div class="mx-auto max-w-xl">
        <x-ui.page-header :title="__('New project')" :description="__('A project groups the subscribers, push keys and messages of one app. Use the same project for the iOS, Android and web versions of an app.')" />

        <form method="POST" action="{{ route('projects.store') }}" enctype="multipart/form-data">
            @csrf
            <x-ui.card>
                <div class="space-y-6">
                    <x-ui.input name="name" :label="__('Name')" required maxlength="80" autofocus :placeholder="__('e.g. My shop')" />
                    <x-ui.file name="icon" accept="image/png,image/jpeg,image/webp" :label="__('Icon')" :hint="__('Shown in the dashboard. PNG, JPEG or WebP, 1 MB maximum.')" optional />
                </div>
                <x-slot:footer>
                    <x-ui.button variant="secondary" :href="route('projects.index')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="plus">{{ __('Create the project') }}</x-ui.button>
                </x-slot:footer>
            </x-ui.card>
        </form>
    </div>
</x-layouts.app>
