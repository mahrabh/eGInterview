import React, { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Mic, Loader2, Play, CheckCircle2, AlertCircle } from 'lucide-react';
import { GoogleGenAI, Modality, LiveServerMessage } from "@google/genai";
import { LoanCandidateGate } from './LoanCandidateGate';
import { InterviewLive } from './InterviewLive';

const WORKLET_CODE = `
class PCMProcessor extends AudioWorkletProcessor {
  constructor(options) {
    super();
    this.sourceRate = options.processorOptions.sampleRate || 48000;
    this.targetRate = 16000;
    this.buffer = new Float32Array(4096);
    this.bufferIdx = 0;
  }
  process(inputs) {
    const input = inputs[0];
    if (!input || !input.length) return true;
    const channel = input[0];
    const ratio = this.sourceRate / this.targetRate;
    const outputSamples = Math.floor(channel.length / ratio);

    for (let i = 0; i < outputSamples; i++) {
      const start = Math.floor(i * ratio);
      const end = Math.floor((i + 1) * ratio);
      let sum = 0;
      let count = 0;
      for (let j = start; j < end && j < channel.length; j++) {
        sum += channel[j];
        count++;
      }
      const sample = count > 0 ? sum / count : 0;
      if (this.bufferIdx < this.buffer.length) this.buffer[this.bufferIdx++] = sample;
      else {
        this.flush();
        this.buffer[this.bufferIdx++] = sample;
      }
    }

    if (this.bufferIdx >= 512) this.flush();
    return true;
  }
  flush() {
    if (this.bufferIdx === 0) return;
    const pcmData = new Int16Array(this.bufferIdx);
    for (let i = 0; i < this.bufferIdx; i++) {
      let s = Math.max(-1, Math.min(1, this.buffer[i]));
      pcmData[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
    }
    this.port.postMessage(pcmData);
    this.bufferIdx = 0;
  }
}
registerProcessor('pcm-processor', PCMProcessor);
`;

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
  const [transcript, setTranscript] = useState<{ speaker: string; text: string }[]>([]);
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
  const timeNudgeTimerRef = useRef<any>(null);
  const hardLimitTimerRef = useRef<any>(null);
  const closingRequestedRef = useRef(false);
  const closingDeliveredRef = useRef(false);

  const INTERVIEW_TIME_NUDGE_MS = 150000; // 2:30 — remind AI to finish remaining mandatory questions
  const INTERVIEW_HARD_LIMIT_MS = 180000; // 3:00 — ask AI to give closing message only

  const videoRef = useRef<HTMLVideoElement>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const audioContextRef = useRef<AudioContext | null>(null);
  const processorRef = useRef<any>(null);
  const sessionRef = useRef<any>(null);
  const transcriptEndRef = useRef<HTMLDivElement>(null);
  const audioQueueRef = useRef<Int16Array[]>([]);
  const isPlayingRef = useRef(false);
  const transcriptRef = useRef<any[]>([]);
  const isTurnCompleteRef = useRef(true);

  useEffect(() => {
    wrapUpStateRef.current = wrapUpState;
  }, [wrapUpState]);

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

  useEffect(() => {
    return () => {
      if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
      if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);

      if (sessionRef.current) {
        try {
          sessionRef.current.close();
        } catch (_) { }
      }

      if (localStreamRef.current) {
        localStreamRef.current.getTracks().forEach((track) => track.stop());
      }

      if (audioContextRef.current) {
        audioContextRef.current.close().catch(() => { });
      }
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
    closingRequestedRef.current = true;
    setWrapUpState('closing');
    sessionRef.current.sendRealtimeInput({
      text: "Give your final closing message now exactly as specified in STEP 4. Do not ask any new questions. Wait for the candidate to click End Session."
    });
  };

  const addTranscriptMessage = (speaker: string, text: string) => {
    if (!text || text.trim() === "") return;

    const last = transcriptRef.current[transcriptRef.current.length - 1];

    if (last && last.speaker === speaker) {
      last.text += (speaker === 'assistant' ? "" : " ") + text;
    } else {
      transcriptRef.current.push({ speaker, text, timestamp: new Date().toISOString() });
    }

    setTranscript([...transcriptRef.current]);
  };

  const startAudioCapture = async () => {
    try {
      const stream = localStreamRef.current;
      if (!stream) return;

      const audioContext = new AudioContext();
      audioContextRef.current = audioContext;
      const source = audioContext.createMediaStreamSource(stream);

      const blob = new Blob([WORKLET_CODE], { type: 'application/javascript' });
      const url = URL.createObjectURL(blob);
      await audioContext.audioWorklet.addModule(url);

      const workletNode = new AudioWorkletNode(audioContext, 'pcm-processor', {
        processorOptions: { sampleRate: audioContext.sampleRate }
      });

      processorRef.current = workletNode;

      workletNode.port.onmessage = (e: any) => {
        if (isMuted || !sessionRef.current || wrapUpStateRef.current === 'closing_done' || wrapUpStateRef.current === 'saving' || wrapUpStateRef.current === 'submitted') return;
        const pcmData = e.data;

        sessionRef.current.sendRealtimeInput({
          audio: {
            mimeType: "audio/pcm;rate=16000",
            data: uint8ArrayToBase64(new Uint8Array(pcmData.buffer))
          }
        });

        let sum = 0;
        for (let i = 0; i < pcmData.length; i++) {
          sum += (pcmData[i] / 32768) * (pcmData[i] / 32768);
        }
        setAudioLevel(Math.sqrt(sum / pcmData.length));
      };

      source.connect(workletNode);
      workletNode.connect(audioContext.destination);
    } catch (err) {
      console.error("Audio capture error:", err);
    }
  };

  const playNextInQueue = () => {
    if (audioQueueRef.current.length === 0 || !audioContextRef.current) {
      isPlayingRef.current = false;
      
      // If we are in closing state and AI turn is complete, trigger stop
      if (wrapUpStateRef.current === 'closing' && isTurnCompleteRef.current && !closingDeliveredRef.current) {
          closingDeliveredRef.current = true;
          setWrapUpState('closing_done');
          
          if (sessionRef.current) {
              sessionRef.current.sendRealtimeInput({ audioStreamEnd: true });
          }
          // Never auto-submit — the applicant must click End Session.
      }
      return;
    }

    isPlayingRef.current = true;
    const pcmData = audioQueueRef.current.shift()!;
    const float32Data = new Float32Array(pcmData.length);

    for (let i = 0; i < pcmData.length; i++) {
      float32Data[i] = pcmData[i] / 32768.0;
    }

    const buffer = audioContextRef.current.createBuffer(1, float32Data.length, 24000);
    buffer.getChannelData(0).set(float32Data);

    const source = audioContextRef.current.createBufferSource();
    source.buffer = buffer;
    source.connect(audioContextRef.current.destination);
    source.onended = () => playNextInQueue();
    source.start();
  };

  const startLiveInterview = async () => {
    if (!hasPermissions || !localStreamRef.current || isConnecting || isConnected) {
      return;
    }

    setIsConnecting(true);
    setConnectionError(null);

    try {
      const apiKey = import.meta.env.VITE_GEMINI_API_KEY;

      if (!apiKey || apiKey.trim() === "" || apiKey === "undefined") {
        setConnectionError("Gemini API key is missing.");
        setIsConnecting(false);
        return;
      }

      const ai = new GoogleGenAI({ apiKey });

      const systemInstruction = `CRITICAL LANGUAGE RULE:
You are allowed to speak ONLY in:
- English
- Bengali (Bangla, native script)

If the candidate speaks Bengali:
- You MUST respond in Bengali script (বাংলা)
- NEVER use Banglish (romanized Bangla)
- NEVER switch to Hindi or Urdu

------------------------------------------------------------

IDENTITY:
You are a professional Bank Loan Agent AI helping the candidate complete their loan application securely.

CANDIDATE INFO:
Name: ${interviewData?.candidate_name}

------------------------------------------------------------

MANDATORY DATA CHECKLIST (track internally — do NOT read this list aloud):
You MUST collect every item below before the closing message. Ask ONE question at a time, then STOP and WAIT.

ALWAYS REQUIRED:
1. Loan type — Personal, Car, or Home
2. Loan purpose — why they need the loan
3. Requested loan amount — exact BDT figure (no ranges)
4. Requested tenure — exact number of MONTHS (convert years to months if needed)
5. Income source — salaried, business, self-employed, etc.
6. Employer or business name
7. Net monthly income — exact BDT monthly amount (not annual)
8. Other regular monthly income — exact BDT amount, or confirm zero/none
9. Existing monthly loan EMI and obligations — exact BDT amount, or confirm zero/none

ONLY IF LOAN TYPE IS CAR OR HOME (skip for Personal):
10. Asset value — vehicle price or property value in BDT
11. Down payment or equity available — exact BDT amount, or confirm zero/none

------------------------------------------------------------

INTERVIEW FLOW

STEP 1 — GREETING
- Greet politely, introduce yourself briefly
- Ask if they are ready to begin
- STOP and WAIT for their answer

STEP 2 — QUESTIONS (one at a time)
- Work through the mandatory checklist in a natural order
- Skip any item the candidate already answered clearly
- If the candidate is silent or did not answer: repeat the SAME question once, then wait patiently. Do NOT end the interview and do NOT tell them to click End Session
- If they say "I don't know": ask once more simply; if still unknown, accept that and move to the next item
- Ask follow-up questions ONLY when an answer is ambiguous, contradictory, or a range (e.g. "20-30 thousand", "2 or 3 years"). One short clarification only — then move on
- Do NOT ask unnecessary or duplicate follow-up questions
- Do NOT calculate EMI, DBR, LTV, eligibility, approval, or loan limits
- If asked "How much loan can I get?": say EXACTLY "I am collecting your application information. An indicative result will be calculated afterward using the bank's approved lending rules and reviewed by an authorized bank officer."

STEP 3 — WHEN CHECKLIST IS COMPLETE
- Give ONE brief confirmation such as "Thank you, I have all the information I need."
- Then give the closing message from STEP 4
- Do NOT ask any new questions after the checklist is complete

STEP 4 — CLOSING (only after ALL mandatory items are collected, OR at the 3-minute time limit)
Say EXACTLY one of:
- English: "Thank you for your time and for providing the required information. Please click the End Session button to complete the interview."
- Bangla: "আপনার সময় ও প্রয়োজনীয় তথ্য দেওয়ার জন্য ধন্যবাদ। সাক্ষাৎকারটি সম্পন্ন করতে অনুগ্রহ করে 'End Session' বাটনে ক্লিক করুন।"

NEVER tell the candidate to click End Session before STEP 4.
NEVER end the interview because of silence, pauses, or time pressure before the checklist is complete (unless the 3-minute limit is reached).

If the candidate explicitly says they want to stop now:
- Acknowledge briefly
- Say EXACTLY: "Certainly. Please click the End Session button to submit your interview."

TIME GUIDANCE:
- Target about 3 minutes total
- Prioritize completing the mandatory checklist over speed
- Keep questions concise

START NOW:
Begin with greeting only, then WAIT.`;

      const session = await ai.live.connect({
        model: "gemini-3.1-flash-live-preview",
        config: {
          generationConfig: {
            speechConfig: {
              voiceConfig: {
                prebuiltVoiceConfig: {
                  voiceName: "Aoede"
                }
              }
            },
            temperature: 0.2,
            topP: 0.8
          },
          responseModalities: [Modality.AUDIO],
          systemInstruction,
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
            startAudioCapture();

            if (sessionRef.current) {
              sessionRef.current.sendRealtimeInput({
                text: "Begin with your greeting only. Wait for the candidate reply, then ask the mandatory checklist questions one at a time. Do not tell them to end the session until every mandatory item is collected."
              });
            }

            timeNudgeTimerRef.current = setTimeout(() => {
              if (
                sessionRef.current &&
                wrapUpStateRef.current === 'active' &&
                !closingRequestedRef.current
              ) {
                sessionRef.current.sendRealtimeInput({
                  text: "Time check: about 2 minutes 30 seconds have passed. Ask any remaining mandatory checklist questions now. Keep each question short. Do not give the closing message yet unless every mandatory item is already collected."
                });
              }
            }, INTERVIEW_TIME_NUDGE_MS);

            hardLimitTimerRef.current = setTimeout(() => {
              if (
                wrapUpStateRef.current !== 'saving' &&
                wrapUpStateRef.current !== 'submitted' &&
                !closingRequestedRef.current
              ) {
                requestClosingMessage();
              }
            }, INTERVIEW_HARD_LIMIT_MS);
          },

          onmessage: async (message: LiveServerMessage) => {
            const serverContent = (message as any).serverContent;

            const base64Audio = serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
            if (base64Audio) {
              const pcmData = new Int16Array(base64ToUint8Array(base64Audio).buffer);
              audioQueueRef.current.push(pcmData);
              if (!isPlayingRef.current) {
                playNextInQueue();
              }
            }

            if (serverContent) {
              let userText = "";
              if (serverContent.userTurn?.parts) {
                userText = serverContent.userTurn.parts.map((p: any) => p.text || "").join("").trim();
              }
              if (!userText) {
                userText = serverContent.inputAudioTranscription?.text || serverContent.inputTranscription?.text || "";
              }
              if (userText) {
                addTranscriptMessage('Candidate', userText);
              }

              let aiText = "";
              if (serverContent.modelTurn?.parts) {
                aiText = serverContent.modelTurn.parts.map((p: any) => p.text || "").join("").trim();
              }
              if (!aiText) {
                aiText = serverContent.outputAudioTranscription?.text || serverContent.outputTranscription?.text || "";
              }
              if (aiText) {
                addTranscriptMessage('assistant', aiText);
              }
            }

            if (serverContent?.turnComplete) {
                isTurnCompleteRef.current = true;
                if (wrapUpStateRef.current === 'closing' && !isPlayingRef.current && !closingDeliveredRef.current) {
                    // Do nothing here, it is handled in playNextInQueue
                }
            } else if (serverContent) {
                isTurnCompleteRef.current = false;
            }
          },

          onclose: () => {
            if (!hasEverConnected) {
              setConnectionError("Connection closed unexpectedly. Please try again.");
              setIsConnecting(false);
              setIsConnected(false);
              sessionRef.current = null;
            } else if (wrapUpStateRef.current !== 'saving' && wrapUpStateRef.current !== 'submitted') {
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
    } catch (err: any) {
      console.error("Critical error during Gemini connection initialization:", err);
      setIsConnecting(false);
      setConnectionError(`Failed to initialize connection: ${err.message || "Unknown error"}`);
    }
  };

  const stopInterview = async () => {
    if (wrapUpStateRef.current === 'saving' || wrapUpStateRef.current === 'submitted' || isSaving) return;

    if (timeNudgeTimerRef.current) clearTimeout(timeNudgeTimerRef.current);
    if (hardLimitTimerRef.current) clearTimeout(hardLimitTimerRef.current);
    
    setWrapUpState('saving');
    wrapUpStateRef.current = 'saving';
    
    const transcriptIdentifier = interviewData?.public_url;

    if (!transcriptIdentifier) {
      alert("Missing interview identifier.");
      return;
    }

    try {
      setIsSaving(true);

      // Stop AudioWorklet samples and explicit flush
      if (sessionRef.current) {
        sessionRef.current.sendRealtimeInput({ audioStreamEnd: true });
      }

      // Stop microphone and disconnect capture nodes
      if (localStreamRef.current) {
        localStreamRef.current.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;
      }
      if (processorRef.current) {
         processorRef.current.disconnect();
      }
      if (audioContextRef.current) {
        audioContextRef.current.close().catch(() => { });
        audioContextRef.current = null;
      }

      setIsConnected(false);
      setIsConnecting(false);
      audioQueueRef.current = [];
      isPlayingRef.current = false;

      // Keep Gemini open until transcription remains quiet for 1.2s max 4s.
      await new Promise(resolve => setTimeout(resolve, 1500));

      const finalTranscript = transcriptRef.current;
      const transcriptText = finalTranscript
        .map((e: any) => `${e.speaker === 'assistant' ? 'AI' : 'Candidate'}: ${e.text}`)
        .join('\n\n');

      const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

      const response = await fetch(`/loan-interview/${transcriptIdentifier}/transcript`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          transcript_text: transcriptText,
        }),
      });

      if (!response.ok) {
          setWrapUpState('closing'); // Reset to allow retry
          throw new Error('Failed to save session');
      }

      if (sessionRef.current) {
        try {
          sessionRef.current.close();
        } catch (_) { }
        sessionRef.current = null;
      }

      setIsCompleted(true);
      setStage('completed');
      setWrapUpState('submitted');

      if (onComplete) {
        onComplete();
      }
    } catch (error) {
      console.error("Failed to save session:", error);
      alert("Failed to save interview session.");
    } finally {
      setIsSaving(false);
    }
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
              This interview has already been completed.
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
    />
  );
};
