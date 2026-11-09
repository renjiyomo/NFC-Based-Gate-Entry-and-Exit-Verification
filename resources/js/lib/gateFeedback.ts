import type { Operation } from './operations';

export type FeedbackTone = 'success' | 'warning' | 'denied' | 'duplicate';

export function sequenceLabel(value: string): string {
    const labels: Record<string, string> = {
        exit_without_recorded_entry:
            'No earlier Entry is recorded. A missed scan may explain this. Check the person and selected direction.',
        repeated_entry:
            'The previous record is also Entry. An Exit may have been missed. Check the person and selected direction.',
        repeated_exit:
            'The previous record is also Exit. An Entry may have been missed. Check the person and selected direction.',
        previous_day_unclosed_entry:
            'An Entry from an earlier campus day has no recorded Exit. This Entry is saved separately; the earlier gap remains in history.',
        exit_after_earlier_day_entry:
            'The last Entry was on an earlier campus day. This may be an overnight visit or a missed scan. Check with the person.',
        exit_without_entry_today:
            'There is no Entry recorded on the day of this Exit; the previous record is also Exit. Check for a missed scan or wrong direction.',
    };
    return (
        labels[value] ??
        'The recorded sequence needs checking. Review the person and selected direction.'
    );
}

export function gateFeedback(result: Pick<Operation, 'direction' | 'passage' | 'duplicate_of'>): {
    tone: FeedbackTone;
    title: string;
    message: string;
} {
    const direction = result.direction === 'entry' ? 'Entry' : 'Exit';
    if (result.passage?.voided)
        return {
            tone: 'warning',
            title: 'Record voided',
            message:
                'An administrator voided this record. It does not count as an effective passage.',
        };
    if (result.passage)
        return result.passage.sequence_notice
            ? {
                  tone: 'warning',
                  title: `${direction} recorded with warning`,
                  message: sequenceLabel(result.passage.sequence_notice),
              }
            : {
                  tone: 'success',
                  title: `${direction} recorded`,
                  message: 'The scan was saved successfully.',
              };
    if (result.duplicate_of)
        return {
            tone: 'duplicate',
            title: 'Duplicate suppressed',
            message:
                'A matching scan was recorded within the duplicate window. No additional passage was recorded.',
        };
    return {
        tone: 'denied',
        title: 'Not recorded',
        message: 'The credential was rejected. No passage was recorded.',
    };
}

export function playFeedback(context: AudioContext, tone: FeedbackTone): void {
    if (context.state !== 'running') return;
    try {
        const pattern =
            tone === 'warning'
                ? [440, 440]
                : tone === 'denied'
                  ? [180, 140]
                  : tone === 'duplicate'
                    ? [330]
                    : [880];
        pattern.forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = context.currentTime + index * 0.18;
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(0.06, start + 0.01);
            gain.gain.linearRampToValueAtTime(0, start + 0.12);
            oscillator.connect(gain);
            gain.connect(context.destination);
            oscillator.onended = () => {
                oscillator.disconnect();
                gain.disconnect();
            };
            oscillator.start(start);
            oscillator.stop(start + 0.13);
        });
    } catch {
        // Audio is optional and must never affect recording or recovery.
    }
}
