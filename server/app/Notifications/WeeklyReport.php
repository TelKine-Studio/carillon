<?php

namespace App\Notifications;

use App\Models\CustomEvent;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Monday summary of the last 7 days of the projects of a person. */
class WeeklyReport extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<int> $projectIds */
    public function __construct(public array $projectIds) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Figures of a project over a week and the one before.
     *
     * @return array<string, mixed>
     */
    public static function figures(Project $project, CarbonImmutable $end): array
    {
        $start = $end->subDays(7);
        $week = function (CarbonImmutable $from, CarbonImmutable $to) use ($project) {
            $messages = $project->messages()->whereNotIn('status', ['draft', 'canceled'])->where('is_test', false)->whereBetween('created_at', [$from, $to]);

            return [
                'messages' => (clone $messages)->count(),
                'delivered' => (int) (clone $messages)->sum('successful'),
                'clicked' => (int) (clone $messages)->sum('clicked'),
                'new_devices' => $project->subscriptions()->whereBetween('created_at', [$from, $to])->count(),
                'conversions' => CustomEvent::query()->where('project_id', $project->id)->whereNotNull('message_id')->whereBetween('occurred_at', [$from, $to])->count(),
            ];
        };

        return [
            'now' => $week($start, $end),
            'before' => $week($start->subDays(7), $start),
            'top' => $project->messages()->whereBetween('created_at', [$start, $end])->where('successful', '>=', 10)->orderByRaw('clicked * 1.0 / successful desc')->first(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $end = CarbonImmutable::now();
        $n = fn ($value) => number_format((int) $value, 0, ',', ' ');
        $change = function (int $now, int $before) {
            if ($before === 0) {
                return '';
            }
            $percent = ($now - $before) / $before * 100;

            return ' ('.($percent >= 0 ? '+' : '−').number_format(abs($percent), 0, ',', ' ').' %)';
        };

        $mail = (new MailMessage)
            ->subject(__('Your week on :app', ['app' => config('app.name')]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Here is what happened in the last 7 days.'));

        /** @var Collection<int, Project> $projects */
        $projects = Project::query()->whereIn('id', $this->projectIds)->orderBy('name')->get();
        foreach ($projects as $project) {
            $figures = self::figures($project, $end);
            [$now, $before] = [$figures['now'], $figures['before']];
            $rate = $now['delivered'] ? number_format($now['clicked'] / $now['delivered'] * 100, 1, ',', ' ').' %' : '—';

            $mail->line('**'.$project->name.'**')
                ->line(implode("  \n", [
                    __(':count messages sent', ['count' => $n($now['messages'])]),
                    __(':count notifications delivered', ['count' => $n($now['delivered'])]).$change($now['delivered'], $before['delivered']),
                    __(':count clicks (rate :rate)', ['count' => $n($now['clicked']), 'rate' => $rate]).$change($now['clicked'], $before['clicked']),
                    __(':count new devices', ['count' => $n($now['new_devices'])]).$change($now['new_devices'], $before['new_devices']),
                    __(':count conversions after a message', ['count' => $n($now['conversions'])]),
                ]));
            if ($figures['top']) {
                $mail->line(__('Best message: “:name”, :rate of clicks.', ['name' => $figures['top']->label(), 'rate' => number_format($figures['top']->clicked / max(1, $figures['top']->successful) * 100, 1, ',', ' ').' %']));
            }
        }

        return $mail
            ->action(__('Open the statistics'), $projects->count() === 1 ? route('projects.stats', $projects->first()) : route('projects.index'))
            ->line(__('You can stop this summary in your profile.'));
    }
}
