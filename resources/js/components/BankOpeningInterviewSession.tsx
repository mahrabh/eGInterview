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
import { isBanglishOnly } from '../lib/transcriptLanguage';

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
  "আপনার সময় ও প্রয়োজনীয় তথ্য দেওয়ার জন্য ধন্যবাদ। সাক্ষাৎকারটি সম্পন্ন করতে অনুগ্রহ করে 'End Session' বাটনে ক্লিক করুন।";
const CLOSING_MESSAGE_EN =
  'Thank you for your time and for providing the required information. Please click the End Session button to complete the interview.';

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

function extractClosingSegment(text: string): { remainder: string; closing: string | null } {
  const bnIdx = text.indexOf('আপনার সম');
  const enIdx = text.search(/Thank you for your time/i);
  let idx = -1;

  if (bnIdx >= 0 && enIdx >= 0) idx = Math.min(bnIdx, enIdx);
  else if (bnIdx >= 0) idx = bnIdx;
  else if (enIdx >= 0) idx = enIdx;

  if (idx < 0) return { remainder: text, closing: null };

  return {
    remainder: text.slice(0, idx).trim(),
    closing: bnIdx >= 0 && (enIdx < 0 || bnIdx <= enIdx) ? CLOSING_MESSAGE_BN : CLOSING_MESSAGE_EN,
  };
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
  const autoEndTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closingCountdownIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
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

  const pushAssistantFinal = (text: string) => {
    const cleaned = text.trim();
    if (!cleaned || isAssistantInstructionEcho(cleaned)) return;
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

    const { remainder, closing } = extractClosingSegment(raw);
    if (remainder) pushAssistantFinal(remainder);
    if (closing) {
      pushAssistantFinal(closing);
      closingDeliveredRef.current = true;
      setWrapUpState('closing_done');
      scheduleAutoEnd();
    }
  };

  const tryFlushAssistantTranscript = () => {
    if (!assistantTurnCompleteRef.current) return;
    if (pcmPlayerRef.current?.isPlaying()) return;
    flushAssistantTranscript();
  };

  const bufferAssistantText = (text: string) => {
    if (!text) return;
    pendingAssistantTextRef.current = `${pendingAssistantTextRef.current} ${text}`.trim();
    if (assistantFlushTimerRef.current) clearTimeout(assistantFlushTimerRef.current);
    assistantFlushTimerRef.current = setTimeout(() => tryFlushAssistantTranscript(), 500);
  };

  const commitApplicantFinal = (text: string, source: 'transcribe' | 'live_input') => {
    const cleaned = text.trim();
    if (!cleaned) return;
    if (isBanglishOnly(cleaned) && source === 'live_input') return;
    if (pcmPlayerRef.current?.isPlaying()) return;

    if (applicantSourceRef.current === 'unset') {
      applicantSourceRef.current = source;
    } else if (applicantSourceRef.current !== source && source === 'live_input') {
      return;
    }

    orchestratorRef.current.addParticipantFinal(cleaned);
    syncTranscript();
  };

  const bufferApplicantLiveInput = (text: string) => {
    if (!text || pcmPlayerRef.current?.isPlaying()) return;
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

  const clearClosingTimers = () => {
    if (autoEndTimerRef.current) {
      clearTimeout(autoEndTimerRef.current);
      autoEndTimerRef.current = null;
    }
    if (closingCountdownIntervalRef.current) {
      clearInterval(closingCountdownIntervalRef.current);
      closingCountdownIntervalRef.current = null;
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
      void stopInterview();
    }, CLOSING_AUTO_END_MS);
  };

  const nudgeIncompleteChecklist = (message: string) => {
    if (!sessionRef.current || closingDeliveredRef.current) return;
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

STEP 3.5 — VERIFY BEFORE CLOSING (internal — do NOT read aloud)
Before STEP 4, confirm:
□ Account type chosen and confirmed
□ Every question in that profile has a clear applicant answer

If ANY box is unchecked, ask that ONE missing item now and WAIT. Do NOT close.

STEP 4 — CLOSING (ONLY when STEP 3.5 passes)
- Give ONLY the closing message — no questions after it
- English (say exactly once): "${CLOSING_MESSAGE_EN}"
- Bengali (say exactly once): "${CLOSING_MESSAGE_BN}"
- After the closing message, STOP COMPLETELY. Remain silent.
- NEVER tell the applicant to click End Session before STEP 4

If the applicant explicitly wants to stop:
- Acknowledge briefly, then say EXACTLY: "Certainly. Please click the End Session button to submit your interview."

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
        if (isMutedRef.current || !sessionRef.current) return;
        if (pcmPlayerRef.current?.isPlaying()) return;

        const base64Pcm = uint8ArrayToBase64(new Uint8Array(pcmData.buffer));
        sessionRef.current.sendRealtimeInput({
          audio: { mimeType: 'audio/pcm;rate=16000', data: base64Pcm },
        });
        transcribeManagerRef.current?.sendAudio(base64Pcm);
      },
      (level) => setAudioLevel(level),
    );
  };

  const handleAssistantPlaybackIdle = () => {
    tryFlushAssistantTranscript();
    finalizeLiveInputApplicant();
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
            if (closingDeliveredRef.current) return;

            const serverContent = (message as any).serverContent;
            if (!serverContent) return;

            const base64Audio = serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
            if (base64Audio) {
              const pcmData = new Int16Array(base64ToUint8Array(base64Audio).buffer);
              pcmPlayerRef.current?.enqueue(pcmData);
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
            if (userText && !pcmPlayerRef.current?.isPlaying()) {
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
              tryFlushAssistantTranscript();
              if (!pcmPlayerRef.current?.isPlaying()) {
                finalizeLiveInputApplicant();
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
          if (applicantSourceRef.current === 'unset') {
            applicantSourceRef.current = 'transcribe';
          }
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
