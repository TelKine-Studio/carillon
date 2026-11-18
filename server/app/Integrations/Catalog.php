<?php

namespace App\Integrations;

use App\Models\OneSignalSync;
use App\Models\Project;

/**
 * Everything a project can connect, as cards: the integrations of this page
 * (kind) and the features found elsewhere in Carillon (url).
 */
final class Catalog
{
    /** @return array<string, string> */
    public static function categories(): array
    {
        return [
            'analytics' => __('Analytics'),
            'cdp' => __('Customer data'),
            'database' => __('Database'),
            'storage' => __('Storage'),
            'chat' => __('Team chat'),
            'automation' => __('Automation'),
            'messaging' => __('Messaging'),
            'developer' => __('Developers'),
        ];
    }

    /**
     * @return list<array{key: string, logo?: string, name: string, category: string, directions: list<string>, description: string, icon: string, monogram: string, color: string, kind?: string, url?: string, connected?: bool}>
     */
    public static function all(Project $project): array
    {
        $settings = fn (string $section, string $anchor = '') => route('projects.settings', [$project, $section]).$anchor;

        return [
            ['key' => 'posthog', 'logo' => 'posthog', 'kind' => 'posthog', 'name' => 'PostHog', 'category' => 'analytics', 'directions' => ['out'], 'monogram' => 'Ph', 'color' => '#1D4AFF', 'icon' => 'bar-chart',
                'description' => __('Send receptions, clicks and subscriptions to PostHog (cloud or self-hosted) to analyse them with the rest of your product.')],
            ['key' => 'mixpanel', 'logo' => 'mixpanel', 'kind' => 'mixpanel', 'name' => 'Mixpanel', 'category' => 'analytics', 'directions' => ['out'], 'monogram' => 'Mx', 'color' => '#7856FF', 'icon' => 'bar-chart',
                'description' => __('The events of your notifications in your Mixpanel funnels and retention reports.')],
            ['key' => 'amplitude', 'logo' => 'amplitude', 'kind' => 'amplitude', 'name' => 'Amplitude', 'category' => 'analytics', 'directions' => ['out'], 'monogram' => 'Am', 'color' => '#1E61F0', 'icon' => 'bar-chart',
                'description' => __('Each reception, click and subscription becomes an Amplitude event of the user.')],
            ['key' => 'segment', 'logo' => 'segment', 'kind' => 'segment', 'name' => 'Segment', 'category' => 'cdp', 'directions' => ['in'], 'monogram' => 'Sg', 'color' => '#52BD94', 'icon' => 'layers',
                'description' => __('identify and track calls of Segment create your users, their tags and events in Carillon.')],
            ['key' => 'rudderstack', 'logo' => 'rudderstack', 'kind' => 'segment', 'name' => 'RudderStack', 'category' => 'cdp', 'directions' => ['in'], 'monogram' => 'Rs', 'color' => '#2E3A59', 'icon' => 'layers',
                'description' => __('Same format as Segment: use the Webhook destination of RudderStack, open source and self-hostable.')],
            ['key' => 'postgres', 'logo' => 'postgres', 'kind' => 'postgres', 'name' => 'PostgreSQL', 'category' => 'database', 'directions' => ['in'], 'monogram' => 'Pg', 'color' => '#336791', 'icon' => 'layers',
                'description' => __('A SELECT query on the database of your app keeps the tags of your users up to date, every hour or every night.')],
            ['key' => 'mysql', 'logo' => 'mysql', 'kind' => 'mysql', 'name' => 'MySQL / MariaDB', 'category' => 'database', 'directions' => ['in'], 'monogram' => 'My', 'color' => '#00758F', 'icon' => 'layers',
                'description' => __('Your users and their properties read in MySQL or MariaDB, without code in your app.')],
            ['key' => 's3', 'logo' => 's3', 'kind' => 's3', 'name' => 'Amazon S3', 'category' => 'storage', 'directions' => ['out'], 'monogram' => 'S3', 'color' => '#E25444', 'icon' => 'upload',
                'description' => __('The send history of each day, as a CSV file in your bucket: S3, Cloudflare R2, MinIO, Scaleway…')],
            ['key' => 'slack', 'logo' => 'slack', 'kind' => 'slack', 'name' => 'Slack', 'category' => 'chat', 'directions' => ['out'], 'monogram' => 'Sl', 'color' => '#4A154B', 'icon' => 'message',
                'description' => __('A message in a channel when a campaign is sent, or when a sync or an integration fails.')],
            ['key' => 'discord', 'logo' => 'discord', 'kind' => 'discord', 'name' => 'Discord', 'category' => 'chat', 'directions' => ['out'], 'monogram' => 'Dc', 'color' => '#5865F2', 'icon' => 'message',
                'description' => __('The same alerts in a channel of your Discord server.')],
            ['key' => 'automation', 'logo' => 'automation', 'name' => 'n8n, Make, Zapier', 'category' => 'automation', 'directions' => ['in', 'out'], 'monogram' => 'n8', 'color' => '#EA4B71', 'icon' => 'zap',
                'url' => $settings('webhooks'), 'connected' => $project->webhooks()->exists(),
                'description' => __('Start a workflow on each event with a webhook, and send messages from it with the REST API.')],
            ['key' => 'webhooks', 'name' => __('Webhooks'), 'category' => 'developer', 'directions' => ['out'], 'monogram' => '', 'color' => '', 'icon' => 'link',
                'url' => $settings('webhooks'), 'connected' => $project->webhooks()->exists(),
                'description' => __('Signed POST requests to your server for each event: sends, receptions, clicks, subscriptions.')],
            ['key' => 'api', 'name' => __('REST API'), 'category' => 'developer', 'directions' => ['in', 'out'], 'monogram' => '', 'color' => '', 'icon' => 'code',
                'url' => $settings('api-keys'), 'connected' => $project->apiKeys()->exists(),
                'description' => __('Compatible with the OneSignal API: notifications, users, custom events and statistics.')],
            ['key' => 'csv', 'name' => __('CSV import'), 'category' => 'developer', 'directions' => ['in'], 'monogram' => '', 'color' => '', 'icon' => 'file',
                'url' => route('projects.imports.create', $project), 'can' => 'configure',
                'description' => __('Users, devices and tags from a CSV file or a OneSignal export.')],
            ['key' => 'onesignal', 'logo' => 'onesignal', 'name' => 'OneSignal', 'category' => 'cdp', 'directions' => ['in'], 'monogram' => 'OS', 'color' => '#E54B4D', 'icon' => 'refresh',
                'url' => route('projects.imports.create', $project), 'can' => 'configure', 'connected' => OneSignalSync::query()->where('project_id', $project->id)->where('enabled', true)->exists(),
                'description' => __('Import your subscribers, then fetch the new ones every hour or every day while your app migrates.')],
            ['key' => 'email', 'name' => __('Email services'), 'category' => 'messaging', 'directions' => ['out'], 'monogram' => '', 'color' => '', 'icon' => 'mail',
                'url' => $settings('platforms', '#platform-email'), 'connected' => $project->credentials()->where('provider', 'email')->exists(),
                'description' => 'SMTP, Amazon SES, Resend, Postmark, Mailgun.'],
            ['key' => 'sms', 'logo' => 'sms', 'name' => 'SMS · Twilio, Orange', 'category' => 'messaging', 'directions' => ['out'], 'monogram' => '', 'color' => '', 'icon' => 'message',
                'url' => $settings('platforms', '#platform-sms'), 'connected' => $project->credentials()->where('provider', 'sms')->exists(),
                'description' => __('Text messages to the phone numbers of your audience.')],
            ['key' => 'whatsapp', 'logo' => 'whatsapp', 'name' => 'WhatsApp', 'category' => 'messaging', 'directions' => ['out'], 'monogram' => '', 'color' => '', 'icon' => 'chat',
                'url' => $settings('platforms', '#platform-whatsapp'), 'connected' => $project->credentials()->where('provider', 'whatsapp')->exists(),
                'description' => __('Template messages from your WhatsApp Business number (Cloud API).')],
        ];
    }

    /** Inline SVG of an official logo (Simple Icons and gilbarbara/logos, CC0). */
    public static function logo(string $name): ?string
    {
        static $cache = [];
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }
        $file = resource_path('svg/integrations/'.$name.'.svg');

        return $cache[$name] ??= is_file($file) ? str_replace('<svg ', '<svg class="size-full" aria-hidden="true" focusable="false" ', trim(file_get_contents($file))) : null;
    }

    /** Card of a kind (for the form pages). */
    public static function find(Project $project, string $key): ?array
    {
        return collect(self::all($project))->first(fn ($card) => $card['key'] === $key && isset($card['kind']));
    }
}
