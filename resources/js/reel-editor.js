/**
 * The caption editor: a cue list with timing, text and live findings.
 *
 * A plain script rather than a built bundle. Statamic puts Vue on
 * `window.Vue` with the runtime template compiler, so a `template` string
 * works and the addon needs no npm and no build step. Everything visible
 * is Statamic's own component library, with native `fieldset`, `legend`,
 * `ol` and `details` for the structure, because those carry their meaning
 * to a screen reader without any ARIA at all.
 *
 * Keyboard first, on purpose. Every control is a real button or input in
 * document order, so Tab walks the cues top to bottom; "Next finding"
 * moves focus to the text of the next cue with a problem; a delete is two
 * presses with the second labelled, never a browser dialog. There is no
 * drag, no hover-only control, and nothing moves.
 *
 * The editor holds the document in the parser's array form and never as
 * text. Edits are sent to the server to be inspected as they are made,
 * and sent once more to be written: the serializer, not this script,
 * produces the file, so what is written is what the parser reads back.
 */
(function () {
    const PAGE = 50;

    function parseTs(text) {
        const m = /^(?:(\d{2,}):)?(\d{2}):(\d{2})\.(\d{3})$/.exec(String(text).trim());
        if (!m) return null;
        const minutes = Number(m[2]), seconds = Number(m[3]);
        if (minutes > 59 || seconds > 59) return null;
        const hours = m[1] === undefined ? 0 : Number(m[1]);
        return { ms: ((hours * 60 + minutes) * 60 + seconds) * 1000 + Number(m[4]), withHours: m[1] !== undefined };
    }

    function formatTs(ms, withHours) {
        ms = Math.max(0, ms);
        const h = Math.floor(ms / 3600000), m = Math.floor((ms % 3600000) / 60000), s = Math.floor((ms % 60000) / 1000), x = ms % 1000;
        const pad = (n, w) => String(n).padStart(w, '0');
        return (withHours || h > 0) ? `${pad(h, 2)}:${pad(m, 2)}:${pad(s, 2)}.${pad(x, 3)}` : `${pad(m, 2)}:${pad(s, 2)}.${pad(x, 3)}`;
    }

    function settingsToString(settings) {
        if (typeof settings === 'string') return settings;
        return Object.entries(settings || {}).map(([k, v]) => `${k}:${v}`).join(' ');
    }

    Statamic.$components.register('reel-editor', {
        props: {
            loadUrl: String,
            saveUrl: String,
            inspectUrl: String,
            canEdit: { type: Boolean, default: false },
        },

        template: `
<ui-card-panel heading="Caption editor">
    <div v-if="state.loading">Loading the caption file.</div>
    <div v-else-if="state.failed" class="text-red-600">{{ state.failed }}</div>
    <div v-else-if="!state.doc">This asset has no caption track to edit.</div>
    <div v-else class="space-y-5">

        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ state.track.path }}, language {{ state.track.language }}, {{ cueIndexes.length }} cues.
            <span v-if="!canEdit">Read only: you do not have permission to save caption files.</span>
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <ui-button v-if="canEdit" variant="primary" :disabled="!state.dirty || state.saving" :text="state.saving ? 'Saving' : 'Save captions'" @click="save" />
            <ui-button size="sm" :disabled="findings.length === 0" text="Next finding" @click="nextFinding" />
            <ui-button size="sm" variant="ghost" :disabled="!state.dirty" text="Discard changes" @click="reload" />
            <ui-badge :color="errorCount ? 'red' : 'green'" :text="errorCount + (errorCount === 1 ? ' error' : ' errors')" />
            <ui-badge :color="warningCount ? 'amber' : 'default'" :text="warningCount + (warningCount === 1 ? ' finding' : ' findings')" />
            <ui-badge :color="machineColor" :text="'Machine captions: ' + machine.label + ' (' + Math.round(machine.confidence * 100) + '%)'" />
        </div>

        <p role="status" aria-live="polite" class="text-sm min-h-5">{{ state.status }}</p>

        <details>
            <summary class="cursor-pointer font-medium">Machine caption signals</summary>
            <p class="mt-2 text-sm">A likelihood, not a verdict. Each signal says what it saw; a stylistic choice can trip one, and a person decides.</p>
            <ul class="mt-2 space-y-1 text-sm">
                <li v-for="s in machine.signals" :key="s.key">
                    <strong>{{ s.name }}</strong>: {{ s.fired ? 'fired at ' + Math.round(s.strength * 100) + '%' : 'not fired' }}<span v-if="s.low_confidence"> (weak evidence by nature)</span>. {{ s.evidence }}
                </li>
            </ul>
        </details>

        <section v-if="fileFindings.length" aria-labelledby="reel-file-findings">
            <h3 id="reel-file-findings" class="font-medium">Findings about the file</h3>
            <ul class="mt-1 space-y-1 text-sm">
                <li v-for="(f, i) in fileFindings" :key="'file' + i">
                    <ui-badge :color="f.severity === 'error' ? 'red' : 'amber'" :text="f.severity" pill /> <span v-if="f.line">Line {{ f.line }}: </span>{{ f.message }}
                </li>
            </ul>
        </section>

        <nav v-if="pageCount > 1" aria-label="Cue pages" class="flex flex-wrap items-center gap-2">
            <ui-button size="sm" :disabled="state.page === 0" text="Previous 50" @click="state.page--" />
            <span class="text-sm">Cues {{ state.page * 50 + 1 }} to {{ Math.min((state.page + 1) * 50, cueIndexes.length) }} of {{ cueIndexes.length }}</span>
            <ui-button size="sm" :disabled="state.page >= pageCount - 1" text="Next 50" @click="state.page++" />
        </nav>

        <ol class="space-y-4 list-none p-0 m-0">
            <li v-for="(n, i) in pageCueNumbers" :key="'cue' + blockIndex(n)">
                <fieldset class="rounded-lg border p-4 space-y-3" :class="cueFindings(n).some(f => f.severity === 'error') ? 'border-red-500' : (cueFindings(n).length ? 'border-amber-500' : 'border-gray-300 dark:border-gray-700')">
                    <legend class="px-1 font-medium">
                        Cue {{ n + 1 }}<span v-if="cue(n).id"> ({{ cue(n).id }})</span>
                        <span v-if="cueFindings(n).length" class="font-normal text-sm">, {{ cueFindings(n).length }} {{ cueFindings(n).length === 1 ? 'finding' : 'findings' }}</span>
                    </legend>

                    <div class="grid gap-3 md:grid-cols-4">
                        <ui-field label="Identifier" :id="fid(n, 'id')">
                            <ui-input :id="fid(n, 'id')" :model-value="cue(n).id || ''" @update:model-value="v => set(n, 'id', v)" :disabled="!canEdit" />
                        </ui-field>
                        <ui-field label="Start" :id="fid(n, 'start')" :instructions-below="true">
                            <ui-input :id="fid(n, 'start')" :model-value="cue(n).start" @update:model-value="v => set(n, 'start', v)" :disabled="!canEdit" placeholder="00:00:00.000" />
                            <div class="mt-1 flex gap-1">
                                <ui-button size="xs" variant="ghost" text="-100 ms" :aria-label="'Start of cue ' + (n + 1) + ' earlier by 100 milliseconds'" :disabled="!canEdit" @click="nudge(n, 'start', -100)" />
                                <ui-button size="xs" variant="ghost" text="+100 ms" :aria-label="'Start of cue ' + (n + 1) + ' later by 100 milliseconds'" :disabled="!canEdit" @click="nudge(n, 'start', 100)" />
                            </div>
                        </ui-field>
                        <ui-field label="End" :id="fid(n, 'end')">
                            <ui-input :id="fid(n, 'end')" :model-value="cue(n).end" @update:model-value="v => set(n, 'end', v)" :disabled="!canEdit" placeholder="00:00:00.000" />
                            <div class="mt-1 flex gap-1">
                                <ui-button size="xs" variant="ghost" text="-100 ms" :aria-label="'End of cue ' + (n + 1) + ' earlier by 100 milliseconds'" :disabled="!canEdit" @click="nudge(n, 'end', -100)" />
                                <ui-button size="xs" variant="ghost" text="+100 ms" :aria-label="'End of cue ' + (n + 1) + ' later by 100 milliseconds'" :disabled="!canEdit" @click="nudge(n, 'end', 100)" />
                            </div>
                        </ui-field>
                        <ui-field label="Settings" :id="fid(n, 'settings')">
                            <ui-input :id="fid(n, 'settings')" :model-value="settingsText(n)" @update:model-value="v => setSettings(n, v)" :disabled="!canEdit" placeholder="line:0 align:start" />
                        </ui-field>
                    </div>

                    <ui-field label="Text" :id="fid(n, 'text')">
                        <ui-textarea :id="fid(n, 'text')" :rows="2" :model-value="cue(n).text" @update:model-value="v => set(n, 'text', v)" :disabled="!canEdit" :aria-describedby="cueFindings(n).length ? fid(n, 'findings') : null" />
                    </ui-field>

                    <ul v-if="cueFindings(n).length" :id="fid(n, 'findings')" class="space-y-1 text-sm">
                        <li v-for="(f, j) in cueFindings(n)" :key="j">
                            <ui-badge :color="f.severity === 'error' ? 'red' : 'amber'" :text="f.severity" pill /> {{ f.message }}
                        </li>
                    </ul>

                    <div v-if="canEdit" class="flex flex-wrap gap-2">
                        <ui-button size="sm" variant="ghost" text="Insert cue after" :aria-label="'Insert a cue after cue ' + (n + 1)" @click="insertAfter(n)" />
                        <template v-if="state.deleting === n">
                            <ui-button size="sm" variant="danger" :text="'Confirm: delete cue ' + (n + 1)" @click="remove(n)" />
                            <ui-button size="sm" variant="ghost" text="Keep it" @click="state.deleting = null" />
                        </template>
                        <ui-button v-else size="sm" variant="ghost" text="Delete cue" :aria-label="'Delete cue ' + (n + 1)" @click="state.deleting = n" />
                    </div>
                </fieldset>
            </li>
        </ol>

        <nav v-if="pageCount > 1" aria-label="Cue pages, bottom" class="flex flex-wrap items-center gap-2">
            <ui-button size="sm" :disabled="state.page === 0" text="Previous 50" @click="state.page--" />
            <ui-button size="sm" :disabled="state.page >= pageCount - 1" text="Next 50" @click="state.page++" />
        </nav>

    </div>
</ui-card-panel>
`,

        setup(props) {
            const axios = Statamic.$app.config.globalProperties.$axios;

            const state = Vue.reactive({
                loading: true,
                failed: null,
                track: null,
                doc: null,
                inspection: null,
                dirty: false,
                saving: false,
                status: '',
                page: 0,
                deleting: null,
                findingCursor: -1,
                // What the last action did, said before the counts once the
                // check comes back, so a nudge is not announced as "Checking".
                note: '',
            });

            let timer = null;

            // Cues are blocks of type "cue"; everything else (notes, regions,
            // styles) is kept in the document untouched and never shown.
            const cueIndexes = Vue.computed(() => (state.doc ? state.doc.blocks : []).map((b, i) => (b.type === 'cue' ? i : -1)).filter(i => i >= 0));
            const pageCount = Vue.computed(() => Math.max(1, Math.ceil(cueIndexes.value.length / PAGE)));
            const pageCueNumbers = Vue.computed(() => cueIndexes.value.map((_, n) => n).slice(state.page * PAGE, (state.page + 1) * PAGE));

            const blockIndex = n => cueIndexes.value[n];
            const cue = n => state.doc.blocks[blockIndex(n)];
            const fid = (n, part) => 'reel-cue-' + blockIndex(n) + '-' + part;

            const errors = Vue.computed(() => (state.inspection ? state.inspection.errors : []));
            const warnings = Vue.computed(() => (state.inspection ? state.inspection.warnings : []));
            const findings = Vue.computed(() => errors.value.concat(warnings.value).filter(f => f.cue !== null && f.cue !== undefined).sort((a, b) => a.cue - b.cue));
            const fileFindings = Vue.computed(() => errors.value.concat(warnings.value).filter(f => f.cue === null || f.cue === undefined));
            const errorCount = Vue.computed(() => errors.value.length);
            const warningCount = Vue.computed(() => warnings.value.length);
            const machine = Vue.computed(() => (state.inspection ? state.inspection.machine : { label: 'unlikely', confidence: 0, signals: [] }));
            const machineColor = Vue.computed(() => ({ likely: 'red', possible: 'amber' }[machine.value.label] || 'green'));

            const cueFindings = n => findings.value.filter(f => f.cue === n);

            async function load() {
                state.loading = true;
                state.failed = null;

                try {
                    const { data } = await axios.get(props.loadUrl);
                    state.track = data.track;
                    state.doc = data.document || null;
                    state.inspection = data.inspection || null;
                    state.dirty = false;
                    state.page = 0;
                    state.status = '';
                } catch (e) {
                    state.failed = 'The caption file could not be loaded: ' + (e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message);
                } finally {
                    state.loading = false;
                }
            }

            async function reload() {
                await load();
                state.status = 'Changes discarded. The file is as it was saved.';
            }

            function changed() {
                state.dirty = true;
                state.status = state.note ? state.note + ' Checking.' : 'Checking.';
                clearTimeout(timer);
                timer = setTimeout(inspect, 400);
            }

            async function inspect() {
                try {
                    const { data } = await axios.post(props.inspectUrl, { document: state.doc });
                    state.inspection = data.inspection;
                    state.status = (state.note ? state.note + ' ' : '') + errorCount.value + (errorCount.value === 1 ? ' error, ' : ' errors, ') + warningCount.value + (warningCount.value === 1 ? ' finding.' : ' findings.') + ' Not saved yet.';
                } catch (e) {
                    state.status = 'Could not check: ' + (e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message);
                } finally {
                    state.note = '';
                }
            }

            function set(n, key, value) {
                const c = cue(n);
                c[key] = key === 'id' && value === '' ? null : value;
                changed();
            }

            const settingsText = n => settingsToString(cue(n).settings);

            function setSettings(n, value) {
                // Kept as text until it is sent; the server parses it, and a
                // setting it does not understand is kept as written.
                cue(n).settings = value;
                changed();
            }

            function nudge(n, key, delta) {
                const parsed = parseTs(cue(n)[key]);
                if (!parsed) {
                    state.status = 'The ' + key + ' of cue ' + (n + 1) + ' is not a timestamp, so it cannot be moved.';
                    return;
                }
                cue(n)[key] = formatTs(parsed.ms + delta, parsed.withHours);
                state.note = (key === 'start' ? 'Start' : 'End') + ' of cue ' + (n + 1) + ' is now ' + cue(n)[key] + '.';
                changed();
            }

            function insertAfter(n) {
                const c = cue(n);
                const end = parseTs(c.end) || { ms: 0, withHours: true };
                const block = { type: 'cue', id: null, start: formatTs(end.ms, end.withHours), end: formatTs(end.ms + 1000, end.withHours), settings: {}, text: '', line: 0 };
                state.doc.blocks.splice(blockIndex(n) + 1, 0, block);
                state.note = 'Cue ' + (n + 2) + ' inserted.';
                changed();
                Vue.nextTick(() => focusField(n + 1, 'text'));
            }

            function remove(n) {
                state.doc.blocks.splice(blockIndex(n), 1);
                state.deleting = null;
                state.note = 'Cue ' + (n + 1) + ' deleted.';
                changed();
                Vue.nextTick(() => focusField(Math.min(n, cueIndexes.value.length - 1), 'text'));
            }

            function focusField(n, part) {
                if (n < 0 || n >= cueIndexes.value.length) return;
                state.page = Math.floor(n / PAGE);
                Vue.nextTick(() => {
                    const el = document.getElementById(fid(n, part));
                    if (el) el.focus();
                });
            }

            function nextFinding() {
                if (!findings.value.length) return;
                state.findingCursor = (state.findingCursor + 1) % findings.value.length;
                const f = findings.value[state.findingCursor];
                focusField(f.cue, 'text');
                state.status = 'Cue ' + (f.cue + 1) + ': ' + f.message;
            }

            async function save() {
                state.saving = true;
                state.status = 'Saving';

                try {
                    const { data } = await axios.post(props.saveUrl, { document: state.doc });
                    state.inspection = data.inspection;
                    state.dirty = false;
                    state.status = 'Saved. ' + errorCount.value + (errorCount.value === 1 ? ' error, ' : ' errors, ') + warningCount.value + (warningCount.value === 1 ? ' finding.' : ' findings.');
                } catch (e) {
                    state.status = 'Not saved: ' + (e.response && e.response.data && e.response.data.message ? e.response.data.message : e.message);
                } finally {
                    state.saving = false;
                }
            }

            Vue.onMounted(load);

            return {
                state, cueIndexes, pageCount, pageCueNumbers, blockIndex, cue, fid,
                findings, fileFindings, errorCount, warningCount, machine, machineColor, cueFindings,
                set, settingsText, setSettings, nudge, insertAfter, remove, nextFinding, save, reload,
            };
        },
    });
})();
