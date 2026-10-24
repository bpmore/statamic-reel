{{--
    Reel's page under Tools: every video and audio asset and where it stands.

    Compiled as a Vue template, not printed as HTML: Statamic hands a
    utility view to `DynamicHtmlRenderer`, which does
    `defineComponent({ template: html })`. So the `ui-*` components resolve
    here, and every string that could contain a brace (an asset path can)
    goes through `@plain`, never `{{ }}`. Numbers are safe as they are.
--}}

<ui-header title="Reel" icon="media-computer-screen-tv" />

<div class="space-y-6">

    <ui-card-panel heading="Captions and transcripts">
        <div class="space-y-3">
            <p>Every video and audio file in the asset containers, checked against WCAG 1.2.1 to 1.2.5. Reel does not generate captions: it finds the caption file beside each video, or the one chosen on the asset, reads it, and says what a player and a reader will make of it.</p>
            <div class="flex flex-wrap items-center gap-2">
                <ui-badge color="red" text="{{ $counts['fail'] }} failing" />
                <ui-badge color="amber" text="{{ $counts['warn'] }} with findings" />
                <ui-badge color="blue" text="{{ $counts['manual'] }} not reviewed" />
                <ui-badge color="green" text="{{ $counts['pass'] }} passing" />
                <ui-badge text="@plain('Standard: '.$standard)" />
                <ui-badge text="@plain('Gate: '.$gate->mode)" />
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">A caption file that reads like uncorrected machine output is reported as needing a person's review, never as a failure: the score is a likelihood and it says which signals it saw. The audio-description flag is a person's answer and Reel never guesses it.</p>
        </div>
    </ui-card-panel>

    @if ($rows === [])
        <ui-card-panel heading="No media yet">
            <p>There are no video or audio files in any asset container. Upload one and it appears here.</p>
        </ui-card-panel>
    @else
        {{--
            Five columns do not fit a phone: at 375px the table ran 110px past
            the panel, cutting off "Audio description" and leaving Status off
            screen, so seeing whether a file passed meant scrolling the whole
            page sideways. Status sits next to the asset so the answer is in
            view, and only the table scrolls. Inline style, not a class: see
            the 2026-10-23 decision on layout without a build step.
        --}}
        <ui-panel>
            <div style="overflow-x: auto">
                <ui-table>
                    <ui-table-columns>
                        <ui-table-column>Asset</ui-table-column>
                        <ui-table-column>Status</ui-table-column>
                        <ui-table-column>Captions</ui-table-column>
                        <ui-table-column>Transcript</ui-table-column>
                        <ui-table-column>Audio description</ui-table-column>
                    </ui-table-columns>
                    <ui-table-rows>
                        @foreach ($rows as $row)
                            <ui-table-row>
                                <ui-table-cell>
                                    <a href="@plain($row['url'])" class="font-medium">@plain($row['path'])</a>
                                    <div class="text-xs text-gray-500">@plain($row['container'].', '.$row['kind'])</div>
                                </ui-table-cell>
                                <ui-table-cell>
                                    @php
                                        [$colour, $label] = match ($row['verdict']) {
                                            'fail' => ['red', 'Fail'],
                                            'warn' => ['amber', 'Findings'],
                                            'manual' => ['blue', 'Review'],
                                            default => ['green', 'Pass'],
                                        };
                                    @endphp
                                    <ui-badge color="{{ $colour }}" text="{{ $label }}" pill />
                                </ui-table-cell>
                                <ui-table-cell>
                                    @if ($row['captions'] !== null)
                                        <div>@plain(basename($row['captions']))</div>
                                        @php
                                            $notes = [$row['captionsHow'] === 'chosen' ? 'chosen on the asset' : 'found beside the video'];
                                            if ($row['errors'] > 0) $notes[] = $row['errors'].($row['errors'] === 1 ? ' error' : ' errors');
                                            if ($row['warnings'] > 0) $notes[] = $row['warnings'].($row['warnings'] === 1 ? ' finding' : ' findings');
                                            if ($row['machine'] === 'likely') $notes[] = 'likely machine output';
                                            if ($row['machine'] === 'possible') $notes[] = 'possibly machine output';
                                        @endphp
                                        <div class="text-xs text-gray-500">@plain(implode(', ', $notes))</div>
                                    @elseif ($row['chosenMissing'])
                                        <span class="text-red-600">Chosen file is missing</span>
                                    @elseif ($row['kind'] === 'video')
                                        <span class="text-red-600">None</span>
                                    @else
                                        <span class="text-gray-500">Not needed</span>
                                    @endif
                                </ui-table-cell>
                                <ui-table-cell>
                                    @if ($row['transcript'])
                                        Yes
                                    @elseif ($row['kind'] === 'audio')
                                        <span class="text-red-600">None</span>
                                    @else
                                        <span class="text-gray-500">None</span>
                                    @endif
                                </ui-table-cell>
                                <ui-table-cell>
                                    @if ($row['kind'] !== 'video')
                                        <span class="text-gray-500">Not applicable</span>
                                    @elseif ($row['audioDescription'] === 'provided')
                                        Provided
                                    @elseif ($row['audioDescription'] === 'not_applicable')
                                        Not applicable
                                    @elseif ($row['audioDescription'] === 'needed')
                                        <span class="text-red-600">Needed</span>
                                    @else
                                        <span class="text-blue-700">Not reviewed</span>
                                    @endif
                                </ui-table-cell>
                            </ui-table-row>
                        @endforeach
                    </ui-table-rows>
                </ui-table>
            </div>
        </ui-panel>
    @endif

    <ui-card-panel heading="From the command line">
        <div class="space-y-2">
            <p>The same report, for CI:</p>
            {{-- The --json line is wider than a phone; it scrolls in its box, not the page. --}}
            <pre class="text-sm" style="overflow-x: auto">php please reel:audit
php please reel:audit --json | jq '.summary'</pre>
            <p>Exit code 1 when any asset is at or above the configured threshold, 0 otherwise, 2 if the command could not run.</p>
        </div>
    </ui-card-panel>

</div>
