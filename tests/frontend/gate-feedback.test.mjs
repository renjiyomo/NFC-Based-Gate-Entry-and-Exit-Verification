import test from 'node:test';
import assert from 'node:assert/strict';
import { gateFeedback, sequenceLabel, playFeedback } from '../../resources/js/lib/gateFeedback.ts';

test('every supported history gap produces a recorded warning, including overnight cases', () => {
    for (const notice of ['repeated_entry', 'repeated_exit', 'exit_without_recorded_entry', 'previous_day_unclosed_entry', 'exit_after_earlier_day_entry', 'exit_without_entry_today']) {
        const result = gateFeedback({direction: 'exit', passage: {sequence_notice: notice}, duplicate_of: null});
        assert.equal(result.tone, 'warning');
        assert.match(result.title, /recorded with warning/);
        assert.ok(result.message.length > 30);
        assert.doesNotMatch(result.message, /misconduct|fraud|denied/);
    }
    assert.match(sequenceLabel('unrecognised'), /needs checking/);
});
test('normal, suppressed, denied and voided results remain distinguishable', () => {
    assert.equal(gateFeedback({direction: 'entry', passage: {sequence_notice: null}, duplicate_of: null}).tone, 'success');
    assert.equal(gateFeedback({direction: 'entry', passage: null, duplicate_of: {}}).tone, 'duplicate');
    assert.equal(gateFeedback({direction: 'entry', passage: null, duplicate_of: null}).tone, 'denied');
    assert.equal(gateFeedback({direction: 'entry', passage: {voided: true}, duplicate_of: null}).tone, 'warning');
});
test('unavailable audio cannot prevent the scan result from being handled', () => {
    assert.doesNotThrow(() => playFeedback({state: 'suspended'}, 'warning'));
    assert.doesNotThrow(() => playFeedback({state: 'running', createOscillator() { throw new Error('Audio unavailable'); }}, 'denied'));
});
