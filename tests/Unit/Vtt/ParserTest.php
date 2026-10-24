<?php

declare(strict_types=1);

use Bpmore\Reel\Vtt\Cue;
use Bpmore\Reel\Vtt\Note;
use Bpmore\Reel\Vtt\Parser;
use Bpmore\Reel\Vtt\Region;
use Bpmore\Reel\Vtt\Serializer;
use Bpmore\Reel\Vtt\Style;
use Bpmore\Reel\Vtt\Timestamp;
use Bpmore\Reel\Vtt\UnreadBlock;
use Bpmore\Reel\Vtt\UnreadCue;

/*
 * The parser. Boots nothing: a string in, a document and a list of errors out.
 */

it('returns a structured error, not an exception, for a file missing its header', function () {
    $result = (new Parser)->parse("00:00:01.000 --> 00:00:02.000\nHello\n");

    expect($result->ok())->toBeFalse();
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->line)->toBe(1);
    expect($result->errors[0]->code)->toBe('missing-header');
    expect($result->errors[0]->message)->toContain('WEBVTT');
});

it('returns a structured error naming line 7 for a corrupt timestamp there, and keeps the other cues', function () {
    $vtt = implode("\n", [
        'WEBVTT',                              // 1
        '',                                    // 2
        '00:00:01.000 --> 00:00:02.000',       // 3
        'First',                               // 4
        '',                                    // 5
        'broken',                              // 6
        '00:00:03,000 --> 00:00:04.000',       // 7: an SRT comma
        'Second',                              // 8
        '',                                    // 9
        '00:00:05.000 --> 00:00:06.000',       // 10
        'Third',                               // 11
    ]);

    $result = (new Parser)->parse($vtt);

    expect($result->ok())->toBeFalse();
    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->line)->toBe(7);
    expect($result->errors[0]->code)->toBe('malformed-timestamp');

    $cues = $result->document->cues();
    expect($cues)->toHaveCount(2);
    expect($cues[0]->text)->toBe('First');
    expect($cues[1]->text)->toBe('Third');
});

it('keeps a cue whose timing cannot be read, in place, so writing the file back does not delete it', function () {
    // The SRT comma on campus-tour.vtt. The cue used to be skipped, so the
    // editor never showed it and a save wrote the file without its text.
    $vtt = "WEBVTT\n\n1\n00:00:01.000 --> 00:00:02.000\nFirst\n\n2\n00:00:08,000 --> 00:00:10.500\nThe gardens are open until dusk.\n\n3\n00:00:11.000 --> 00:00:12.000\nThird\n";

    $result = (new Parser)->parse($vtt);

    expect($result->ok())->toBeFalse();
    expect($result->document->cues())->toHaveCount(2);
    expect($result->document->blocks)->toHaveCount(3);
    expect($result->document->blocks[1])->toBeInstanceOf(UnreadCue::class);
    expect($result->document->blocks[1]->text)->toBe('The gardens are open until dusk.');
    expect((new Serializer)->serialize($result->document))->toBe($vtt);
});

it('keeps a block with no timing line as a cue to be timed, not as nothing', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nFirst\n\nA line someone forgot to time\n";

    $result = (new Parser)->parse($vtt);

    expect($result->errors[0]->code)->toBe('cue-missing-timing');
    expect($result->document->blocks[1])->toBeInstanceOf(UnreadCue::class);
    expect((new Serializer)->serialize($result->document))->toBe($vtt);
});

it('never throws, whatever it is given', function (string $input) {
    $result = (new Parser)->parse($input);

    expect($result->errors)->toBeArray();
})->with([
    'empty' => '',
    'header only' => 'WEBVTT',
    'header glued to text' => 'WEBVTTfoo',
    'bare arrow' => "WEBVTT\n\n-->\n",
    'arrow with one side' => "WEBVTT\n\n00:00:01.000 -->\ntext\n",
    'binary' => "WEBVTT\n\n\x00\x01\x02 --> \xff\n",
    'crlf' => "WEBVTT\r\n\r\n00:00:01.000 --> 00:00:02.000\r\nHi\r\n",
    'cr only' => "WEBVTT\r\r00:00:01.000 --> 00:00:02.000\rHi\r",
    'bom' => "\xEF\xBB\xBFWEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n",
    'no trailing newline' => "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi",
    'settings garbage' => "WEBVTT\n\n00:00:01.000 --> 00:00:02.000 line: :: align\nHi\n",
    'region garbage' => "WEBVTT\n\nREGION\nid\nwidth:x\n\n00:00:01.000 --> 00:00:02.000\nHi\n",
]);

