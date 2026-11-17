{{-- Weekly cohorts: share of the people of each week back in the following weeks. --}}
@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn (?float $v) => $v === null ? '—' : number_format($v, $v < 10 ? 1 : 0, ',', ' ').' %';
@endphp
        @if (collect($retention)->sum('size') === 0)
            <p class="px-5 pb-5 text-sm text-zinc-500 sm:px-6">{{ __('No new people in the last 8 weeks yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="text-xs text-zinc-500">
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <th class="px-5 py-2.5 text-left font-medium sm:px-6">{{ __('Week of arrival') }}</th>
                            <th class="px-3 py-2.5 text-right font-medium">{{ __('People') }}</th>
                            @foreach (range(0, 7) as $week)
                                <th class="px-1 py-2.5 text-center font-medium">{{ $week === 0 ? __('Week 0') : __('W:n', ['n' => $week]) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($retention as $row)
                            <tr>
                                <td class="px-5 py-1 whitespace-nowrap text-zinc-700 sm:px-6 dark:text-zinc-300">{{ $row['start']->isoFormat('D MMM') }}</td>
                                <td class="px-3 py-1 text-right tabular-nums text-zinc-900 dark:text-white">{{ $n($row['size']) }}</td>
                                @foreach ($row['weeks'] as $week => $value)
                                    <td class="p-0.5">
                                        @if ($value === null || $row['size'] === 0)
                                            <span class="block h-9 rounded-md bg-zinc-50 dark:bg-white/[.03]"></span>
                                        @else
                                            {{-- One hue, darker = more people back; the text keeps the ink colors. --}}
                                            <span class="retention-cell flex h-9 items-center justify-center rounded-md text-xs font-medium tabular-nums {{ $value >= 70 ? 'text-white' : 'text-zinc-800 dark:text-zinc-100' }}"
                                                style="--level: {{ round(8 + min(100, $value) * 0.84) }}%"
                                                title="{{ __(':value of the people first seen the week of :date were active :weeks week(s) later', ['value' => $pct($value), 'date' => $row['start']->isoFormat('D MMM'), 'weeks' => $week]) }}">{{ $pct($value) }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-5 pt-3 pb-5 text-xs text-zinc-500 sm:px-6">{{ __('Activity is counted from the app sessions (Carillon SDK), the clicks and, for devices still on OneSignal, the nightly sync.') }}</p>
        @endif
