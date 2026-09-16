{{--
    One media asset: where it stands on each criterion, and the caption
    editor. Compiled as a Vue template; see the index view for the rules.
    Cue text never appears in this template at all: the editor component
    fetches it as JSON, so nothing a captioner typed can break the page.
--}}

<ui-header title="@plain($media->asset->basename())" icon="media-computer-screen-tv">
    <ui-button href="@plain($indexUrl)" text="All media" variant="ghost" />
    <ui-button href="@plain($editUrl)" text="Edit asset fields" />
</ui-header>

<div class="space-y-6">

    <ui-card-panel heading="WCAG 1.2: time-based media">
        <ui-table>
            <ui-table-columns>
                <ui-table-column>Criterion</ui-table-column>
                <ui-table-column>Status</ui-table-column>
                <ui-table-column>Why</ui-table-column>
            </ui-table-columns>
            <ui-table-rows>
                @foreach ($assessment->toArray()['criteria'] as $c)
                    @php
                        [$colour, $label] = match ($c['status']) {
                            'pass' => ['green', 'Pass'],
                            'fail' => ['red', 'Fail'],
                            'manual' => ['blue', 'Review'],
                            default => ['default', 'Not applicable'],
                        };
                    @endphp
                    <ui-table-row>
                        <ui-table-cell>
                            <div class="font-medium">@plain($c['criterion'].' '.$c['name'])</div>
                            <div class="text-xs text-gray-500">Level @plain($c['level'])</div>
                        </ui-table-cell>
                        <ui-table-cell><ui-badge color="{{ $colour }}" text="{{ $label }}" pill /></ui-table-cell>
                        <ui-table-cell>@plain($c['reason'])</ui-table-cell>
                    </ui-table-row>
                @endforeach
            </ui-table-rows>
        </ui-table>
    </ui-card-panel>

    <ui-card-panel heading="What Reel found">
        <dl class="grid gap-x-6 gap-y-2 md:grid-cols-[max-content_1fr]">
            <dt class="font-medium">Asset</dt>
            <dd>@plain($media->asset->containerHandle().'::'.$media->asset->path()) (@plain($media->kind()))</dd>

            <dt class="font-medium">Caption track</dt>
            <dd>
                @if ($track !== null)
                    @plain($track->path()), @plain($track->how === 'chosen' ? 'chosen on the asset' : 'found beside the video by name'), language @plain($track->language)
                    @if ($inspection !== null)
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ count($inspection->document->cues()) }} cues, {{ round($inspection->document->durationMs() / 1000) }} seconds, {{ count($inspection->errors) }} {{ count($inspection->errors) === 1 ? 'error' : 'errors' }}, {{ count($inspection->warnings) }} quality {{ count($inspection->warnings) === 1 ? 'finding' : 'findings' }} against the @plain($standard->name()) standard.</div>
                    @endif
                @elseif ($media->chosenTrackIsMissing())
                    <span class="text-red-600">The file chosen on the asset no longer exists.</span> Choose another in the asset's fields, or clear the choice to use a file beside the video.
                @elseif ($media->kind() === 'video')
                    <span class="text-red-600">None.</span> Put a WebVTT file named @plain(pathinfo($media->asset->basename(), PATHINFO_FILENAME).'.vtt') or @plain(pathinfo($media->asset->basename(), PATHINFO_FILENAME).'.en.vtt') in the same folder, or choose one in the asset's fields.
                @else
                    Not needed for audio. A transcript is.
                @endif
            </dd>

            @if (count($sidecars) > 1)
                <dt class="font-medium">Other tracks beside it</dt>
                <dd>
                    @foreach ($sidecars as $s)
                        @if ($track === null || $s->path() !== $track->path())<div>@plain($s->path()) (@plain($s->language))</div>@endif
                    @endforeach
                </dd>
            @endif

            <dt class="font-medium">Transcript</dt>
            <dd>
                @if ($media->transcriptHtml() !== '')
                    On the asset, {{ str_word_count(strip_tags($media->transcriptHtml())) }} words.
                @elseif ($transcriptEntry !== null)
                    The entry <a href="@plain($transcriptEntry->editUrl())">@plain($transcriptEntry->get('title', $transcriptEntry->slug()))</a>.
                @else
                    <span class="{{ $media->kind() === 'audio' ? 'text-red-600' : '' }}">None.</span> Add one in the asset's fields.
                @endif
            </dd>

            @if ($media->kind() === 'video')
                <dt class="font-medium">Audio description</dt>
                <dd>
                    @php $ad = $media->audioDescription(); @endphp
                    @if ($ad === null)
                        <span class="text-blue-700">Not reviewed.</span> Nothing can detect this from the file. Answer it in the asset's fields.
                    @else
                        @plain(\Bpmore\Reel\Compliance\AudioDescription::OPTIONS[$ad])
                    @endif
                </dd>
            @endif
        </dl>
    </ui-card-panel>

    @if ($track !== null)
        <reel-editor
            load-url="@plain($loadUrl)"
            save-url="@plain($saveUrl)"
            inspect-url="@plain($inspectUrl)"
            :can-edit="{{ $canEdit ? 'true' : 'false' }}"
        ></reel-editor>
    @endif

</div>
