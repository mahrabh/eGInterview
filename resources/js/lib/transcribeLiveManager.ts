import { GoogleGenAI, Modality, LiveServerMessage } from '@google/genai';
import { ParticipantSpeaker, TranscriptOrchestrator } from './transcriptOrchestrator';

export interface LiveTokenResponse {
  token: string;
  liveModel: string;
  transcriptionModel: string;
  expireTime?: string | null;
}

export interface TranscribeLiveManagerOptions {
  participantSpeaker: ParticipantSpeaker;
  languageCodes: string[];
  context: 'loan' | 'recruitment';
  orchestrator: TranscriptOrchestrator;
  onTranscriptChange: () => void;
  onFallbackChange: (usingFallback: boolean) => void;
  /** Optional: control when participant lines are committed (for Q→A order). */
  onParticipantFinal?: (text: string) => void;
}

const TRANSCRIBE_SESSION_LIMIT_MS = 9 * 60 * 1000;

const BANKING_VOCABULARY = [
  'NID',
  'BDT',
  'EMI',
  'DBR',
  'LTV',
  'lakh',
  'lac',
  'crore',
  'income',
  'down payment',
  'personal loan',
  'car loan',
  'home loan',
  'tenure',
  'employer',
  'business',
  'monthly income',
  'existing EMI',
  'loan amount',
  'asset value',
  'টাকা',
  'লাখ',
  'কোটি',
  'ডাউন পেমেন্ট',
  'আয়',
  'কিস্তি',
];

function getCsrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function fetchLiveSessionToken(tokenUrl: string): Promise<LiveTokenResponse> {
  const response = await fetch(tokenUrl, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    throw new Error('Unable to obtain secure live session token.');
  }

  const data = await response.json();

  if (!data?.token || !data?.liveModel || !data?.transcriptionModel) {
    throw new Error('Invalid live session token response.');
  }

  return data as LiveTokenResponse;
}

export function createGeminiLiveClient(token: string): GoogleGenAI {
  // Ephemeral tokens require the Live constrained endpoint (v1alpha).
  return new GoogleGenAI({
    apiKey: token,
    apiVersion: 'v1alpha',
    httpOptions: { apiVersion: 'v1alpha' },
  } as ConstructorParameters<typeof GoogleGenAI>[0]);
}

async function logTranscriptionFallback(context: 'loan' | 'recruitment', reason: string): Promise<void> {
  try {
    await fetch('/live/transcription-fallback', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ context, reason }),
    });
  } catch {
    // Ignore logging failures.
  }
}

export class TranscribeLiveManager {
  private session: any = null;

  private rotationTimer: ReturnType<typeof setTimeout> | null = null;

  private sessionStartedAt = 0;

  private usingFallback = false;

  private lastFinalText = '';

  private isClosed = false;

  constructor(
    private readonly ai: GoogleGenAI,
    private readonly transcriptionModel: string,
    private readonly options: TranscribeLiveManagerOptions,
  ) {}

  isUsingFallback(): boolean {
    return this.usingFallback;
  }

  async start(): Promise<void> {
    await this.openSession();
  }

  async stop(): Promise<void> {
    this.isClosed = true;

    if (this.rotationTimer) {
      clearTimeout(this.rotationTimer);
      this.rotationTimer = null;
    }

    if (this.session) {
      try {
        this.session.sendRealtimeInput({ audioStreamEnd: true });
      } catch {
        // Ignore shutdown errors.
      }

      try {
        this.session.close();
      } catch {
        // Ignore shutdown errors.
      }

      this.session = null;
    }
  }

  sendAudio(base64Pcm: string): void {
    if (this.usingFallback || !this.session) {
      return;
    }

    this.session.sendRealtimeInput({
      audio: {
        mimeType: 'audio/pcm;rate=16000',
        data: base64Pcm,
      },
    });
  }

  private async openSession(): Promise<void> {
    if (this.isClosed || this.usingFallback) {
      return;
    }

    try {
      this.session = await this.ai.live.connect({
        model: this.transcriptionModel,
        config: {
          responseModalities: [Modality.TEXT],
          // Gemini Live rejects languageCodes on the developer API.
          // Keep banking vocabulary hints when the preview model accepts them.
          inputAudioTranscription: {
            ...( {
              mode: 'SMART',
              speechContext: {
                phrases: BANKING_VOCABULARY,
              },
            } as Record<string, unknown> ),
          },
        },
        callbacks: {
          onmessage: (message: LiveServerMessage) => this.handleMessage(message),
          onerror: () => {
            void this.enableFallback('transcribe_session_error');
          },
          onclose: () => {
            if (!this.isClosed && !this.usingFallback) {
              void this.rotateSession('transcribe_session_closed');
            }
          },
        },
      });

      this.sessionStartedAt = Date.now();
      this.scheduleRotation();
    } catch {
      await this.enableFallback('transcribe_connect_failed');
    }
  }

  private scheduleRotation(): void {
    if (this.rotationTimer) {
      clearTimeout(this.rotationTimer);
    }

    this.rotationTimer = setTimeout(() => {
      void this.rotateSession('transcribe_session_limit');
    }, TRANSCRIBE_SESSION_LIMIT_MS);
  }

  private async rotateSession(reason: string): Promise<void> {
    if (this.isClosed || this.usingFallback) {
      return;
    }

    if (this.session) {
      try {
        this.session.sendRealtimeInput({ audioStreamEnd: true });
      } catch {
        // Ignore flush errors during rotation.
      }

      try {
        this.session.close();
      } catch {
        // Ignore close errors during rotation.
      }

      this.session = null;
    }

    await new Promise((resolve) => setTimeout(resolve, 300));
    await this.openSession();

    if (!this.usingFallback && !this.session) {
      await this.enableFallback(reason);
    }
  }

  private handleMessage(message: LiveServerMessage): void {
    const payload = message as any;
    const serverContent = payload.serverContent;

    const interimText = (
      serverContent?.interimInputTranscription?.text
      || serverContent?.inputAudioTranscription?.text
      || ''
    ).trim();

    const finished = Boolean(
      serverContent?.turnComplete
      || serverContent?.inputTranscription?.finished
      || serverContent?.inputAudioTranscription?.finished
      || payload?.inputTranscription?.finished
    );

    // Keep streaming hypotheses quiet until the utterance is marked finished.
    if (interimText && !finished) {
      return;
    }

    const finalText = (
      serverContent?.inputTranscription?.text
      || payload.inputTranscription?.text
      || (finished ? interimText : '')
    )?.trim();

    if (!finalText || finalText === this.lastFinalText) {
      return;
    }

    this.lastFinalText = finalText;

    if (this.options.onParticipantFinal) {
      this.options.onParticipantFinal(finalText);
    } else {
      this.options.orchestrator.addParticipantFinal(finalText, this.options.participantSpeaker);
      this.options.onTranscriptChange();
    }
  }

  private async enableFallback(reason: string): Promise<void> {
    if (this.usingFallback) {
      return;
    }

    this.usingFallback = true;
    this.options.onFallbackChange(true);
    await logTranscriptionFallback(this.options.context, reason);

    if (this.session) {
      try {
        this.session.close();
      } catch {
        // Ignore close errors.
      }

      this.session = null;
    }
  }
}
