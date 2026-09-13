import {
  cleanAssistantTranscriptText,
  isBanglishOnly,
  isNearDuplicateUtterance,
  preferTranscriptText,
  sanitizeParticipantTranscript,
} from './transcriptLanguage';

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
    const lastApplicant = [...this.entries].reverse().find((entry) => entry.speaker !== 'assistant');
    const cleaned = cleanAssistantTranscriptText(text, lastApplicant?.text ?? null);
    if (!cleaned) {
      return;
    }

    if (this.mergeIfNearDuplicate(cleaned, 'assistant')) {
      return;
    }

    // Always append in arrival order — never splice earlier (that scrambled Q/A).
    this.entries.push(this.makeEntry('assistant', cleaned));
  }

  addParticipantFinal(text: string, speaker: ParticipantSpeaker): void {
    const sanitized = sanitizeParticipantTranscript(text);
    if (!sanitized) {
      return;
    }

    // Prefer an existing Bengali answer over a Banglish remake of the same turn.
    for (let i = this.entries.length - 1; i >= Math.max(0, this.entries.length - 5); i--) {
      const recent = this.entries[i];
      if (recent.speaker !== speaker) {
        continue;
      }

      if (!isNearDuplicateUtterance(recent.text, sanitized)) {
        continue;
      }

      // Already have Bengali; ignore Banglish twin.
      if (!isBanglishOnly(recent.text) && isBanglishOnly(sanitized)) {
        return;
      }

      recent.text = preferTranscriptText(recent.text, sanitized);
      recent.timestamp = Date.now();
      return;
    }

    this.entries.push(this.makeEntry(speaker, sanitized));
  }

  getEntries(): TranscriptEntry[] {
    return [...this.entries];
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

  private mergeIfNearDuplicate(text: string, speaker: TranscriptEntry['speaker']): boolean {
    for (let i = this.entries.length - 1; i >= Math.max(0, this.entries.length - 4); i--) {
      const recent = this.entries[i];
      if (recent.speaker !== speaker) {
        continue;
      }

      if (isNearDuplicateUtterance(recent.text, text)) {
        recent.text = preferTranscriptText(recent.text, text);
        recent.timestamp = Date.now();
        return true;
      }
    }

    return false;
  }

  private makeEntry(speaker: TranscriptEntry['speaker'], text: string): TranscriptEntry {
    return {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
      speaker,
      text,
      timestamp: Date.now(),
      sequence: this.sequence++,
    };
  }
}