it('parses timestamps with and without hours to the same millisecond value', function () {
    expect(Timestamp::parse('00:01:02.500')?->milliseconds)->toBe(62500);
    expect(Timestamp::parse('01:02.500')?->milliseconds)->toBe(62500);
    expect(Timestamp::parse('10:00:00.001')?->milliseconds)->toBe(36000001);
    expect(Timestamp::parse('100:00:00.000')?->milliseconds)->toBe(360000000);
});

it('refuses malformed timestamps without throwing', function (string $bad) {
    expect(Timestamp::parse($bad))->toBeNull();
})->with([
    '00:00:01,000',   // comma
    '0:00:01.000',    // one-digit hours are allowed but one-digit minutes are not
    '00:1:01.000',
    '00:00:1.000',
    '00:00:01.00',    // two-digit ms
    '00:00:01',       // no ms
    '00:60:01.000',   // minutes over 59
    '00:00:60.000',   // seconds over 59
    '',
    'abc',
]);

it('keeps the hours field as written so a file without hours is written back without them', function () {
    expect(Timestamp::parse('00:01:02.500')?->format())->toBe('00:01:02.500');
    expect(Timestamp::parse('01:02.500')?->format())->toBe('01:02.500');
    expect(Timestamp::fromMilliseconds(3723004)->format())->toBe('01:02:03.004');
    // Without hours is only possible under an hour.
    expect(Timestamp::fromMilliseconds(3723004, withHours: false)->format())->toBe('01:02:03.004');
    expect(Timestamp::fromMilliseconds(62500, withHours: false)->format())->toBe('01:02.500');
});

it('parses a voice span to a speaker attribution', function () {
    $cue = new Cue(Timestamp::parse('00:00:00.000'), Timestamp::parse('00:00:01.000'), '<v Dr. Smith>Take a breath.</v>');

    expect($cue->speakers())->toBe(['Dr. Smith']);
    expect($cue->plainText())->toBe('Take a breath.');

    $classed = new Cue(Timestamp::parse('00:00:00.000'), Timestamp::parse('00:00:01.000'), "<v.loud.patient Jordan Lee>Ow!</v>\n<v Nurse>Sorry.</v>");

    expect($classed->speakers())->toBe(['Jordan Lee', 'Nurse']);
    expect($classed->plainText())->toBe("Ow!\nSorry.");
});

it('reads every block type and cue setting from the complex fixture', function () {
    $result = (new Parser)->parse(captions('complex.vtt'));

    expect($result->errors)->toBe([]);

    $doc = $result->document;
    expect($doc->header)->toBe('- Fixture with every block type');

    $types = array_map(fn ($b) => $b::class, $doc->blocks);
    expect($types)->toBe([
        Note::class, Region::class, Style::class,
        Cue::class, Cue::class, Note::class, Cue::class, Cue::class, Cue::class,
    ]);

    $region = $doc->regions()['speaker'];
    expect($region->settings['width'])->toBe('40%');
    expect($region->settings['lines'])->toBe('3');
    expect($region->settings['scroll'])->toBe('up');

    $cues = $doc->cues();
    expect($cues)->toHaveCount(5);

    expect($cues[0]->id)->toBe('intro');
    expect($cues[0]->settings->get('align'))->toBe('start');
    expect($cues[0]->settings->get('position'))->toBe('10%');
    expect($cues[0]->settings->get('size'))->toBe('80%');
    expect($cues[0]->settings->get('region'))->toBe('speaker');
    expect($cues[0]->speakers())->toBe(['Dr. Smith']);
    expect($cues[0]->line)->toBe(25);

    expect($cues[1]->id)->toBeNull();
    expect($cues[1]->start->milliseconds)->toBe(1500);
    expect($cues[1]->start->withHours)->toBeFalse();
    expect($cues[1]->speakers())->toBe(['Jordan Lee']);
    expect($cues[1]->plainText())->toBe('Thanks, doctor. Nervous but ready.');

    expect($cues[2]->id)->toBe('chorus-1');
    expect($cues[2]->settings->get('line'))->toBe('0');
    expect($cues[2]->plainText())->toBe('Take a deep breath.');

    expect($cues[3]->settings->get('vertical'))->toBe('rl');
    expect($cues[3]->plainText())->toBe('Right now! & hold it.');

    expect($cues[4]->start->milliseconds)->toBe(3613000);
    expect($cues[4]->plainText())->toBe('[door closes]');
});

