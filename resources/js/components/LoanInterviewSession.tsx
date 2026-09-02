import React, { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Mic, Loader2, Play, CheckCircle2, AlertCircle } from 'lucide-react';
import { GoogleGenAI, Modality, LiveServerMessage } from "@google/genai";
import { LoanCandidateGate } from './LoanCandidateGate';
import { InterviewLive } from './InterviewLive';
import { TranscriptOrchestrator } from '../lib/transcriptOrchestrator';
import {
  createGeminiLiveClient,
  fetchLiveSessionToken,
  TranscribeLiveManager,
} from '../lib/transcribeLiveManager';
import { LivePcmPlayer, MicCaptureHandle, startMicCapture } from '../lib/liveAudio';

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

const CLOSING_MESSAGE_BN = "আপনার সময় ও প্রয়োজনীয় তথ্য দেওয়ার জন্য ধন্যবাদ। সাক্ষাৎকারটি সম্পন্ন করতে অনুগ্রহ করে 'End Session' বাটনে ক্লিক করুন।";
const CLOSING_MESSAGE_EN = "Thank you for your time and for providing the required information. Please click the End Session button to complete the interview.";

const ASSISTANT_TURN_SPLIT_PATTERN = /(?<=[।!?])\s*(?=ডাউন পেমেন্ট|বর্তমানে আপনার|আপনার (?:বাড়ির|বাড়ির|গাড়ির|মাসিক|অন্য|ব্যবসার)|আপনি (?:কী|কি|মোট)|লোনটি|এবার বলুন|আচ্ছা,)/u;

function extractClosingSegment(text: string): { remainder: string; closing: string | null } {
  const bnIdx = text.indexOf('আপনার সম');
  const enIdx = text.search(/Thank you for your time/i);
  let idx = -1;

  if (bnIdx >= 0 && enIdx >= 0) {
    idx = Math.min(bnIdx, enIdx);
  } else if (bnIdx >= 0) {
    idx = bnIdx;
  } else if (enIdx >= 0) {
    idx = enIdx;
  }

  if (idx < 0) {
    return { remainder: text, closing: null };
  }

  const remainder = text.slice(0, idx).trim();
  const closing = bnIdx >= 0 && (enIdx < 0 || bnIdx <= enIdx) ? CLOSING_MESSAGE_BN : CLOSING_MESSAGE_EN;

  return { remainder, closing };
}

function splitAssistantMonologue(text: string): string[] {
  const { remainder, closing } = extractClosingSegment(text);
  const turns: string[] = [];

  if (remainder) {
    const parts = remainder
      .split(ASSISTANT_TURN_SPLIT_PATTERN)
      .map((part) => part.trim())
      .filter(Boolean);

    if (parts.length > 1) {
      turns.push(...parts);
    } else {
      const ackSplit = /(?<=[।])\s*(?=ডাউন পেমেন্ট|এবার বলুন|আপনার (?:বাড়ির|বাড়ির))/u;
      const ackParts = remainder.split(ackSplit).map((part) => part.trim()).filter(Boolean);
      turns.push(...(ackParts.length > 1 ? ackParts : [remainder]));
    }
  }

  if (closing) {
    turns.push(closing);
  }

  return turns;
}

interface TranscriptEntry {
  id: string;
  speaker: string;
  text: string;
  timestamp: number;
  sequence: number;
}

interface LoanInterviewSessionProps {
  candidateData: any;
  onComplete?: () => void;
}

