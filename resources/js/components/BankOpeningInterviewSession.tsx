import React, { useEffect, useRef, useState } from 'react';
import {
  Mic,
  Loader2,
  Play,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { Modality, LiveServerMessage } from '@google/genai';
import { InterviewLive } from './InterviewLive';
import { BankOpeningDocumentsPanel } from './BankOpeningDocumentsPanel';
import { TranscriptOrchestrator } from '../lib/transcriptOrchestrator';
import {
  createGeminiLiveClient,
  fetchLiveSessionToken,
  TranscribeLiveManager,
} from '../lib/transcribeLiveManager';
import { LivePcmPlayer, MicCaptureHandle, startMicCapture } from '../lib/liveAudio';

type Stage = 'setup' | 'live' | 'completed';

interface TranscriptEntry {
  id: string;
  speaker: string;
  text: string;
  timestamp: number;
  sequence: number;
}

interface BankOpeningInterviewSessionProps {
  candidateData: any;
  onComplete?: () => void;
}

const CLOSING_MESSAGE_BN =
  "আপনার সময় ও প্রয়োজনীয় তথ্য দেওয়ার জন্য ধন্যবাদ। এখন যাচাই ও ম্যানুয়াল রিভিউয়ের জন্য প্রয়োজনীয় ডকুমেন্ট জমা দিতে হবে। অনুগ্রহ করে 'End Session' বাটনে ক্লিক করে ডকুমেন্ট আপলোড ধাপে যান।";
const CLOSING_MESSAGE_EN =
  'Thank you for your time and for providing the required information. You must now submit documents for verification and manual review. Please click the End Session button to continue to document upload.';

function baoDiag(event: string, payload?: Record<string, unknown>) {
  console.debug('[bao-interview]', event, payload ?? {});
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function uint8ArrayToBase64(bytes: Uint8Array) {
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return window.btoa(binary);
}

function base64ToUint8Array(b64: string) {
  const binaryString = window.atob(b64);
  const bytes = new Uint8Array(binaryString.length);
  for (let i = 0; i < binaryString.length; i++) {
    bytes[i] = binaryString.charCodeAt(i);
  }
  return bytes;
}

function isAssistantInstructionEcho(text: string): boolean {
  const trimmed = text.trim();
  if (!trimmed) return true;
  if (/^for example|^say exactly|^tell them to say/i.test(trimmed)) return true;
  return false;
}

/** Shared markers for spoken/variant closings (canonical + paraphrases). */
const CLOSING_START_PATTERNS: { re: RegExp; isBn: boolean; strong: boolean }[] = [
  { re: /Thank you for your time/i, isBn: false, strong: true },
  { re: /Please click the End Session/i, isBn: false, strong: true },
  { re: /আপনার সম[য়য়]/g, isBn: true, strong: true },
  { re: /আপনার দেওয়া সব তথ্য/g, isBn: true, strong: true },
  { re: /আপনার দেওয়া সব তথ্য/g, isBn: true, strong: true },
  { re: /এখন যাচাই/g, isBn: true, strong: true },
  { re: /ডকুমেন্ট আপলোড ধাপে/g, isBn: true, strong: false },
  { re: /End Session/i, isBn: false, strong: false },
];

function looksLikeClosingSpeech(text: string): boolean {
  return CLOSING_START_PATTERNS.some(({ re }) => {
    re.lastIndex = 0;
    return re.test(text);
  });
}

function findClosingStartPositions(text: string): { index: number; isBn: boolean; strong: boolean }[] {
  const found: { index: number; isBn: boolean; strong: boolean }[] = [];
  for (const { re, isBn, strong } of CLOSING_START_PATTERNS) {
    const flags = re.flags.includes('g') ? re.flags : `${re.flags}g`;
    const global = new RegExp(re.source, flags);
    let match: RegExpExecArray | null;
    while ((match = global.exec(text)) !== null) {
      found.push({ index: match.index, isBn, strong });
      if (match[0].length === 0) global.lastIndex += 1;
    }
  }
  found.sort((a, b) => a.index - b.index || Number(b.strong) - Number(a.strong));
  return found;
}

/** Index where a repeated closing copy begins (same marker twice), else null. */
function findRepeatedClosingIndex(text: string): number | null {
  let earliestSecond: number | null = null;
  for (const { re } of CLOSING_START_PATTERNS) {
    const flags = re.flags.includes('g') ? re.flags : `${re.flags}g`;
    const global = new RegExp(re.source, flags);
    const matches: number[] = [];
    let match: RegExpExecArray | null;
    while ((match = global.exec(text)) !== null) {
      matches.push(match.index);
      if (match[0].length === 0) global.lastIndex += 1;
    }
    if (matches.length >= 2) {
      const second = matches[1];
      if (earliestSecond === null || second < earliestSecond) {
        earliestSecond = second;
      }
    }
  }
  return earliestSecond;
}

function sentenceStartBefore(text: string, index: number): number {
  const before = text.slice(0, index);
  const lastStop = Math.max(
    before.lastIndexOf('।'),
    before.lastIndexOf('.'),
    before.lastIndexOf('?'),
    before.lastIndexOf('!'),
  );
  if (lastStop < 0) return 0;
  let start = lastStop + 1;
  while (start < index && /\s/.test(text[start] || '')) start += 1;
  return start;
}

/**
 * Split pre-closing speech from closing. Always returns the canonical closing once.
 * If the model/STT repeated the closing, the second copy is discarded.
 */
function extractClosingSegment(text: string): { remainder: string; closing: string | null } {
  const starts = findClosingStartPositions(text);
  if (starts.length === 0) {
    return { remainder: text, closing: null };
  }

  // Prefer the earliest strong marker so mid-closing phrases like "এখন যাচাই" are not the cut.
  const strong = starts.filter((s) => s.strong);
  const first = strong[0] || starts[0];
  let cutAt = first.index;
  if (!first.strong) {
    cutAt = sentenceStartBefore(text, first.index);
  }

  const repeatAt = findRepeatedClosingIndex(text);
  const closingSliceEnd = repeatAt !== null && repeatAt > cutAt ? repeatAt : text.length;
  const closingSlice = text.slice(cutAt, closingSliceEnd).trim();
  const isBn =
    first.isBn
    || /[\u0980-\u09FF]/.test(closingSlice)
    || closingSlice.includes('এখন যাচাই')
    || closingSlice.includes('ডকুমেন্ট');

  return {
    remainder: text.slice(0, cutAt).trim(),
    closing: isBn ? CLOSING_MESSAGE_BN : CLOSING_MESSAGE_EN,
  };
}

/** Keep at most one closing copy in the live STT buffer. */
function dedupeClosingBuffer(text: string): string {
  const repeatAt = findRepeatedClosingIndex(text);
  if (repeatAt === null) return text;
  return text.slice(0, repeatAt).trim();
}

export const BankOpeningInterviewSession: React.FC<BankOpeningInterviewSessionProps> = ({
  candidateData,
  onComplete,
}) => {
  const alreadyFinished =
    !!candidateData?.application_submitted
    || !!candidateData?.interview_completed_at
    || candidateData?.interview_state === 'completed'
    || candidateData?.phase === 'completed';

  const [stage, setStage] = useState<Stage>(alreadyFinished ? 'completed' : 'setup');
  const [interviewData, setInterviewData] = useState<any>(candidateData);
  const [documents, setDocuments] = useState<any[]>(candidateData?.documents || []);
  const [applicationSubmitted, setApplicationSubmitted] = useState(!!candidateData?.application_submitted);

  const [isConnected, setIsConnected] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isMuted, setIsMuted] = useState(false);
  const [transcript, setTranscript] = useState<TranscriptEntry[]>([]);
  const [audioLevel, setAudioLevel] = useState(0);
  const [hasEverConnected, setHasEverConnected] = useState(false);
  const [hasPermissions, setHasPermissions] = useState(false);
  const [isRequestingPermissions, setIsRequestingPermissions] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [hasAgreed, setHasAgreed] = useState(false);
  const [wrapUpState, setWrapUpState] = useState<'active' | 'closing' | 'closing_done' | 'saving' | 'submitted'>('active');
  const [closingCountdown, setClosingCountdown] = useState<number | null>(null);

  const wrapUpStateRef = useRef(wrapUpState);
  const isMutedRef = useRef(false);
  const intentionalCloseRef = useRef(false);
  const closingRequestedRef = useRef(false);
  const closingDeliveredRef = useRef(false);
  const closingTranscriptPushedRef = useRef(false);
  const closingTurnCompleteRef = useRef(false);
  const autoEndTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closingCountdownIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const closingFinalizeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closingThanksHeardRef = useRef(false);
  const timeNudgeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const urgentNudgeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const hardLimitTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const videoRef = useRef<HTMLVideoElement>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const micCaptureRef = useRef<MicCaptureHandle | null>(null);
  const pcmPlayerRef = useRef<LivePcmPlayer | null>(null);
  const sessionRef = useRef<any>(null);
  const transcriptEndRef = useRef<HTMLDivElement>(null);
  const transcriptRef = useRef<TranscriptEntry[]>([]);
  const orchestratorRef = useRef(new TranscriptOrchestrator());
  const transcribeManagerRef = useRef<TranscribeLiveManager | null>(null);
  const usingTranscribeFallbackRef = useRef(false);
  const pendingAssistantTextRef = useRef('');
  const pendingApplicantLiveRef = useRef('');
  const assistantTurnCompleteRef = useRef(false);
  const assistantFlushTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const applicantSourceRef = useRef<'unset' | 'transcribe' | 'live_input'>('unset');
  const sessionStartLockRef = useRef(false);
  const greetingSeededRef = useRef(false);
  const stopInterviewRef = useRef<() => Promise<void>>(async () => {});

  const token = interviewData?.token || interviewData?.public_url || candidateData?.token || '';

  const CLOSING_AUTO_END_MS = 5000;
  const INTERVIEW_TIME_NUDGE_MS = 150000;
  const INTERVIEW_URGENT_NUDGE_MS = 240000;
  const INTERVIEW_LONG_NUDGE_MS = 360000;

  useEffect(() => {
    wrapUpStateRef.current = wrapUpState;
  }, [wrapUpState]);

  useEffect(() => {
    isMutedRef.current = isMuted;
  }, [isMuted]);

  useEffect(() => {
    transcriptRef.current = transcript;
  }, [transcript]);

  useEffect(() => {
    if (videoRef.current && localStreamRef.current) {
      videoRef.current.srcObject = localStreamRef.current;
    }
  }, [hasPermissions, isConnected, stage]);

  useEffect(() => {
    transcriptEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [transcript.length]);

  useEffect(() => {
    if (Array.isArray(candidateData?.documents)) {
      setDocuments(candidateData.documents);
    }
    if (candidateData?.application_submitted || candidateData?.interview_completed_at) {
      setApplicationSubmitted(!!candidateData?.application_submitted);
      setStage('completed');
    }
  }, [candidateData]);

  useEffect(() => () => {
    intentionalCloseRef.current = true;
    try {
      sessionRef.current?.close();
    } catch {
      /* ignore */
    }
    sessionRef.current = null;
    void transcribeManagerRef.current?.stop();
    transcribeManagerRef.current = null;
    if (localStreamRef.current) {
      localStreamRef.current.getTracks().forEach((t) => t.stop());
      localStreamRef.current = null;
    }
    micCaptureRef.current?.stop();
    micCaptureRef.current = null;
    pcmPlayerRef.current?.stop();
    pcmPlayerRef.current = null;
    if (assistantFlushTimerRef.current) clearTimeout(assistantFlushTimerRef.current);
    if (autoEndTimerRef.current) clearTimeout(autoEndTimerRef.current);
    if (closingCountdownIntervalRef.current) clearInterval(closingCountdownIntervalRef.current);
    if (closingFinalizeTimerRef.current) clearTimeout(closingFinalizeTimerRef.current);
    if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
    if (urgentNudgeTimerRef.current) clearTimeout(urgentNudgeTimerRef.current);
    if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);
  }, []);

  const syncTranscript = () => {
    const next = orchestratorRef.current.getEntries().map((e) => ({
      id: e.id,
      speaker: e.speaker,
      text: e.text,
      timestamp: e.timestamp,
      sequence: e.sequence,
    }));
    transcriptRef.current = next;
    setTranscript(next);
  };

  const isApplicantClosingThanks = (text: string): boolean =>
    /(?:^|\b)(thank(?:s|\s+you)?|thx|ধন্যবাদ|শুকরিয়া|শুক্রিয়া)(?:\b|$)/i.test(text.trim());

  const clearClosingTimers = () => {
    if (autoEndTimerRef.current) {
      clearTimeout(autoEndTimerRef.current);
      autoEndTimerRef.current = null;
    }
    if (closingCountdownIntervalRef.current) {
      clearInterval(closingCountdownIntervalRef.current);
      closingCountdownIntervalRef.current = null;
    }
    if (closingFinalizeTimerRef.current) {
      clearTimeout(closingFinalizeTimerRef.current);
      closingFinalizeTimerRef.current = null;
    }
    setClosingCountdown(null);
  };

  const scheduleAutoEnd = () => {
    clearClosingTimers();
    let remaining = Math.ceil(CLOSING_AUTO_END_MS / 1000);
    setClosingCountdown(remaining);
    closingCountdownIntervalRef.current = setInterval(() => {
      remaining -= 1;
      setClosingCountdown(remaining > 0 ? remaining : null);
      if (remaining <= 0 && closingCountdownIntervalRef.current) {
        clearInterval(closingCountdownIntervalRef.current);
        closingCountdownIntervalRef.current = null;
      }
    }, 1000);
    autoEndTimerRef.current = setTimeout(() => {
      void stopInterviewRef.current();
    }, CLOSING_AUTO_END_MS);
  };

  /** Close Live only after the closing audio has fully finished (not mid-speech). */
  const closeLiveModelSession = () => {
    if (!sessionRef.current) return;
    intentionalCloseRef.current = true;
    try {
      sessionRef.current.sendRealtimeInput({ audioStreamEnd: true });
    } catch {
      /* ignore */
    }
    try {
      sessionRef.current.close();
    } catch {
      /* ignore */
    }
    sessionRef.current = null;
  };

  const markClosingSpeechStarted = () => {
    if (closingRequestedRef.current) return;
    closingRequestedRef.current = true;
    setWrapUpState((prev) => (prev === 'active' ? 'closing' : prev));
    // Do NOT close Live here — remaining closing PCM would be cut off.
    // Mic → Live is already blocked while closingRequested (see startAudioCapture).
  };

  const finalizeClosingPhase = () => {
    if (closingDeliveredRef.current) return;
    // Never finalize while closing audio is still playing.
    if (pcmPlayerRef.current?.isPlaying()) return;

    closingRequestedRef.current = true;
    closingDeliveredRef.current = true;
    setWrapUpState('closing_done');
    pendingAssistantTextRef.current = '';
    closeLiveModelSession();

    // Thanks during/after closing: end immediately once speech finished; else 5s countdown.
    if (closingThanksHeardRef.current) {
      void stopInterviewRef.current();
      return;
    }
    scheduleAutoEnd();
  };

  /** Wait briefly after idle so late PCM chunks of the same closing can still enqueue. */
  const scheduleFinalizeClosingPhase = () => {
    if (closingDeliveredRef.current || !closingRequestedRef.current) return;
    if (closingFinalizeTimerRef.current) clearTimeout(closingFinalizeTimerRef.current);
    closingFinalizeTimerRef.current = setTimeout(() => {
      closingFinalizeTimerRef.current = null;
      if (pcmPlayerRef.current?.isPlaying()) return;
      finalizeClosingPhase();
    }, 700);
  };

  const noteClosingAudioChunk = () => {
    // New closing audio arrived — cancel a premature finalize from a mid-stream idle gap.
    if (closingFinalizeTimerRef.current) {
      clearTimeout(closingFinalizeTimerRef.current);
      closingFinalizeTimerRef.current = null;
    }
  };

  const pushAssistantFinal = (text: string) => {
    const cleaned = text.trim();
    if (!cleaned || isAssistantInstructionEcho(cleaned)) return;
    // Never append a second closing (or any post-closing AI speech) to the live transcript.
    if (closingDeliveredRef.current) return;
    if (closingTranscriptPushedRef.current && looksLikeClosingSpeech(cleaned)) return;
    orchestratorRef.current.addAssistantFinal(cleaned);
    syncTranscript();
  };

  const flushAssistantTranscript = () => {
    const raw = pendingAssistantTextRef.current.trim();
    pendingAssistantTextRef.current = '';
    assistantTurnCompleteRef.current = false;
    if (assistantFlushTimerRef.current) {
      clearTimeout(assistantFlushTimerRef.current);
      assistantFlushTimerRef.current = null;
    }
    if (!raw) return;
    if (closingDeliveredRef.current) return;

    const { remainder, closing } = extractClosingSegment(dedupeClosingBuffer(raw));
    const shortAckOnly = /^(ধন্যবাদ\.?|শুকরিয়া\.?|শুক্রিয়া\.?|thank you\.?|thanks\.?)$/i.test(
      (remainder || '').trim(),
    );
    if (remainder && !looksLikeClosingSpeech(remainder) && !shortAckOnly) {
      pushAssistantFinal(remainder);
    } else if (remainder && !closing && looksLikeClosingSpeech(remainder)) {
      // Remainder itself is closing-like without a clean split — use canonical only.
      if (!closingTranscriptPushedRef.current) {
        pushAssistantFinal(
          /[\u0980-\u09FF]/.test(remainder) ? CLOSING_MESSAGE_BN : CLOSING_MESSAGE_EN,
        );
        closingTranscriptPushedRef.current = true;
        markClosingSpeechStarted();
      }
      return;
    }

    if (closing) {
      if (!closingTranscriptPushedRef.current) {
        pushAssistantFinal(closing);
        closingTranscriptPushedRef.current = true;
      }
      markClosingSpeechStarted();
      // Auto-end only after playback idle — never finalize here (audio may still be streaming).
    } else if (closingRequestedRef.current && !closingTranscriptPushedRef.current) {
      pushAssistantFinal(
        /[\u0980-\u09FF]/.test(raw) || raw.includes('এখন যাচাই') ? CLOSING_MESSAGE_BN : CLOSING_MESSAGE_EN,
      );
      closingTranscriptPushedRef.current = true;
    }
  };

  const tryFlushAssistantTranscript = () => {
    if (!assistantTurnCompleteRef.current) return;
    if (pcmPlayerRef.current?.isPlaying()) return;
    flushAssistantTranscript();
  };

  const bufferAssistantText = (text: string) => {
    if (!text || closingDeliveredRef.current) return;
    // After one closing bubble is on the transcript, ignore further model STT.
    if (closingTranscriptPushedRef.current) return;

    pendingAssistantTextRef.current = dedupeClosingBuffer(
      `${pendingAssistantTextRef.current} ${text}`.trim(),
    );
    // As soon as closing language appears, stop feeding mic to Live (repeat prevention).
    if (looksLikeClosingSpeech(pendingAssistantTextRef.current)) {
      markClosingSpeechStarted();
    }
    if (assistantFlushTimerRef.current) clearTimeout(assistantFlushTimerRef.current);
    assistantFlushTimerRef.current = setTimeout(() => tryFlushAssistantTranscript(), 500);
  };

  const commitApplicantFinal = (text: string, source: 'transcribe' | 'live_input') => {
    const cleaned = text.trim();
    if (!cleaned) return;

    // After closing starts: ignore applicant text for the AI.
    // Thanks ends the session only after closing audio has finished (or immediately if already done).
    if (closingDeliveredRef.current || closingRequestedRef.current) {
      if (isApplicantClosingThanks(cleaned)) {
        closingThanksHeardRef.current = true;
        if (closingDeliveredRef.current) {
          void stopInterviewRef.current();
        }
        // else: finalizeClosingPhase will end immediately once speech completes
      }
      return;
    }

    if (applicantSourceRef.current === 'unset') {
      applicantSourceRef.current = source;
    } else if (applicantSourceRef.current !== source && source === 'live_input') {
      if (applicantSourceRef.current === 'transcribe') {
        return;
      }
    }

    orchestratorRef.current.addParticipantFinal(cleaned, 'applicant');
    syncTranscript();
  };

  const bufferApplicantLiveInput = (text: string) => {
    if (!text) return;
    if (closingDeliveredRef.current || closingRequestedRef.current) {
      if (isApplicantClosingThanks(text)) {
        closingThanksHeardRef.current = true;
        if (closingDeliveredRef.current) {
          void stopInterviewRef.current();
        }
      }
      return;
    }
    pendingApplicantLiveRef.current = text;
  };

  const finalizeLiveInputApplicant = () => {
    const text = pendingApplicantLiveRef.current.trim();
    pendingApplicantLiveRef.current = '';
    if (!text) return;
    if (usingTranscribeFallbackRef.current || applicantSourceRef.current === 'live_input' || applicantSourceRef.current === 'unset') {
      commitApplicantFinal(text, 'live_input');
    }
  };

  const nudgeIncompleteChecklist = (message: string) => {
    if (!sessionRef.current || closingDeliveredRef.current || closingRequestedRef.current) return;
    try {
      sessionRef.current.sendRealtimeInput({ text: message });
    } catch {
      /* ignore */
    }
  };

  const requestMediaAccess = async () => {
    setIsRequestingPermissions(true);
    setPermissionError(null);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { width: 1280, height: 720 },
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
      });
      localStreamRef.current = stream;
      if (videoRef.current) videoRef.current.srcObject = stream;
      setHasPermissions(true);
    } catch (err) {
      console.error('Media access error:', err);
      setPermissionError('Could not access camera or microphone. Please check permissions.');
    } finally {
      setIsRequestingPermissions(false);
    }
  };

  const buildSystemInstruction = (data: any) => {
    const applicantName = (data?.candidate_name || data?.applicant_name || 'Applicant').trim();
    const applicantFirstName = (data?.first_name || applicantName.split(/\s+/)[0] || applicantName).trim();
    const guide = data?.live_interview_guide || '';

    return `CRITICAL LANGUAGE RULE:
You are allowed to speak ONLY in:
- English
- Bengali (Bangla, native script)

LANGUAGE STICKINESS (critical):
- After the greeting, lock to the applicant's active language for the rest of the interview.
- If they are speaking English (or ask "speak in English"), reply in English only.
- If they are speaking Bengali script (or ask "বাংলায় বলুন"), reply in Bengali script only.
- NEVER switch language only because they asked you to repeat — repeat in the SAME language, unless they explicitly ask to switch.
- If language is still unclear after the readiness answer, ask ONCE: "Would you like to continue in English or Bengali?" then lock to their choice.
- NEVER use Banglish (romanized Bangla). NEVER switch to Hindi or Urdu.

------------------------------------------------------------

THIS IS A LIVE VOICE INTERVIEW — NOT A FORM:
- NEVER say "write", "type", "fill in", "enter", or Bengali equivalents like "লিখুন", "টাইপ করুন", "পূরণ করুন"
- ALWAYS say "tell me", "say", "let me know", or Bengali "বলুন", "জানান", "উত্তর দিন"
- There is NO on-screen account-type picker and NO language form. Everything happens by voice.

------------------------------------------------------------

IDENTITY:
You are a professional UCB Bank account-opening agent AI helping the applicant open an account securely.
Say "UCB Bank" only in the greeting or when confirming the bank name. Never say "NRB Bank".
Say "account type" — never say "product".
"UCB NRB Savings" is a valid account type name (NRB = Non-Resident Bangladeshi).

APPLICANT INFO (use in greeting):
Full name: ${applicantName}
First name for greeting: ${applicantFirstName}

------------------------------------------------------------

ACCOUNT CATALOG & PROFILE QUESTIONS (internal — do NOT read this block aloud verbatim):
${guide}

------------------------------------------------------------

INTERVIEW FLOW

STEP 1 — GREETING (first turn only — STRICT ORDER)
- Your FIRST spoken words MUST be English: "Hello ${applicantFirstName} Sir,"
- Exact preferred first turn:
  "Hello ${applicantFirstName} Sir, I am a professional UCB Bank account-opening agent AI. I am here to help you open an account securely. Are you ready to begin?"
- Do NOT open with নমস্কার or any non-English first word
- After the applicant replies, match their language (Bengali script or English) and KEEP that language
- Ask readiness ONCE only. If they already said yes/ready/প্রস্তুত, do NOT ask again — move to account type
- STOP and WAIT for their answer

STEP 2 — ACCOUNT TYPE (spoken only)
- Ask what kind of account they want to open
- You know every account type in the catalog above
- NEVER recommend, suggest, or rank account types. Never say best, ideal, or good fit
- If they give a generic answer (e.g. "savings"), briefly list matching options from the catalog and ask them to pick ONE
- Confirm the chosen account type in one short spoken confirmation, then WAIT
- Only after they confirm, continue with that account type's question profile

STEP 3 — PROFILE QUESTIONS (strictly one at a time)
- Ask ONLY the questions for the confirmed account type's profile, IN ORDER
- Ask EXACTLY ONE question per turn, then STOP and WAIT
- Skip any item the applicant already answered clearly
- NEVER ask two checklist items in the same turn
- NEVER repeat a question they already answered clearly
- Ask follow-up ONLY when an answer is ambiguous, contradictory, or a range — one short clarification only
- Keep opening deposit separate from monthly deposit / turnover / remittance
- AMOUNTS (verified table in the catalog guide — mandatory):
  - Follow the "UCB BANK: OPENING DEPOSIT & MONTHLY / REMITTANCE VALIDATION" block exactly
  - When asking opening_deposit or monthly/remittance/turnover: briefly state the verified minimum for the confirmed account type, then WAIT
  - If the applicant says an amount BELOW that minimum (including absurd amounts like BDT 10 or 20): REJECT it, state the minimum again, and re-ask — do NOT accept it and do NOT close
  - If they ask for a suggested/required opening amount: answer with the verified floor for that account — NEVER say "there is no suggested amount" when a floor exists
  - NEVER invent amounts outside the verified table
  - For Sabuj Shanchay, also reject monthly amounts above BDT 100,000

STEP 3.5 — VERIFY BEFORE CLOSING (internal — do NOT read aloud)
Before STEP 4, confirm:
□ Account type chosen and confirmed
□ Every question in that profile has a clear applicant answer
□ Opening and monthly/remittance amounts (when asked) meet the verified floors for that account

If ANY box is unchecked, ask that ONE missing item now and WAIT. Do NOT close.

STEP 4 — CLOSING (ONLY when STEP 3.5 passes)
- Give ONLY the closing message — no questions after it
- The closing MUST mention document submission for verification BEFORE telling them to click End Session
- English (say exactly once): "${CLOSING_MESSAGE_EN}"
- Bengali (say exactly once): "${CLOSING_MESSAGE_BN}"
- Say the closing message EXACTLY ONCE — do not paraphrase it twice, do not append it again in the same turn, and never repeat it in a later turn.
- After the closing message, STOP COMPLETELY. Remain silent forever for this session.
- If the applicant says thank you / ধন্যবাদ / anything else after closing: do NOT reply, do NOT repeat the closing, do NOT ask questions.
- NEVER tell the applicant to click End Session before STEP 4

If the applicant explicitly wants to stop:
- Acknowledge briefly, then say EXACTLY: "Certainly. You will still need to submit documents for verification. Please click the End Session button to continue to document upload."

START NOW:
Begin with exactly: "Hello ${applicantFirstName} Sir," then the professional introduction and readiness question. Then WAIT.`;
  };

  const startAudioCapture = async () => {
    const stream = localStreamRef.current;
    if (!stream) return;

    micCaptureRef.current?.stop();
    micCaptureRef.current = await startMicCapture(
      stream,
      (pcmData) => {
        if (isMutedRef.current) return;

        const base64Pcm = uint8ArrayToBase64(new Uint8Array(pcmData.buffer));
        // After closing starts, keep STT only so we can detect "thanks" and end early —
        // never send more audio into the Live conversation model (prevents repeated closing).
        if (closingDeliveredRef.current || closingRequestedRef.current) {
          transcribeManagerRef.current?.sendAudio(base64Pcm);
          return;
        }

        transcribeManagerRef.current?.sendAudio(base64Pcm);
        if (!pcmPlayerRef.current?.isPlaying() && sessionRef.current) {
          sessionRef.current.sendRealtimeInput({
            audio: { mimeType: 'audio/pcm;rate=16000', data: base64Pcm },
          });
        }
      },
      (level) => setAudioLevel(level),
    );
  };

  const handleAssistantPlaybackIdle = () => {
    const closingInProgress = closingRequestedRef.current && !closingDeliveredRef.current;
    tryFlushAssistantTranscript();
    finalizeLiveInputApplicant();
    // Only after closing audio has drained (and a short settle) — never mid-speech.
    if (closingInProgress && !closingDeliveredRef.current) {
      scheduleFinalizeClosingPhase();
    }
  };

  const startLiveInterview = async () => {
    if (sessionStartLockRef.current || isConnecting || sessionRef.current) {
      baoDiag('live_start_blocked', {
        lock: sessionStartLockRef.current,
        connecting: isConnecting,
        hasSession: !!sessionRef.current,
      });
      return;
    }

    if (!hasPermissions || !localStreamRef.current) return;
    if (!token) {
      setConnectionError('Missing interview identifier.');
      return;
    }

    sessionStartLockRef.current = true;
    setIsConnecting(true);
    setConnectionError(null);
    baoDiag('live_start', {});

    try {
      const tokenData = await fetchLiveSessionToken(`/bank-opening/${token}/live-token`);
      const ai = createGeminiLiveClient(tokenData.token);

      pcmPlayerRef.current?.stop();
      pcmPlayerRef.current = new LivePcmPlayer(handleAssistantPlaybackIdle);

      const data = interviewData;
      const applicantName = (data?.candidate_name || data?.applicant_name || 'Applicant').trim();
      const applicantFirstName = (data?.first_name || applicantName.split(/\s+/)[0] || applicantName).trim();
      const systemInstruction = buildSystemInstruction(data);

      const session = await ai.live.connect({
        model: tokenData.liveModel,
        config: {
          responseModalities: [Modality.AUDIO],
          systemInstruction,
          temperature: 0.2,
          topP: 0.8,
          speechConfig: {
            languageCode: 'en-US',
            voiceConfig: { prebuiltVoiceConfig: { voiceName: 'Aoede' } },
          },
          outputAudioTranscription: {},
          inputAudioTranscription: {},
        },
        callbacks: {
          onopen: () => {
            setIsConnected(true);
            setHasEverConnected(true);
            setIsConnecting(false);
            setConnectionError(null);
            setStage('live');
            baoDiag('live_open', {});

            timeNudgeTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                'Time check: about 2 minutes 30 seconds have passed. Confirm account type if still missing, then ask any remaining profile questions — ONE per turn. Do NOT give the closing message until every required item has a clear applicant answer.',
              );
            }, INTERVIEW_TIME_NUDGE_MS);

            urgentNudgeTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                'Urgent: interview is running long. Finish account-type confirmation and remaining profile questions ONE at a time. Do NOT close until all items are answered.',
              );
            }, INTERVIEW_URGENT_NUDGE_MS);

            hardLimitTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                'Long interview reminder: continue asking remaining mandatory items ONE at a time. NEVER give the closing message until the applicant has clearly answered every required item.',
              );
            }, INTERVIEW_LONG_NUDGE_MS);
          },
          onmessage: async (message: LiveServerMessage) => {
            // After closing is finalized, ignore everything (no repeated closing).
            if (closingDeliveredRef.current) {
              return;
            }

            const serverContent = (message as any).serverContent;
            if (!serverContent) return;

            const base64Audio = serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
            if (base64Audio) {
              // After the first closing turn completes, drop a second spoken closing.
              // Still allow late PCM of the first turn while it is playing.
              const dropSecondClosingAudio =
                closingTurnCompleteRef.current && !pcmPlayerRef.current?.isPlaying();
              if (!dropSecondClosingAudio) {
                noteClosingAudioChunk();
                const pcmData = new Int16Array(base64ToUint8Array(base64Audio).buffer);
                pcmPlayerRef.current?.enqueue(pcmData);
              }
            }

            let userText = '';
            if (serverContent.userTurn?.parts) {
              userText = serverContent.userTurn.parts.map((p: any) => p.text || '').join('').trim();
            }
            if (!userText) {
              userText = serverContent.inputAudioTranscription?.text
                || serverContent.inputTranscription?.text
                || '';
            }
            if (userText) {
              bufferApplicantLiveInput(userText);
            }

            let aiText = '';
            if (serverContent.modelTurn?.parts) {
              aiText = serverContent.modelTurn.parts.map((p: any) => p.text || '').join('').trim();
            }
            if (!aiText) {
              aiText = serverContent.outputAudioTranscription?.text
                || serverContent.outputTranscription?.text
                || '';
            }
            if (aiText) bufferAssistantText(aiText);

            if (serverContent.turnComplete) {
              assistantTurnCompleteRef.current = true;
              if (closingRequestedRef.current) {
                closingTurnCompleteRef.current = true;
              }
              tryFlushAssistantTranscript();
              if (!pcmPlayerRef.current?.isPlaying()) {
                finalizeLiveInputApplicant();
                if (closingRequestedRef.current && !closingDeliveredRef.current) {
                  scheduleFinalizeClosingPhase();
                }
              }
            }
          },
          onclose: () => {
            if (intentionalCloseRef.current) {
              intentionalCloseRef.current = false;
              sessionRef.current = null;
              sessionStartLockRef.current = false;
              return;
            }
            sessionRef.current = null;
            sessionStartLockRef.current = false;
            setIsConnected(false);
            if (!hasEverConnected) {
              setConnectionError('Connection closed unexpectedly. Please try again.');
              setIsConnecting(false);
              setStage('setup');
            }
          },
          onerror: (error: any) => {
            console.error('Gemini Live API Error:', error);
            sessionStartLockRef.current = false;
            if (!hasEverConnected) {
              setConnectionError(`Connection failed: ${error.message || 'Unknown error'}`);
              setIsConnecting(false);
              setIsConnected(false);
              sessionRef.current = null;
              setStage('setup');
            }
          },
        },
      });

      sessionRef.current = session;
      await startAudioCapture();

      if (!greetingSeededRef.current) {
        const greetingText =
          `Hello ${applicantFirstName} Sir, I am a professional UCB Bank account-opening agent AI. I am here to help you open an account securely. Are you ready to begin?`;
        greetingSeededRef.current = true;
        orchestratorRef.current.addAssistantFinal(greetingText);
        syncTranscript();
        session.sendRealtimeInput({
          text: `First turn only: say exactly this greeting once, then STOP and WAIT:\n"${greetingText}"`,
        });
        baoDiag('greeting_seeded', { name: applicantFirstName });
      }

      void (async () => {
        try {
          const transcribeToken = await fetchLiveSessionToken(`/bank-opening/${token}/live-token`);
          const transcribeAi = createGeminiLiveClient(transcribeToken.token);
          transcribeManagerRef.current = new TranscribeLiveManager(
            transcribeAi,
            transcribeToken.transcriptionModel,
            {
              participantSpeaker: 'applicant',
              languageCodes: ['bn-BD', 'en-US'],
              context: 'bank_opening',
              orchestrator: orchestratorRef.current,
              onTranscriptChange: () => syncTranscript(),
              onParticipantFinal: (text) => {
                void commitApplicantFinal(text, 'transcribe');
              },
              onFallbackChange: (usingFallback) => {
                usingTranscribeFallbackRef.current = usingFallback;
                if (usingFallback && applicantSourceRef.current === 'unset') {
                  applicantSourceRef.current = 'live_input';
                }
              },
            },
          );
          await transcribeManagerRef.current.start();
          // Do not lock applicantSource to 'transcribe' until a final arrives —
          // otherwise quiet STT permanently blocks live_input fallback.
        } catch {
          usingTranscribeFallbackRef.current = true;
          if (applicantSourceRef.current === 'unset') {
            applicantSourceRef.current = 'live_input';
          }
        }
      })();
    } catch (err: any) {
      console.error('Live connection init failed:', err);
      sessionStartLockRef.current = false;
      setIsConnecting(false);
      setStage('setup');
      setConnectionError(`Failed to initialize connection: ${err.message || 'Unknown error'}`);
    }
  };

  const teardownLiveSession = (fullMediaRelease: boolean) => {
    intentionalCloseRef.current = true;
    clearClosingTimers();
    if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
    if (urgentNudgeTimerRef.current) clearTimeout(urgentNudgeTimerRef.current);
    if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);

    try {
      sessionRef.current?.sendRealtimeInput({ audioStreamEnd: true });
    } catch {
      /* ignore */
    }
    try {
      sessionRef.current?.close();
    } catch {
      /* ignore */
    }
    sessionRef.current = null;
    sessionStartLockRef.current = false;
    void transcribeManagerRef.current?.stop();
    transcribeManagerRef.current = null;
    micCaptureRef.current?.stop();
    micCaptureRef.current = null;
    pcmPlayerRef.current?.stop();
    pcmPlayerRef.current = null;
    setIsConnected(false);
    setIsConnecting(false);

    if (fullMediaRelease && localStreamRef.current) {
      localStreamRef.current.getTracks().forEach((t) => t.stop());
      localStreamRef.current = null;
    }
  };

  const persistCompleteInBackground = (transcriptText: string) => {
    if (!token || !transcriptText.trim()) return;

    void fetch(`/bank-opening/${token}/interview/complete-live`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ transcript_text: transcriptText }),
      credentials: 'same-origin',
      keepalive: true,
    }).then(async (response) => {
      if (!response.ok) {
        console.error('Background live complete failed.', response.status);
        return;
      }
      const data = await response.json().catch(() => ({}));
      if (data && typeof data === 'object') {
        setInterviewData((prev: any) => ({ ...prev, ...data, token: data.token || prev?.token || token }));
        if (Array.isArray(data.documents)) setDocuments(data.documents);
      }
      baoDiag('live_complete_saved', { interview_state: data?.interview_state });
    }).catch((error) => {
      console.error('Background live complete error.', error);
    });
  };

  const stopInterview = async () => {
    if (wrapUpStateRef.current === 'saving' || wrapUpStateRef.current === 'submitted') {
      return;
    }

    setIsSaving(true);
    setWrapUpState('saving');

    if (pendingAssistantTextRef.current.trim()) flushAssistantTranscript();
    finalizeLiveInputApplicant();

    const transcriptText = orchestratorRef.current.toSaveFormat('Applicant');
    teardownLiveSession(true);
    setIsMuted(true);
    setIsSaving(false);

    // Show docs thank-you immediately — do not wait on network (loan pattern).
    setStage('completed');
    setWrapUpState('submitted');
    onComplete?.();

    persistCompleteInBackground(transcriptText);
  };
  stopInterviewRef.current = stopInterview;

  // —— Documents (account-type groups) ——
  if (stage === 'completed') {
    const requirements = Array.isArray(interviewData?.document_requirements)
      ? interviewData.document_requirements
      : [];
    const accountOptions = Array.isArray(interviewData?.account_type_options)
      ? interviewData.account_type_options
      : [];

    return (
      <BankOpeningDocumentsPanel
        token={token}
        applicantName={interviewData?.candidate_name || interviewData?.applicant_name || 'Applicant'}
        accountType={interviewData?.account_type || null}
        accountLabel={interviewData?.account_label || interviewData?.account_type_name_snapshot || null}
        accountConfirmed={!!interviewData?.account_confirmed || !!interviewData?.account_type_confirmed}
        accountTypeOptions={accountOptions}
        requirements={requirements}
        documents={documents}
        applicationSubmitted={applicationSubmitted}
        resubmissionReason={interviewData?.resubmission?.reason || null}
        completionMessage={interviewData?.completion_message || null}
        documentsWindowHours={interviewData?.documents_window_hours || 24}
        publicTokenExpiry={interviewData?.public_token_expiry || null}
        documentsWindowExpiresAt={interviewData?.documents_window_expires_at || null}
        csrfToken={csrfToken()}
        onBootstrapUpdate={(data) => {
          if (!data || typeof data !== 'object') return;
          setInterviewData((prev: any) => ({
            ...prev,
            ...data,
            token: data.token || prev?.token || token,
            account_confirmed: data.account_confirmed ?? data.account_type_confirmed ?? prev?.account_confirmed,
          }));
          if (Array.isArray(data.documents)) setDocuments(data.documents);
        }}
        onDocumentsChange={setDocuments}
        onSubmitted={() => setApplicationSubmitted(true)}
      />
    );
  }

  // —— Setup (permissions + consent) — same pattern as loan ——
  if (stage === 'setup') {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-950 text-white p-6 font-sans">
        {!hasPermissions ? (
          <div className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center space-y-6 shadow-2xl">
            <div className="w-20 h-20 bg-blue-500/10 rounded-2xl flex items-center justify-center mx-auto text-blue-500">
              <Mic className="w-10 h-10" />
            </div>
            <div className="space-y-2">
              <h3 className="text-xl font-bold">Ready to start?</h3>
              <p className="text-sm text-slate-400">
                You will start a short live spoken interview to open your account. We need access to your camera and microphone to begin.
              </p>
            </div>
            {permissionError && (
              <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-xs text-red-400">{permissionError}</div>
            )}
            <button
              onClick={requestMediaAccess}
              disabled={isRequestingPermissions}
              className="w-full py-4 bg-blue-600 hover:bg-blue-500 rounded-2xl font-bold flex items-center justify-center gap-2 transition-all"
            >
              {isRequestingPermissions ? <Loader2 className="w-5 h-5 animate-spin" /> : <CheckCircle2 className="w-5 h-5" />}
              Grant Permissions
            </button>
          </div>
        ) : (
          <div className="relative w-full max-w-2xl aspect-video bg-slate-900 rounded-3xl overflow-hidden border border-slate-800 shadow-2xl">
            <video ref={videoRef} autoPlay muted playsInline className="w-full h-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent flex flex-col items-center justify-end p-8">
              {connectionError && (
                <div className="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-xs text-red-400 text-center max-w-md">
                  {connectionError}
                </div>
              )}
              <div className="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 mb-6 text-left space-y-3 w-full max-w-md backdrop-blur-md">
                <h4 className="text-rose-400 font-bold text-sm flex items-center gap-2">
                  <AlertCircle className="w-4 h-4" /> Consent & Verification
                </h4>
                <p className="text-xs text-rose-200/80 leading-relaxed">
                  I consent to providing my account-opening details and understand that my session is recorded for verification purposes.
                </p>
                <label className="flex items-center gap-3 cursor-pointer group mt-2">
                  <div className="relative flex items-center justify-center">
                    <input
                      type="checkbox"
                      checked={hasAgreed}
                      onChange={(e) => setHasAgreed(e.target.checked)}
                      className="peer appearance-none w-5 h-5 border border-rose-500/50 rounded bg-rose-900/20 checked:bg-rose-500 checked:border-rose-500 transition-all cursor-pointer"
                    />
                    <svg className="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" /></svg>
                  </div>
                  <span className="text-xs font-bold text-rose-200 group-hover:text-white transition-colors"> I agree </span>
                </label>
              </div>
              <button
                onClick={() => void startLiveInterview()}
                disabled={!hasAgreed || isConnecting}
                className="px-12 py-4 bg-blue-600 hover:bg-blue-500 rounded-2xl font-bold flex items-center justify-center gap-3 transition-all shadow-xl shadow-blue-600/40 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isConnecting ? <Loader2 className="w-5 h-5 animate-spin" /> : <Play className="w-5 h-5 fill-current" />}
                {connectionError ? 'Retry Connection' : 'Start Application'}
              </button>
            </div>
          </div>
        )}
      </div>
    );
  }

  // —— Live (loan-style panel; only after connection opens) ——
  return (
    <InterviewLive
      data={interviewData}
      videoRef={videoRef}
      isSaving={isSaving}
      audioLevel={audioLevel}
      isMuted={isMuted}
      setIsMuted={setIsMuted}
      stopInterview={stopInterview}
      transcript={transcript}
      transcriptEndRef={transcriptEndRef}
      wrapUpState={wrapUpState}
      closingCountdown={closingCountdown}
      participantLabel="Applicant"
    />
  );
};
