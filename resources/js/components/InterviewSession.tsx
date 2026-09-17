import React, { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Mic, Loader2, Play, CheckCircle2, AlertCircle } from 'lucide-react';
import { GoogleGenAI, Modality, LiveServerMessage } from "@google/genai";
import { CandidateGate } from './CandidateGate';
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

interface InterviewSessionProps {
  candidateData: any;
  onComplete?: () => void;
}

export const InterviewSession: React.FC<InterviewSessionProps> = ({
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
  const [isCompleted, setIsCompleted] = useState(candidateData?.status === 'completed');
  const [hasEverConnected, setHasEverConnected] = useState(false);
  const [hasPermissions, setHasPermissions] = useState(false);
  const [isRequestingPermissions, setIsRequestingPermissions] = useState(false);
  const [permissionError, setPermissionError] = useState<string | null>(null);
  const [connectionError, setConnectionError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [hasAgreed, setHasAgreed] = useState(false);

  const videoRef = useRef<HTMLVideoElement>(null);
  const localStreamRef = useRef<MediaStream | null>(null);
  const micCaptureRef = useRef<MicCaptureHandle | null>(null);
  const pcmPlayerRef = useRef<LivePcmPlayer | null>(null);
  const sessionRef = useRef<any>(null);
  const transcriptEndRef = useRef<HTMLDivElement>(null);
  const transcriptRef = useRef<any[]>([]);
  const orchestratorRef = useRef(new TranscriptOrchestrator());
  const transcribeManagerRef = useRef<TranscribeLiveManager | null>(null);
  const usingTranscribeFallbackRef = useRef(false);
  const isMutedRef = useRef(false);
  const pendingApplicantTextRef = useRef('');
  const pendingAssistantTextRef = useRef('');
  const assistantTurnCompleteRef = useRef(false);
  const assistantFlushTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const ASSISTANT_TRANSCRIPT_DELAY_MS = 500;

  useEffect(() => {
    isMutedRef.current = isMuted;
  }, [isMuted]);

  useEffect(() => {
    if (candidateData?.status === 'completed') {
      setStage('completed');
      setIsCompleted(true);
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

  const syncTranscript = () => {
    const entries = orchestratorRef.current.getEntries();
    transcriptRef.current = entries;
    setTranscript(entries);
  };

  const addTranscriptMessage = (speaker: string, text: string) => {
    if (!text || text.trim() === "") return;

    if (speaker === 'assistant') {
      orchestratorRef.current.addAssistantFinal(text.trim());
      syncTranscript();
      return;
    }

    if (usingTranscribeFallbackRef.current) {
      pendingApplicantTextRef.current += (pendingApplicantTextRef.current ? ' ' : '') + text.trim();
    }
  };

  const finalizeCandidateTranscript = () => {
    if (!usingTranscribeFallbackRef.current) {
      return;
    }

    const text = pendingApplicantTextRef.current.trim();
    pendingApplicantTextRef.current = '';

    if (text) {
      orchestratorRef.current.addParticipantFinal(text, 'candidate');
      syncTranscript();
    }
  };

  const flushAssistantTranscript = () => {
    const text = pendingAssistantTextRef.current.trim();
    if (!text) {
      return;
    }

    pendingAssistantTextRef.current = '';
    assistantTurnCompleteRef.current = false;
    addTranscriptMessage('assistant', text);
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
    if (!text || text.trim() === "") {
      return;
    }

    pendingAssistantTextRef.current += text;
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
          if (isMutedRef.current || !sessionRef.current) {
            return;
          }

          const encodedAudio = uint8ArrayToBase64(new Uint8Array(pcmData.buffer));

          transcribeManagerRef.current?.sendAudio(encodedAudio);

          sessionRef.current.sendRealtimeInput({
            audio: {
              mimeType: "audio/pcm;rate=16000",
              data: encodedAudio,
            },
          });
        },
        (level) => setAudioLevel(level),
      );
    } catch (err) {
      console.error("Audio capture error:", err);
    }
  };

  const captureAndSavePhoto = async () => {
    if (!videoRef.current) return;
    try {
      const canvas = document.createElement('canvas');
      canvas.width = videoRef.current.videoWidth || 1280;
      canvas.height = videoRef.current.videoHeight || 720;
      const ctx = canvas.getContext('2d');
      if (ctx) {
        ctx.drawImage(videoRef.current, 0, 0, canvas.width, canvas.height);
        const base64Photo = canvas.toDataURL('image/jpeg', 0.8);

        const transcriptIdentifier = interviewData?.public_url || interviewData?.id || interviewData?.interview_id;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (transcriptIdentifier && token) {
          fetch(`/join/${transcriptIdentifier}/photo`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': token,
              'Accept': 'application/json',
            },
            body: JSON.stringify({ photo: base64Photo }),
          }).catch(err => console.error("Failed to save photo:", err));
        }
      }
    } catch (err) {
      console.error("Error capturing photo:", err);
    }
  };

  const startLiveInterview = async () => {
    if (!hasPermissions || !localStreamRef.current || isConnecting || isConnected) {
      return;
    }

    captureAndSavePhoto();

    setIsConnecting(true);
    setConnectionError(null);

    try {
      const transcriptIdentifier = interviewData?.public_url || interviewData?.id || interviewData?.interview_id;

      if (!transcriptIdentifier) {
        setConnectionError('Missing interview identifier.');
        setIsConnecting(false);
        return;
      }

      const tokenData = await fetchLiveSessionToken(`/join/${transcriptIdentifier}/live-token`);
      const ai = createGeminiLiveClient(tokenData.token);

      pcmPlayerRef.current?.stop();
      pcmPlayerRef.current = new LivePcmPlayer(() => tryFlushAssistantTranscript());

      const approvedQuestions = interviewData?.approved_questions || [];

      if (approvedQuestions.length === 0) {
        setConnectionError('No approved questions available. Please approve at least one question before starting the session.');
        setIsConnecting(false);
        return;
      }

      const systemInstruction = `CRITICAL LANGUAGE RULE:
You are allowed to speak ONLY in:
- English
- Bengali (Bangla, native script)

If the candidate speaks Bengali:
- You MUST respond in Bengali script (বাংলা)
- NEVER use Banglish (romanized Bangla)
- NEVER switch to Hindi or Urdu

Always keep technical terms (e.g., API, Laravel, Database, React) in English even inside Bengali sentences.

------------------------------------------------------------

IDENTITY:
You are "eG Credit AI", a professional AI voice interviewer.

You are conducting a structured interview.

------------------------------------------------------------

CANDIDATE INFO:
Name: ${interviewData?.candidate_name}
Role: ${interviewData?.applied_role}
Job Description: ${interviewData?.job_description || "N/A"}

------------------------------------------------------------

APPROVED QUESTIONS:
You MUST ONLY ask these questions, in order:

${approvedQuestions.map((q: string, i: number) => (i + 1) + '. ' + q).join('\n')}

------------------------------------------------------------

INTERVIEW FLOW (VERY IMPORTANT)

STEP 1: GREETING
- Greet the candidate politely
- Introduce yourself briefly
- Ask if they are ready
- STOP and WAIT for candidate response

DO NOT ASK ANY QUESTION YET

------------------------------------------------------------

STEP 2: QUESTION FLOW

For EACH question:

1. Ask exactly ONE approved question only.
2. STOP speaking immediately after the question.
3. WAIT for the candidate to answer before asking anything else.

After candidate answers:
- Give a short acknowledgment (1 sentence max)
- Move to next approved question if there is one

IMPORTANT:
- Do NOT ask multiple questions in the same turn.
- Do NOT ask any question outside the approved list.
- Do NOT repeat questions unless the candidate asked you to repeat.
- Do NOT ask follow-up questions, probing questions, or clarification questions.
- Do NOT ask "skip or continue" unless the candidate explicitly says "skip", "I don't know", or "stop".

If the candidate says:
- "I don't know" → say a polite one-sentence acknowledgment and continue to the next approved question.
- "skip" or "move on" → skip the current question and continue to the next approved question.
- The candidate remains silent → wait patiently, then gently prompt once.

If there are no more approved questions:
- Thank the candidate.
- Say: "Thank you. The interview is complete. Please click End Session to submit your responses."
- Do NOT ask any new questions.
- Do NOT continue the conversation.

------------------------------------------------------------

CONVERSATION STYLE

- Natural, professional, human-like
- Short and clear sentences
- No robotic tone
- No long explanations

------------------------------------------------------------

TRANSCRIPT RULE

Your responses are shown live in transcript.

So:
- Speak clearly
- Avoid unnecessary filler words
- Do not include formatting or symbols

------------------------------------------------------------

STRICT OUTPUT RULE

- Output ONLY what you say aloud
- No explanations
- No JSON
- No markdown
- No internal reasoning

------------------------------------------------------------

START NOW:
Begin with greeting only, then WAIT.`;

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
          },

          onmessage: async (message: LiveServerMessage) => {
            const serverContent = (message as any).serverContent;

            const base64Audio = serverContent?.modelTurn?.parts?.[0]?.inlineData?.data;
            if (base64Audio) {
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
              if (userText && usingTranscribeFallbackRef.current) {
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
                bufferAssistantText(aiText);
              }
            }

            if (serverContent?.turnComplete) {
              assistantTurnCompleteRef.current = true;
              finalizeCandidateTranscript();
              tryFlushAssistantTranscript();
            } else if (serverContent?.modelTurn) {
              assistantTurnCompleteRef.current = false;
            }
          },

          onclose: () => {
            if (!hasEverConnected) {
              setConnectionError("Connection closed unexpectedly. Please try again.");
              setIsConnecting(false);
              setIsConnected(false);
              sessionRef.current = null;
            } else {
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
            } else {
              stopInterview();
            }
          }
        }
      });

      sessionRef.current = session;
      await startAudioCapture();

      session.sendRealtimeInput({
        text: 'Please begin with your greeting only, wait for the candidate reply, and then continue the approved questions one by one.',
      });

      void (async () => {
        try {
          const transcribeToken = await fetchLiveSessionToken(`/join/${transcriptIdentifier}/live-token`);
          const transcribeAi = createGeminiLiveClient(transcribeToken.token);
          transcribeManagerRef.current = new TranscribeLiveManager(
            transcribeAi,
            transcribeToken.transcriptionModel,
            {
              participantSpeaker: 'candidate',
              languageCodes: ['en-US', 'bn-BD'],
              context: 'recruitment',
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

  const stopInterview = async () => {
    const transcriptIdentifier =
      interviewData?.public_url || interviewData?.id || interviewData?.interview_id;

    if (!transcriptIdentifier) {
      alert("Missing interview identifier.");
      return;
    }

    try {
      setIsSaving(true);

      await transcribeManagerRef.current?.stop();
      transcribeManagerRef.current = null;

      if (pendingAssistantTextRef.current.trim()) {
        flushAssistantTranscript();
      }

      const transcriptText = orchestratorRef.current.toSaveFormat('Candidate');

      const token = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

      await fetch(`/join/${transcriptIdentifier}/transcript`, {
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

      if (sessionRef.current) {
        try {
          sessionRef.current.close();
        } catch (_) { }
        sessionRef.current = null;
      }

      if (localStreamRef.current) {
        localStreamRef.current.getTracks().forEach((track) => track.stop());
        localStreamRef.current = null;
      }

      micCaptureRef.current?.stop();
      micCaptureRef.current = null;
      pcmPlayerRef.current?.stop();
      pcmPlayerRef.current = null;

      setIsConnected(false);
      setIsConnecting(false);
      setIsCompleted(true);
      setStage('completed');

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

  if (isCompleted || interviewData?.status === 'completed' || stage === 'completed') {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-slate-950 text-white p-6">
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
              Interview Completed Successfully
            </h2>
            <p className="text-slate-400 leading-relaxed">
              Your responses have been securely saved. The recruiting team will review your session and contact you shortly.
            </p>
          </div>
        </motion.div>
      </div>
    );
  }

  if (stage === 'gate') {
    return (
      <CandidateGate
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
                We need access to your camera and microphone to conduct the interview.
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
                  <AlertCircle className="w-4 h-4" /> Important Rules
                </h4>
                <p className="text-xs text-rose-200/80 leading-relaxed">
                  No cheating, plagiarism, or outside help is allowed. Your session is monitored and recorded.
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
                {connectionError ? 'Retry Connection' : 'Start Interview'}
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
    />
  );
};