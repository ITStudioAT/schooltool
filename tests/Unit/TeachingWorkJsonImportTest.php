<?php

use App\Support\SchooltoolAssessmentJson;
use Illuminate\Validation\ValidationException;
use Tests\Support\TeachingWorkJsonFixture;
use Tests\TestCase;

uses(TestCase::class);

test('JSON submission checks preserve complete and open checkpoints separately from assessment completion', function (string $state) {
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
    $package = TeachingWorkJsonFixture::sign(TeachingWorkJsonFixture::withSubmissionCheck(TeachingWorkJsonFixture::package(), $state));
    expect((new SchooltoolAssessmentJson)->parse(json_encode($package, JSON_THROW_ON_ERROR)))->toBe($package);
})->with(['complete', 'open']);

test('JSON submission checks reject unproven inconsistent or corrupted checkpoints', function (string $case) {
    $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
    $package = TeachingWorkJsonFixture::withSubmissionCheck(TeachingWorkJsonFixture::package());
    $check = &$package['submission_check'];
    match ($case) {
        'cache' => $check['coverage'][0]['scope'] = 'local_cache',
        'missing mailbox' => array_pop($check['coverage']),
        'duplicate mailbox' => $check['coverage'][1]['mailbox'] = $check['coverage'][0]['mailbox'],
        'late search' => $check['coverage'][0]['start_at'] = '2026-10-04T08:00:01Z',
        'short search' => $check['coverage'][0]['end_at'] = '2026-10-06T10:00:59Z',
        'unverified' => $check['coverage'][0]['verified'] = false,
        'fake boolean' => $check['coverage'][0]['verified'] = 1,
        'missing evidence' => $check['coverage'][0]['evidence_sha256'] = [],
        'future' => $check['checked_at'] = '2099-10-06T10:01:00Z',
        'invalid date' => $check['checked_at'] = '2026-02-30T10:01:00Z',
        'before deadline' => $check['deadline_at'] = $check['checked_at'],
        'wrong exercise' => $check['exercise_id'] = 'OTHER',
        'wrong roster' => $check['roster_fingerprint'] = str_repeat('b', 64),
        'missing person' => array_pop($check['participants']),
        'person gap' => $check['participants'][0]['gaps'] = ['missing attachment'],
        'other route unresolved' => $check['participants'][0]['other_result'] = 'unresolved',
        'late dispatch' => $check['participants'][0]['dispatched_at'] = $check['checked_at'],
        'candidate unresolved' => $check['unresolved_candidates'] = [['candidate_fingerprint' => str_repeat('c', 64), 'evidence_sha256' => [str_repeat('a', 64)]]],
        'record contradiction' => $package['records'][1]['submission_state'] = 'unresolved',
        'later collected' => $package['records'][0]['email_collected_at'] = '2026-10-06T10:02:00Z',
        'wrong completion' => $check['completed_at'] = '2026-10-06T10:02:00Z',
        'checksum' => $check['check_checksum'] = str_repeat('b', 64),
    };
    if ($case !== 'checksum') {
        unset($check['check_checksum']);
        $check['check_checksum'] = SchooltoolAssessmentJson::digest($check);
    }
    unset($check);
    expect(fn () => (new SchooltoolAssessmentJson)->parse(json_encode(TeachingWorkJsonFixture::sign($package), JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
})->with(['cache', 'missing mailbox', 'duplicate mailbox', 'late search', 'short search', 'unverified', 'fake boolean', 'missing evidence', 'future', 'invalid date', 'before deadline', 'wrong exercise', 'wrong roster', 'missing person', 'person gap', 'other route unresolved', 'late dispatch', 'candidate unresolved', 'record contradiction', 'later collected', 'wrong completion', 'checksum']);

test('JSON submission check permits evidenced non submission while keeping its assessment open', function () {
    $package = TeachingWorkJsonFixture::withSubmissionCheck(TeachingWorkJsonFixture::package());
    $package['submission_check']['participants'][1]['email_result'] = 'not_found';
    $package['records'][1]['submission_state'] = 'not_received';
    unset($package['submission_check']['check_checksum']);
    $package['submission_check']['check_checksum'] = SchooltoolAssessmentJson::digest($package['submission_check']);
    $package = TeachingWorkJsonFixture::sign($package);
    $parsed = (new SchooltoolAssessmentJson)->parse(json_encode($package, JSON_THROW_ON_ERROR));
    expect($parsed['records'][1]['evaluation_state'])->toBe('open');
});

test('JSON v1 accepts optional nullable UTC event times without changing old checksums', function (?string $value) {
    $package = TeachingWorkJsonFixture::package();
    $package['records'][0]['email_collected_at'] = $value;
    $package['records'][0]['evaluation_completed_at'] = $value;
    $signed = TeachingWorkJsonFixture::sign($package);
    expect((new SchooltoolAssessmentJson)->parse(json_encode($signed, JSON_THROW_ON_ERROR)))->toBe($signed);
})->with([null, '2026-10-07T00:10:00Z', '2024-02-29T23:59:59.1Z', '2026-10-07T00:10:00.123456Z']);

test('JSON v1 rejects invalid event dates and types', function (mixed $value) {
    $package = TeachingWorkJsonFixture::package();
    $package['records'][0]['email_collected_at'] = $value;
    expect(fn () => (new SchooltoolAssessmentJson)->parse(json_encode(TeachingWorkJsonFixture::sign($package), JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
})->with(['', 123, false, '0000-01-01T00:00:00Z', '2026-02-29T00:00:00Z', '2026-04-31T00:00:00Z', '2026-10-07T24:00:00Z', '2026-10-07T00:60:00Z', '2026-10-07T00:00:60Z', '2026-10-07T00:00:00', '2026-10-07T00:00:00+02:00', '2026-10-07T00:00:00.1234567Z', '2026-10-07T00:00:00Z'."\n"]);

test('JSON v1 enforces event time state boundaries', function (string $state) {
    $package = TeachingWorkJsonFixture::package();
    $record = &$package['records'][0];
    $record['evaluation_state'] = 'open';
    $record['total_minor'] = null;
    $record['criteria'][0]['earned_minor'] = null;
    if ($state === 'partial') {
        $record['evaluation_state'] = 'partial';
    } elseif ($state !== 'open') {
        $record['submission_state'] = $state;
    }
    $record[$state === 'open' || $state === 'partial' ? 'evaluation_completed_at' : 'email_collected_at'] = '2026-10-07T00:00:00Z';
    unset($record);
    expect(fn () => (new SchooltoolAssessmentJson)->parse(json_encode(TeachingWorkJsonFixture::sign($package), JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
})->with(['open', 'partial', 'unresolved', 'not_received']);

test('JSON v1 canonical Unicode slash and empty lists match Python without normalization', function () {
    expect(SchooltoolAssessmentJson::digest(['slash' => '/', 'line' => "a\u{2028}b", 'emoji' => '🦉', 'empty' => [], 'nested' => ['z' => true, 'a' => null]]))
        ->toBe('6e51934089adf95731ac96b3de01aa8dfe2c969638d6f6c5ca645874d0b329d0');
});

test('JSON v1 matches the original Python checksums and arbitrary criteria', function () {
    $text = file_get_contents(__DIR__.'/../Support/schooltool-json-v1.beispiel.json');
    $package = (new SchooltoolAssessmentJson)->parse($text);
    expect($package['records'][0]['total_minor'])->toBe(475)
        ->and($package['package_checksum'])->toBe('00322662fdee1fb7e3d22200fa7c42516af4f2363c7bf4dde1283acbf6105044');
});

test('JSON v1 accepts signed adjustments and partial and independent open submission states', function (string $state) {
    $package = TeachingWorkJsonFixture::package();
    $record = &$package['records'][0];
    if ($state === 'adjustment') {
        $record['criteria'][0]['earned_minor'] = 500;
        $record['adjustments'][] = ['label' => 'Vereinbarte Anpassung', 'amount_minor' => -25, 'reason' => 'Belegte Regel'];
    } elseif ($state === 'partial') {
        $package['rubric'] = [['criterion' => 'Inhalt', 'maximum_minor' => 300], ['criterion' => 'Form', 'maximum_minor' => 200]];
        $record['criteria'] = [['criterion' => 'Inhalt', 'maximum_minor' => 300, 'earned_minor' => 250, 'checkability' => 'checkable', 'reason' => 'Belegt'], ['criterion' => 'Form', 'maximum_minor' => 200, 'earned_minor' => null, 'checkability' => 'unresolved', 'reason' => 'Offen']];
        $record['evaluation_state'] = 'partial';
        $record['total_minor'] = null;
    } else {
        $record['submission_state'] = $state;
        $record['evaluation_state'] = 'open';
        $record['total_minor'] = null;
        $record['criteria'][0]['earned_minor'] = null;
        $record['criteria'][0]['checkability'] = 'uncheckable';
    }
    unset($record);
    $signed = TeachingWorkJsonFixture::sign($package);
    expect((new SchooltoolAssessmentJson)->parse(json_encode($signed, JSON_THROW_ON_ERROR)))->toBe($signed);
})->with(['adjustment', 'partial', 'received', 'unresolved', 'not_received']);

test('JSON v1 rejects schema and semantic violations', function (string $case) {
    $package = TeachingWorkJsonFixture::package();
    switch ($case) {
        case 'version': $package['schema_version'] = 2;
            break;
        case 'unknown': $package['records'][0]['extra'] = true;
            break;
        case 'missing': unset($package['overview_pdf']);
            break;
        case 'float': $package['records'][0]['total_minor'] = 475.5;
            break;
        case 'decimal': $package['records'][0]['total_minor'] = '4.755';
            break;
        case 'negative': $package['records'][0]['criteria'][0]['earned_minor'] = -1;
            break;
        case 'range': $package['maximum_minor'] = 9007199254740992;
            break;
        case 'maximum': $package['records'][0]['criteria'][0]['earned_minor'] = 501;
            break;
        case 'sum': $package['records'][0]['total_minor'] = 474;
            break;
        case 'adjustment sum': $package['records'][0]['adjustments'][] = ['label' => 'A', 'amount_minor' => 1, 'reason' => 'B'];
            break;
        case 'rubric': $package['records'][0]['criteria'][0]['criterion'] = 'Anderes';
            break;
        case 'unresolved points': $package['records'][0]['criteria'][0]['checkability'] = 'unresolved';
            break;
        case 'uncheckable points': $package['records'][0]['criteria'][0]['checkability'] = 'uncheckable';
            break;
        case 'not received': $package['records'][0]['submission_state'] = 'not_received';
            break;
        case 'partial complete': $package['records'][0]['evaluation_state'] = 'partial';
            $package['records'][0]['total_minor'] = null;
            break;
        case 'open numeric': $package['records'][0]['evaluation_state'] = 'open';
            $package['records'][0]['total_minor'] = null;
            break;
        case 'duplicate person': $package['records'][] = $package['records'][0];
            $package['records'][1]['participant_id'] = 'Second';
            break;
        case 'record limit': $package['records'] = array_fill(0, 100, $package['records'][0]);
            break;
        case 'path': $package['overview_pdf'] = ['filename' => '../report.pdf', 'sha256' => str_repeat('a', 64)];
            break;
    }
    expect(fn () => (new SchooltoolAssessmentJson)->parse(json_encode(TeachingWorkJsonFixture::sign($package), JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
})->with(['version', 'unknown', 'missing', 'float', 'decimal', 'negative', 'range', 'maximum', 'sum', 'adjustment sum', 'rubric', 'unresolved points', 'uncheckable points', 'not received', 'partial complete', 'open numeric', 'duplicate person', 'record limit', 'path']);

test('JSON v1 rejects duplicate escaped keys bad checksums and file limits', function (string $case) {
    $text = file_get_contents(__DIR__.'/../Support/schooltool-json-v1.beispiel.json');
    $text = match ($case) {
        'duplicate' => str_replace('"schema_version": 1,', '"schema_version": 1, "schema_version": 1,', $text),
        'escaped duplicate' => str_replace('"schema_version": 1,', '"schema_version": 1, "schema_versio\\u006e": 1,', $text),
        'nested duplicate' => str_replace('"first_name": "Ada",', '"first_name": "Ada", "first_name": "Ada",', $text),
        'checksum' => str_replace('00322662', '00322663', $text),
        'record checksum' => str_replace('1e000569', '1e000568', $text),
        'BOM' => "\xEF\xBB\xBF".$text,
        'size' => $text.str_repeat(' ', 262144),
        'float integer' => str_replace('"total_minor": 475,', '"total_minor": 475.0,', $text),
    };
    expect(fn () => (new SchooltoolAssessmentJson)->parse($text))->toThrow(ValidationException::class);
})->with(['duplicate', 'escaped duplicate', 'nested duplicate', 'checksum', 'record checksum', 'BOM', 'size', 'float integer']);
