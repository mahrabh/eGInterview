import { sanitizeParticipantTranscript } from './transcriptLanguage';

export type ParticipantSpeaker = 'applicant' | 'candidate';

export interface TranscriptEntry {
  id: string;
  speaker: 'assistant' | ParticipantSpeaker;
  text: string;
  timestamp: number;
  sequence: number;
}

export class TranscriptOrchestrator {
  private entries: TranscriptEntry[] = [];

  private sequence = 0;

  addAssistantFinal(text: string): void {
    this.addFinal('assistant', text);
  }

  addParticipantFinal(text: string, speaker: ParticipantSpeaker): void {
    const sanitized = sanitizeParticipantTranscript(text);
    if (!sanitized) {
      return;
    }

    this.addFinal(speaker, sanitized);
  }

  getEntries(): TranscriptEntry[] {
    return [...this.entries].sort((a, b) => {
      if (a.timestamp !== b.timestamp) {
        return a.timestamp - b.timestamp;
      }

      return a.sequence - b.sequence;
    });
  }

  toSaveFormat(participantLabel: 'Applicant' | 'Candidate'): string {
    return this.getEntries()
      .map((entry) => {
        const label = entry.speaker === 'assistant' ? 'AI' : participantLabel;
        return `${label}: ${entry.text}`;
      })
      .join('\n\n');
  }

  replaceEntries(entries: TranscriptEntry[]): void {
    this.entries = [...entries];
    this.sequence = entries.reduce((max, entry) => Math.max(max, entry.sequence), 0) + 1;
  }

  private addFinal(speaker: TranscriptEntry['speaker'], text: string): void {
    const trimmed = text.trim();
    if (!trimmed) {
      return;
    }

    const last = this.entries[this.entries.length - 1];
    if (last && last.speaker === speaker && last.text === trimmed) {
      return;
    }

    this.entries.push({
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
      speaker,
      text: trimmed,
      timestamp: Date.now(),
      sequence: this.sequence++,
    });
  }
}