it('round-trips a complex file: parse, serialize, parse again is semantically identical', function () {
    $source = captions('complex.vtt');
    $first = (new Parser)->parse($source);
    $written = (new Serializer)->serialize($first->document);
    $second = (new Parser)->parse($written);

    expect($second->errors)->toBe([]);
    expect($second->document->toArray())->toBe($first->document->toArray());

    // And the text itself: the same file, byte for byte, once line endings are
    // one kind. An editor that rewrites a file it did not change is an editor
    // whose diffs nobody can read.
    expect($written)->toBe($source);
});

it('survives a round trip through the editor representation with the voice span unchanged', function () {
    $source = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n<v Dr. Smith>Take a breath.</v>\n";

    $doc = (new Parser)->parse($source)->document;
    $edited = \Bpmore\Reel\Vtt\Document::fromArray($doc->toArray());

    expect($edited->cues()[0]->speakers())->toBe(['Dr. Smith']);
    expect((new Serializer)->serialize($edited))->toBe($source);
});

it('records a STYLE block after the first cue as an error rather than swallowing it', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n\nSTYLE\n::cue { color: red }\n";
    $result = (new Parser)->parse($vtt);

    expect($result->errors)->toHaveCount(1);
    expect($result->errors[0]->code)->toBe('block-out-of-place');
    expect($result->errors[0]->line)->toBe(6);
    expect($result->document->cues())->toHaveCount(1);
});

it('keeps a STYLE or REGION block after the first cue, in place, so writing the file back does not delete it', function () {
    // Players ignore such a block, but it is someone's text, and skipping it
    // meant the next save from the editor deleted it.
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n\nSTYLE\n::cue { color: red }\n\nREGION\nid:late\nwidth:40%\n\n00:00:03.000 --> 00:00:04.000 region:late\nThere\n";
    $result = (new Parser)->parse($vtt);

    expect(array_map(fn ($e) => $e->code, $result->errors))->toBe(['block-out-of-place', 'block-out-of-place']);
    expect($result->document->blocks[1])->toBeInstanceOf(UnreadBlock::class);
    expect($result->document->blocks[2])->toBeInstanceOf(UnreadBlock::class);
    // Not a region a cue can use: a player would not see it either.
    expect($result->document->regions())->toBe([]);
    expect((new Serializer)->serialize($result->document))->toBe($vtt);
});

it('counts a cue whose timing cannot be read as a cue, as the editor shows it', function () {
    $result = (new Parser)->parse("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nOne\n\n00:00:03,000 --> 00:00:04.000\nTwo\n");

    expect($result->document->cues())->toHaveCount(1);
    expect($result->document->cueCount())->toBe(2);
});

it('treats a line containing an arrow inside a payload as the start of a new cue, as the spec does', function () {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nFirst\n00:00:03.000 --> 00:00:04.000\nSecond\n";
    $result = (new Parser)->parse($vtt);

    expect($result->document->cues())->toHaveCount(2);
    expect($result->document->cues()[1]->text)->toBe('Second');
});

it('reads real files without error and writes them back semantically unchanged', function (string $file) {
    $source = captions('real/'.$file);
    $first = (new Parser)->parse($source);

    expect($first->errors)->toBe([]);
    expect(count($first->document->cues()))->toBeGreaterThan(5);

    $second = (new Parser)->parse((new Serializer)->serialize($first->document));

    expect($second->errors)->toBe([]);
    expect($second->document->toArray())->toBe($first->document->toArray());
})->with(['sintel-en.vtt', 'videojs-example-captions.vtt']);