export const LoanInterviewSession: React.FC<LoanInterviewSessionProps> = ({
  candidateData,
  onComplete
}) => {
  const [stage, setStage] = useState<'gate' | 'setup' | 'live' | 'completed'>('gate');
  const [interviewData, setInterviewData] = useState<any>(candidateData);
  const [isConnected, setIsConnected] = useState(false);
  const [isConnecting, setIsConnecting] = useState(false);
  const [isMuted, setIsMuted] = useState(false);
  const [transcript, setTranscript] = useState<TranscriptEntry[]>([]);
  const [audioLevel, setAudioLevel] = useState(0);
  const [isCompleted, setIsCompleted] = useState(candidateData?.is_submitted);
  const [hasEverConnected, setHasEverConnected] = useState(false);
  const [hasPermissions, setHasPermissions] = useState(false);
  const [isRequestingPermissions, setIsRequestingPermissions] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [hasAgreed, setHasAgreed] = useState(false);
  const [wrapUpState, setWrapUpState] = useState<'active' | 'closing' | 'closing_done' | 'saving' | 'submitted'>('active');
  const wrapUpStateRef = useRef(wrapUpState);
  const isMutedRef = useRef(false);
  const timeNudgeTimerRef = useRef<any>(null);
  const hardLimitTimerRef = useRef<any>(null);
  const closingRequestedRef = useRef(false);
  const closingDeliveredRef = useRef(false);
  const intentionalCloseRef = useRef(false);
  const prematureClosingNudgeRef = useRef(false);
  const autoEndTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const closingCountdownIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const urgentNudgeTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [closingCountdown, setClosingCountdown] = useState<number | null>(null);

  const CLOSING_AUTO_END_MS = 5000;

  const INTERVIEW_TIME_NUDGE_MS = 150000; // 2:30 — remind AI to finish remaining mandatory questions
  const INTERVIEW_URGENT_NUDGE_MS = 240000; // 4:00 — urgent reminder for missing checklist items
  const INTERVIEW_LONG_NUDGE_MS = 360000; // 6:00 — long-running interview nudge (never force close)

  const QUESTION_MARKERS = /[?？]|বলুন|জানাবেন|বলবেন|কত টাকা|কি ধরণ|থাকলে পরিমাণ|না থাকলে|ডাউন পেমেন্ট|মাসিক আয়|কিস্তি/i;

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
  const isTurnCompleteRef = useRef(true);
  const pendingAssistantTextRef = useRef('');
  const pendingApplicantTextRef = useRef('');
  const assistantTurnCompleteRef = useRef(false);
  const assistantFlushTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const ASSISTANT_TRANSCRIPT_DELAY_MS = 500;

  useEffect(() => {
    wrapUpStateRef.current = wrapUpState;
  }, [wrapUpState]);

  useEffect(() => {
    isMutedRef.current = isMuted;
  }, [isMuted]);

  useEffect(() => {
    if (candidateData?.is_submitted) {
      setStage('completed');
      setIsCompleted(true);
      setWrapUpState('submitted');
    }
  }, [candidateData]);

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

  const isClosingMessage = (text: string): boolean => {
    return /End Session|সাক্ষাৎকারটি সম্পন্ন|সম্পন্ন করতে অনুগ্রহ করে/i.test(text);
  };

  const isStandaloneClosingMessage = (text: string): boolean => {
    if (!isClosingMessage(text)) {
      return false;
    }

    const closingMatch = text.match(/End Session|সাক্ষাৎকারটি সম্পন্ন|সম্পন্ন করতে অনুগ্রহ করে/i);
    if (!closingMatch || closingMatch.index === undefined) {
      return false;
    }

    const beforeClosing = text.slice(0, closingMatch.index).trim();
    if (!beforeClosing) {
      return true;
    }

    return !QUESTION_MARKERS.test(beforeClosing);
  };

  const hasApplicantResponseAfterLastQuestion = (
    entries: { speaker: string; text: string }[]
  ): boolean => {
    let lastQuestionIdx = -1;

    for (let i = entries.length - 1; i >= 0; i--) {
      if (entries[i].speaker === 'assistant' && QUESTION_MARKERS.test(entries[i].text)) {
        lastQuestionIdx = i;
        break;
      }
    }

    if (lastQuestionIdx < 0) {
      return true;
    }

    for (let i = lastQuestionIdx + 1; i < entries.length; i++) {
      if (entries[i].speaker === 'applicant' && entries[i].text.trim()) {
        return true;
      }
    }

    return false;
  };

  const canProceedToClosing = (text: string): boolean => {
    return isStandaloneClosingMessage(text)
      && hasApplicantResponseAfterLastQuestion(transcriptRef.current);
  };

  const nudgePrematureClosing = () => {
    if (prematureClosingNudgeRef.current || !sessionRef.current || closingDeliveredRef.current) {
      return;
    }

    prematureClosingNudgeRef.current = true;
    sessionRef.current.sendRealtimeInput({
      text: "STOP closing. The applicant has NOT answered your last question yet, OR you combined a question with the closing message. Ask the next missing mandatory checklist item as ONE question only, then STOP and WAIT for the applicant's answer. Do NOT give the closing message until every mandatory item is clearly answered."
    });

    window.setTimeout(() => {
      prematureClosingNudgeRef.current = false;
    }, 15000);
  };

  const resetClosingState = () => {
    closingRequestedRef.current = false;
    wrapUpStateRef.current = 'active';
    setWrapUpState('active');
  };

  const nudgeIncompleteChecklist = (message: string) => {
    if (!sessionRef.current || closingRequestedRef.current || closingDeliveredRef.current) {
      return;
    }

    sessionRef.current.sendRealtimeInput({ text: message });
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

  const finalizeClosingPhase = () => {
    if (closingDeliveredRef.current) return;

    const closingCandidate = pendingAssistantTextRef.current.trim();
    if (!canProceedToClosing(closingCandidate)) {
      flushAssistantTranscript({ allowClosing: false });
      resetClosingState();
      nudgePrematureClosing();
      return;
    }

    if (assistantFlushTimerRef.current) {
      clearTimeout(assistantFlushTimerRef.current);
      assistantFlushTimerRef.current = null;
    }
    flushAssistantTranscript();

    closingDeliveredRef.current = true;
    wrapUpStateRef.current = 'closing_done';
    setWrapUpState('closing_done');
    setIsMuted(true);

    pcmPlayerRef.current?.clear();
    pendingAssistantTextRef.current = '';

    if (sessionRef.current) {
      intentionalCloseRef.current = true;
      try {
        sessionRef.current.close();
      } catch (_) { }
      sessionRef.current = null;
    }

    const seconds = CLOSING_AUTO_END_MS / 1000;
    setClosingCountdown(seconds);
    closingCountdownIntervalRef.current = setInterval(() => {
      setClosingCountdown((prev) => (prev !== null && prev > 1 ? prev - 1 : prev));
    }, 1000);

    autoEndTimerRef.current = setTimeout(() => {
      clearClosingTimers();
      stopInterview();
    }, CLOSING_AUTO_END_MS);
  };

  useEffect(() => {
    return () => {
      if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
      if (urgentNudgeTimerRef.current) clearTimeout(urgentNudgeTimerRef.current);
      if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);
      clearClosingTimers();

      if (sessionRef.current) {
        try {
          sessionRef.current.close();
        } catch (_) { }
      }

      if (localStreamRef.current) {
        localStreamRef.current.getTracks().forEach((track) => track.stop());
      }

      micCaptureRef.current?.stop();
      micCaptureRef.current = null;
      pcmPlayerRef.current?.stop();
      pcmPlayerRef.current = null;
    };
  }, []);

  const requestMediaAccess = async () => {
    setIsRequestingPermissions(true);
    setPermissionError(null);

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { width: 1280, height: 720 },
        audio: {
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true,
        },
      });

      localStreamRef.current = stream;

      if (videoRef.current) {
        videoRef.current.srcObject = stream;
      }

      setHasPermissions(true);
    } catch (err: any) {
      console.error("Media access error:", err);
      setPermissionError("Could not access camera or microphone. Please check permissions.");
    } finally {
      setIsRequestingPermissions(false);
    }
  };

  const requestClosingMessage = () => {
    if (closingRequestedRef.current || !sessionRef.current) return;

    if (!hasApplicantResponseAfterLastQuestion(transcriptRef.current)) {
      nudgePrematureClosing();
      return;
    }

    closingRequestedRef.current = true;
    setWrapUpState('closing');
    sessionRef.current.sendRealtimeInput({
      text: "All mandatory checklist items appear complete. Give ONLY the STEP 3 closing message once in the applicant's language — no other sentences, no questions. After saying it, remain completely silent."
    });
  };

  const syncTranscript = () => {
    const entries = orchestratorRef.current.getEntries();
    transcriptRef.current = entries;
    setTranscript(entries);
  };

  const pushTranscriptEntry = (speaker: 'assistant', text: string) => {
    const trimmed = text.trim();
    if (!trimmed) {
      return;
    }

    orchestratorRef.current.addAssistantFinal(trimmed);
    syncTranscript();
  };

  const finalizeApplicantTranscript = () => {
    if (!usingTranscribeFallbackRef.current) {
      return;
    }

    const text = pendingApplicantTextRef.current.trim();
    pendingApplicantTextRef.current = '';

    if (text) {
      orchestratorRef.current.addParticipantFinal(text, 'applicant');
      syncTranscript();
    }
  };

  const bufferApplicantText = (text: string) => {
    const trimmed = text.trim();
    if (!trimmed) {
      return;
    }

    pendingApplicantTextRef.current += (pendingApplicantTextRef.current ? ' ' : '') + trimmed;
  };

  const flushAssistantTranscript = (options?: { allowClosing?: boolean }) => {
    const text = pendingAssistantTextRef.current.trim();
    if (!text) {
      return;
    }

    pendingAssistantTextRef.current = '';
    assistantTurnCompleteRef.current = false;

    const allowClosing = options?.allowClosing ?? canProceedToClosing(text);
    let turns = splitAssistantMonologue(text);

    if (!allowClosing) {
      turns = turns.filter((turn) => !isClosingMessage(turn));
    }

    for (const turn of turns) {
      pushTranscriptEntry('assistant', turn);
    }
  };

  const tryFlushAssistantTranscript = () => {
    if (!assistantTurnCompleteRef.current) {
      return;
    }

    if (pcmPlayerRef.current?.isPlaying()) {
      return;
    }

    if (assistantFlushTimerRef.current) {
      clearTimeout(assistantFlushTimerRef.current);
    }

    assistantFlushTimerRef.current = setTimeout(() => {
      assistantFlushTimerRef.current = null;

      if (!assistantTurnCompleteRef.current || pcmPlayerRef.current?.isPlaying()) {
        return;
      }

      flushAssistantTranscript();
    }, ASSISTANT_TRANSCRIPT_DELAY_MS);
  };

  const bufferAssistantText = (text: string) => {
    if (!text || text.trim() === "" || closingDeliveredRef.current) {
      return;
    }

    if (isTurnCompleteRef.current && pendingAssistantTextRef.current.trim()) {
      flushAssistantTranscript();
    }

    isTurnCompleteRef.current = false;
    assistantTurnCompleteRef.current = false;
    pendingAssistantTextRef.current += text;

    if (!closingRequestedRef.current && isClosingMessage(pendingAssistantTextRef.current)) {
      if (!canProceedToClosing(pendingAssistantTextRef.current)) {
        nudgePrematureClosing();
      } else {
        closingRequestedRef.current = true;
        wrapUpStateRef.current = 'closing';
        setWrapUpState('closing');
      }
    }

    tryFlushAssistantTranscript();
  };

  const startAudioCapture = async () => {
    try {
      const stream = localStreamRef.current;
      if (!stream) return;

      micCaptureRef.current?.stop();
      micCaptureRef.current = await startMicCapture(
        stream,
        (pcmData) => {
          if (
            isMutedRef.current ||
            !sessionRef.current ||
            closingDeliveredRef.current ||
            wrapUpStateRef.current === 'closing' ||
            wrapUpStateRef.current === 'closing_done' ||
            wrapUpStateRef.current === 'saving' ||
            wrapUpStateRef.current === 'submitted'
          ) {
            return;
          }

          const base64Pcm = uint8ArrayToBase64(new Uint8Array(pcmData.buffer));

          sessionRef.current.sendRealtimeInput({
            audio: {
              mimeType: "audio/pcm;rate=16000",
              data: base64Pcm,
            },
          });

          transcribeManagerRef.current?.sendAudio(base64Pcm);
        },
        (level) => setAudioLevel(level),
      );
    } catch (err) {
      console.error("Audio capture error:", err);
    }
  };

  const handleAssistantPlaybackIdle = () => {
    tryFlushAssistantTranscript();

    if (
      (wrapUpStateRef.current === 'closing' || closingRequestedRef.current) &&
      isTurnCompleteRef.current &&
      !closingDeliveredRef.current
    ) {
      finalizeClosingPhase();
    }
  };

  const startLiveInterview = async () => {
    if (!hasPermissions || !localStreamRef.current || isConnecting || isConnected) {
      return;
    }

    setIsConnecting(true);
    setConnectionError(null);

    try {
      const transcriptIdentifier = interviewData?.public_url;

      if (!transcriptIdentifier) {
        setConnectionError("Missing interview identifier.");
        setIsConnecting(false);
        return;
      }

      const tokenData = await fetchLiveSessionToken(`/loan-interview/${transcriptIdentifier}/live-token`);
      const ai = createGeminiLiveClient(tokenData.token);

      pcmPlayerRef.current?.stop();
      pcmPlayerRef.current = new LivePcmPlayer(handleAssistantPlaybackIdle);

      const applicantName = (interviewData?.candidate_name || 'Applicant').trim();
      const applicantFirstName = applicantName.split(/\s+/)[0] || applicantName;

      const systemInstruction = `CRITICAL LANGUAGE RULE:
You are allowed to speak ONLY in:
- English
- Bengali (Bangla, native script)

If the applicant speaks Bengali:
- You MUST respond in Bengali script (বাংলা)
- NEVER use Banglish (romanized Bangla)
- NEVER switch to Hindi or Urdu in your replies

If the applicant mixes Hindi/English with Bengali, understand their answer but reply in Bengali or English only. Politely confirm any numbers they gave.

------------------------------------------------------------

THIS IS A LIVE VOICE INTERVIEW — NOT A FORM:
- NEVER say "write", "type", "fill in", "enter", or Bengali equivalents like "লিখুন", "টাইপ করুন", "পূরণ করুন"
- ALWAYS say "tell me", "say", "let me know", or Bengali "বলুন", "জানান", "উত্তর দিন"
- For zero/none answers say: "না থাকলে শূন্য বলুন" or "নেই বললেই হবে" — NEVER "শূন্য লিখুন"

------------------------------------------------------------

IDENTITY:
You are a professional Bank Loan Agent AI helping the applicant complete their loan application securely.

APPLICANT INFO (use in greeting):
Full name: ${applicantName}
First name for greeting: ${applicantFirstName}

------------------------------------------------------------

MANDATORY DATA CHECKLIST (track internally — do NOT read this list aloud):
Collect every item below before closing. Ask EXACTLY ONE question per turn, then STOP and WAIT.

ALWAYS REQUIRED:
1. Loan type — Personal, Car, or Home
2. Loan purpose — why they need the loan
3. Requested loan amount — exact BDT figure (no ranges)
4. Requested tenure — exact number of MONTHS (convert years to months if needed)
5. Income source — salaried, business, self-employed, etc.
6. Employer or business name (separate question — do NOT combine with income)
7. Net monthly income — exact BDT monthly amount (separate question — do NOT combine with employer)
8. Other regular monthly income — exact BDT amount, or confirm none/zero
9. Existing monthly loan EMI and obligations — exact BDT amount, or confirm none/zero

ONLY IF LOAN TYPE IS CAR OR HOME (skip for Personal):
10. Asset value — vehicle price or property value in BDT (ONE question only)
11. Down payment or equity available — exact BDT amount, or confirm none/zero (ONE separate question — never combine with item 10)

------------------------------------------------------------

INTERVIEW FLOW

STEP 1 — GREETING (first turn only — STRICT ORDER)
- Your FIRST spoken words MUST be English: "Hello ${applicantFirstName},"
- Then continue with a short professional introduction and ask if they are ready
- Exact preferred first turn:
  "Hello ${applicantFirstName}, I am a professional Bank Loan Agent AI. I am here to help you complete your loan application securely. Are you ready to begin?"
- Do NOT open with নমস্কার, আসসালামু আলাইকুম, or any non-English first word
- After the applicant replies, match their language (Bengali script or English) for later turns
- You MUST use the applicant's name (${applicantFirstName}) right after Hello
- Ask readiness ONCE only. If they already said yes/ready/প্রস্তুত, do NOT ask again — move to loan type
- STOP and WAIT for their answer

STEP 2 — QUESTIONS (strictly one at a time)
- Work through the mandatory checklist in a natural order
- Skip any item the applicant already answered clearly
- NEVER ask two checklist items in the same turn (e.g. do NOT ask asset price and down payment together)
- NEVER ask a new question in the same turn as acknowledging a previous answer — acknowledge briefly, STOP, then ask the NEXT item in your following turn
- NEVER repeat a question the applicant already answered clearly
- If you asked a question, you MUST wait for the applicant's spoken answer before asking anything else or closing
- If the applicant is silent: repeat the SAME question once only, then wait. Do NOT end the interview
- If they say "I don't know": ask once more simply; if still unknown, record as unknown and move on
- Ask follow-up ONLY when an answer is ambiguous, contradictory, or a range. One short clarification only
- For car/home loans: asset value and down payment are SEPARATE turns — never skip down payment
- Do NOT calculate EMI, DBR, LTV, eligibility, approval, or loan limits
- If asked "How much loan can I get?": say EXACTLY "I am collecting your application information. An indicative result will be calculated afterward using the bank's approved lending rules and reviewed by an authorized bank officer."

BENGALI QUESTION EXAMPLES (voice-safe phrasing):
- Other income: "আপনার অন্য কোনো নিয়মিত মাসিক আয় আছে কি? থাকলে পরিমাণটি বলুন। না থাকলে 'নেই' বলুন।"
- Existing EMI: "বর্তমানে আপনার কোনো মাসিক কিস্তি বা ঋণের বাধ্যবাধকতা আছে কি? থাকলে পরিমাণটি বলুন। না থাকলে 'নেই' বলুন।"
- Asset value (car/home): "গাড়ির/সম্পত্তির আনুমানিক মূল্য কত টাকা?"
- Down payment (next turn only): "ডাউন পেমেন্ট হিসেবে আপনি কত টাকা দিতে পারবেন? না থাকলে 'নেই' বলুন।"

STEP 2.5 — VERIFY BEFORE CLOSING (internal — do NOT read aloud)
Before STEP 3, confirm EVERY mandatory item has a clear applicant answer:
□ Loan type
□ Loan purpose
□ Requested amount (exact BDT)
□ Tenure in months
□ Income source
□ Employer/business name
□ Net monthly income
□ Other income (amount or none)
□ Existing EMI/obligations (amount or none)
□ Asset value (car/home only)
□ Down payment (car/home only — amount or none)

If ANY box is unchecked, ask that ONE missing item now and WAIT. Do NOT close.

STEP 3 — CLOSING (ONLY when STEP 2.5 verification passes)
- Give ONLY the closing message — no acknowledgements, no questions, no extra sentences before or after
- Say the closing message ONCE only. Do NOT repeat it.
- English (say exactly once): "Thank you for your time and for providing the required information. Please click the End Session button to complete the interview."
- Bengali (say exactly once): "আপনার সময় ও প্রয়োজনীয় তথ্য দেওয়ার জন্য ধন্যবাদ। সাক্ষাৎকারটি সম্পন্ন করতে অনুগ্রহ করে 'End Session' বাটনে ক্লিক করুন।"
- After the closing message, STOP COMPLETELY. Remain silent. Do NOT respond if the applicant says thank you or anything else.
- NEVER combine STEP 3 with any question in the same turn

NEVER tell the applicant to click End Session before STEP 3.
NEVER end the interview because of silence, pauses, or time elapsed — only close when the checklist is complete.

If the applicant explicitly wants to stop:
- Acknowledge briefly, then say EXACTLY: "Certainly. Please click the End Session button to submit your interview."

TIME GUIDANCE:
- Take as long as needed to complete the mandatory checklist
- Keep each question short — one sentence plus the instruction for none/zero if relevant

START NOW:
Begin with exactly: "Hello ${applicantFirstName}," then the professional introduction and readiness question. Do not start with নমস্কার. Then WAIT.`;

      const session = await ai.live.connect({
        model: tokenData.liveModel,
        config: {
          responseModalities: [Modality.AUDIO],
          systemInstruction,
          temperature: 0.2,
          topP: 0.8,
          speechConfig: {
            languageCode: 'en-US',
            voiceConfig: {
              prebuiltVoiceConfig: {
                voiceName: 'Aoede',
              },
            },
          },
          // Gemini Live rejects languageCodes; enable transcription with empty config.
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

            timeNudgeTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                "Time check: about 2 minutes 30 seconds have passed. Ask any remaining mandatory checklist questions now — ONE per turn. Do NOT give the closing message until every mandatory item has a clear applicant answer."
              );
            }, INTERVIEW_TIME_NUDGE_MS);

            urgentNudgeTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                "Urgent: interview is running long. Ask any remaining mandatory checklist items ONE at a time. For car/home loans you MUST get both asset value AND down payment as separate answers. Do NOT close until all items are answered."
              );
            }, INTERVIEW_URGENT_NUDGE_MS);

            hardLimitTimerRef.current = setTimeout(() => {
              nudgeIncompleteChecklist(
                "Long interview reminder: continue asking remaining mandatory checklist items ONE at a time. NEVER give the closing message until the applicant has clearly answered every item including down payment for car/home loans."
              );
            }, INTERVIEW_LONG_NUDGE_MS);
          },

          onmessage: async (message: LiveServerMessage) => {
            const serverContent = (message as any).serverContent;

            if (closingDeliveredRef.current) {
              return;
            }

            const base64Audio = serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
            if (base64Audio && !closingDeliveredRef.current) {
              const pcmData = new Int16Array(base64ToUint8Array(base64Audio).buffer);
              pcmPlayerRef.current?.enqueue(pcmData);
            }

            if (serverContent) {
              let userText = "";
              if (serverContent.userTurn?.parts) {
                userText = serverContent.userTurn.parts.map((p: any) => p.text || "").join("").trim();
              }
              if (!userText) {
                userText = serverContent.inputAudioTranscription?.text || serverContent.inputTranscription?.text || "";
              }
              if (
                userText &&
                usingTranscribeFallbackRef.current &&
                !closingDeliveredRef.current &&
                wrapUpStateRef.current !== 'closing' &&
                wrapUpStateRef.current !== 'closing_done'
              ) {
                bufferApplicantText(userText);
              }

              let aiText = "";
              if (serverContent.modelTurn?.parts) {
                aiText = serverContent.modelTurn.parts.map((p: any) => p.text || "").join("").trim();
              }
              if (!aiText) {
                aiText = serverContent.outputAudioTranscription?.text || serverContent.outputTranscription?.text || "";
              }
              if (aiText && !closingDeliveredRef.current) {
                bufferAssistantText(aiText);
              }
            }

            if (serverContent?.turnComplete) {
                isTurnCompleteRef.current = true;
                assistantTurnCompleteRef.current = true;
                finalizeApplicantTranscript();
                tryFlushAssistantTranscript();
            } else if (serverContent) {
                isTurnCompleteRef.current = false;
                assistantTurnCompleteRef.current = false;
            }
          },

          onclose: () => {
            if (intentionalCloseRef.current) {
              intentionalCloseRef.current = false;
              sessionRef.current = null;
              return;
            }

            if (!hasEverConnected) {
              setConnectionError("Connection closed unexpectedly. Please try again.");
              setIsConnecting(false);
              setIsConnected(false);
              sessionRef.current = null;
            } else if (
              wrapUpStateRef.current !== 'saving' &&
              wrapUpStateRef.current !== 'submitted' &&
              wrapUpStateRef.current !== 'closing_done'
            ) {
              stopInterview();
            }
          },

          onerror: (error: any) => {
            console.error("Gemini Live API Error:", error);
            if (!hasEverConnected) {
              setConnectionError(`Connection failed: ${error.message || "Unknown error"}`);
              setIsConnecting(false);
              setIsConnected(false);
              sessionRef.current = null;
            } else if (wrapUpStateRef.current !== 'saving' && wrapUpStateRef.current !== 'submitted') {
              stopInterview();
            }
          }
        }
      });

      sessionRef.current = session;
      await startAudioCapture();

      // Kick off after the session handle exists. Doing this in onopen races
      // sessionRef assignment and silently skips the AI greeting.
      session.sendRealtimeInput({
        text: `Begin now. First words must be exactly "Hello ${applicantFirstName}," then introduce yourself as Bank Loan Agent AI and ask if they are ready. Do NOT start with নমস্কার. Then wait. Ask one checklist question per turn. Never say লিখুন or ask two questions in one turn.`,
      });

      // Dedicated transcription is secondary — never block the spoken interview.
      void (async () => {
        try {
          const transcribeToken = await fetchLiveSessionToken(`/loan-interview/${transcriptIdentifier}/live-token`);
          const transcribeAi = createGeminiLiveClient(transcribeToken.token);
          transcribeManagerRef.current = new TranscribeLiveManager(
            transcribeAi,
            transcribeToken.transcriptionModel,
            {
              participantSpeaker: 'applicant',
              languageCodes: ['bn-BD', 'en-US'],
              context: 'loan',
              orchestrator: orchestratorRef.current,
              onTranscriptChange: () => syncTranscript(),
              onFallbackChange: (usingFallback) => {
                usingTranscribeFallbackRef.current = usingFallback;
              },
            },
          );
          await transcribeManagerRef.current.start();
        } catch {
          usingTranscribeFallbackRef.current = true;
        }
      })();
    } catch (err: any) {
      console.error("Critical error during Gemini connection initialization:", err);
      setIsConnecting(false);
      setConnectionError(`Failed to initialize connection: ${err.message || "Unknown error"}`);
    }
  };

  const persistTranscriptInBackground = (transcriptIdentifier: string, transcriptText: string): void => {
    const token = document
      .querySelector('meta[name="csrf-token"]')
      ?.getAttribute('content');

    const body = JSON.stringify({ transcript_text: transcriptText });

    void fetch(`/loan-interview/${transcriptIdentifier}/transcript`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token || '',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body,
      credentials: 'same-origin',
      keepalive: true,
    }).then(async (response) => {
      if (!response.ok) {
        console.error('Background transcript save failed.', response.status);
      }
    }).catch((error) => {
      console.error('Background transcript save error.', error);
    });
  };

  const releaseLocalMedia = () => {
    if (localStreamRef.current) {
      localStreamRef.current.getTracks().forEach((track) => track.stop());
      localStreamRef.current = null;
    }

    micCaptureRef.current?.stop();
    micCaptureRef.current = null;

    pcmPlayerRef.current?.stop();
    pcmPlayerRef.current = null;
  };

  const stopInterview = async () => {
    if (wrapUpStateRef.current === 'saving' || wrapUpStateRef.current === 'submitted') {
      return;
    }

    clearClosingTimers();
    if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
    if (urgentNudgeTimerRef.current) clearTimeout(urgentNudgeTimerRef.current);
    if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);

    wrapUpStateRef.current = 'saving';
    setWrapUpState('saving');

    const transcriptIdentifier = interviewData?.public_url;

    // Stop Live / mic immediately so the applicant leaves the room without waiting.
    try {
      sessionRef.current?.sendRealtimeInput({ audioStreamEnd: true });
    } catch {
      // Ignore flush errors during teardown.
    }

    void transcribeManagerRef.current?.stop();
    transcribeManagerRef.current = null;

    releaseLocalMedia();

    setIsConnected(false);
    setIsConnecting(false);
    setIsMuted(true);

    finalizeApplicantTranscript();
    if (pendingAssistantTextRef.current.trim()) {
      flushAssistantTranscript({
        allowClosing: closingDeliveredRef.current || closingRequestedRef.current,
      });
    }

    const transcriptText = orchestratorRef.current.toSaveFormat('Applicant');

    if (sessionRef.current) {
      intentionalCloseRef.current = true;
      try {
        sessionRef.current.close();
      } catch {
        // Ignore close errors during teardown.
      }
      sessionRef.current = null;
    }

    // Show thank-you immediately — do not wait on network or extraction.
    setIsSaving(false);
    setIsCompleted(true);
    setStage('completed');
    wrapUpStateRef.current = 'submitted';
    setWrapUpState('submitted');

    if (onComplete) {
      onComplete();
    }

    if (!transcriptIdentifier) {
      console.error('Missing interview identifier for background transcript save.');
      return;
    }

    if (!transcriptText.trim()) {
      console.error('Empty transcript; background save skipped.');
      return;
    }

    persistTranscriptInBackground(transcriptIdentifier, transcriptText);
  };

  if (isCompleted || stage === 'completed') {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-950 text-white p-6 font-sans">
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          className="max-w-md w-full bg-slate-900 border border-slate-800 rounded-[32px] p-10 text-center space-y-8 shadow-2xl"
        >
          <div className="w-24 h-24 bg-emerald-500/10 rounded-3xl flex items-center justify-center mx-auto text-emerald-500 shadow-inner">
            <CheckCircle2 className="w-12 h-12" />
          </div>

          <div className="space-y-4">
            <h2 className="text-3xl font-black tracking-tight text-white">
              Application Submitted — Thank you.
            </h2>
            <p className="text-slate-400 leading-relaxed">
              Your interview session has ended. You can close this window now.
         
            </p>
          </div>
        </motion.div>
      </div>
    );
  }

  if (stage === 'gate') {
    return (
      <LoanCandidateGate
        interviewData={interviewData}
        onSuccess={(data) => {
          setInterviewData(data);
          setStage('setup');
        }}
      />
    );
  }

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
                We need access to your camera and microphone to conduct the loan interview.
              </p>
            </div>

            {permissionError && (
              <div className="p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-xs text-red-400">
                {permissionError}
              </div>
            )}

            <button
              onClick={requestMediaAccess}
              disabled={isRequestingPermissions}
              className="w-full py-4 bg-blue-600 hover:bg-blue-500 rounded-2xl font-bold flex items-center justify-center gap-2 transition-all"
            >
              {isRequestingPermissions ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                <CheckCircle2 className="w-5 h-5" />
              )}
              Grant Permissions
            </button>
          </div>
        ) : (
          <div className="relative w-full max-w-2xl aspect-video bg-slate-900 rounded-3xl overflow-hidden border border-slate-800 shadow-2xl">
            <video
              ref={videoRef}
              autoPlay
              muted
              playsInline
              className="w-full h-full object-cover"
            />

            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 to-transparent flex flex-col items-center justify-end p-8">
              {connectionError && (
                <div className="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-xs text-red-400 text-center max-w-md">
                  {connectionError}
                </div>
              )}

              {/* Disclaimer & Checkbox */}
              <div className="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 mb-6 text-left space-y-3 w-full max-w-md backdrop-blur-md">
                <h4 className="text-rose-400 font-bold text-sm flex items-center gap-2">
                  <AlertCircle className="w-4 h-4" /> Consent & Verification
                </h4>
                <p className="text-xs text-rose-200/80 leading-relaxed">
                  I consent to providing my financial details and understand that my session is recorded for verification purposes.
                </p>
                <label className="flex items-center gap-3 cursor-pointer group mt-2">
                  <div className="relative flex items-center justify-center">
                    <input
                      type="checkbox"
                      checked={hasAgreed}
                      onChange={(e) => setHasAgreed(e.target.checked)}
                      className="peer appearance-none w-5 h-5 border border-rose-500/50 rounded bg-rose-900/20 checked:bg-rose-500 checked:border-rose-500 transition-all cursor-pointer"
                    />
                    <svg className="absolute w-3 h-3 text-white opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path></svg>
                  </div>
                  <span className="text-xs font-bold text-rose-200 group-hover:text-white transition-colors"> I agree </span>
                </label>
              </div>

              <button
                onClick={startLiveInterview}
                disabled={isConnecting || !hasAgreed}
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
