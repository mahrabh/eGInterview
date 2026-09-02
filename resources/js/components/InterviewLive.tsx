import React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Square,
  Bot,
  Loader2,
  MessageSquare,
  Activity,
  User,
} from 'lucide-react';

interface TranscriptItem {
  id?: string;
  speaker: string;
  text: string;
}

interface InterviewLiveProps {
  data: any;
  videoRef: React.RefObject<HTMLVideoElement | null>;
  isSaving: boolean;
  audioLevel: number;
  isMuted: boolean;
  setIsMuted: (muted: boolean) => void;
  stopInterview: () => void;
  transcript: TranscriptItem[];
  transcriptEndRef: React.RefObject<HTMLDivElement | null>;
  wrapUpState?: string;
  closingCountdown?: number | null;
  participantLabel?: string;
}

export const InterviewLive: React.FC<InterviewLiveProps> = ({
  data,
  videoRef,
  isSaving,
  audioLevel,
  isMuted,
  setIsMuted,
  stopInterview,
  transcript,
  transcriptEndRef,
  wrapUpState,
  closingCountdown,
  participantLabel = 'Me',
}) => {
  const isClosingDone = wrapUpState === 'closing_done';
  // Warn user before leaving if session isn't saved
  React.useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
      return '';
    };
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, []);

  // A dynamic height for the audio visualizer based on the audio level
  const audioIndicatorHeight = Math.min(100, Math.max(10, audioLevel * 200)) + '%';

  return (
    <div className="flex h-screen bg-[#020617] text-white overflow-hidden font-sans">
      {/* Main Video Area */}
      <div className="flex-1 relative flex flex-col p-6 pr-3">
        <div className="flex-1 relative rounded-[2rem] overflow-hidden bg-slate-900 border border-slate-800 shadow-2xl">
          <video
            ref={videoRef}
            autoPlay
            muted
            playsInline
            className="w-full h-full object-cover"
          />
          
          <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-slate-950/40 pointer-events-none" />

          {/* Header overlay */}
          <div className="absolute top-6 left-8 right-8 flex justify-between items-center z-10">
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2 px-4 py-2 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
                <div className="w-2 h-2 rounded-full bg-rose-500 animate-pulse" />
                <span className="text-xs font-bold tracking-wider uppercase text-slate-200">Live Session</span>
              </div>
              <div className="px-4 py-2 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
                <span className="text-xs font-medium text-slate-300">
                  {data?.candidate_name} • {data?.applied_role}
                </span>
              </div>
            </div>
          </div>

          {/* Audio Visualizer Overlay */}
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex items-center justify-center opacity-30 pointer-events-none">
            <motion.div 
              animate={{ scale: 1 + audioLevel * 10 }}
              transition={{ type: "spring", stiffness: 300, damping: 20 }}
              className="w-32 h-32 rounded-full bg-blue-500 blur-3xl mix-blend-screen"
            />
          </div>

          {/* Floating Controls */}
          <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-4 z-20">
            <div className="flex items-center gap-4">
              <button
                onClick={stopInterview}
                disabled={isSaving}
                className={`flex items-center gap-2 px-8 py-4 ${isClosingDone ? 'bg-emerald-600 hover:bg-emerald-500 animate-pulse shadow-[0_0_30px_rgba(16,185,129,0.5)] scale-105' : 'bg-rose-600 hover:bg-rose-500 shadow-[0_0_20px_rgba(225,29,72,0.3)]'} disabled:opacity-50 text-white rounded-2xl font-bold transition-all`}
              >
                {isSaving ? (
                  <Loader2 className="w-5 h-5 animate-spin" />
                ) : (
                  <Square className="w-5 h-5 fill-current" />
                )}
                {isSaving ? 'Ending Session...' : 'End Session'}
              </button>
            </div>
            <p className="text-xs text-rose-200/80 font-medium bg-black/40 px-4 py-1.5 rounded-full border border-rose-500/20">
              {isClosingDone && closingCountdown !== null
                ? `Session ending automatically in ${closingCountdown} second${closingCountdown === 1 ? '' : 's'}… You may click "End Session" now.`
                : isClosingDone
                  ? 'Click "End Session" now to submit your interview.'
                  : 'Please click "End Session" when the interview is complete.'}
            </p>
          </div>
        </div>
      </div>

      {/* Transcript Sidebar */}
      <div className="w-[420px] p-6 pl-3 flex flex-col h-full relative">
        <div className="flex-1 flex flex-col bg-white/[0.02] backdrop-blur-3xl border border-white/[0.05] rounded-[2rem] overflow-hidden shadow-2xl relative">
          
          {/* Decorative background glow */}
          <div className="absolute top-[-20%] right-[-20%] w-[80%] h-[50%] rounded-full bg-indigo-600/10 blur-[100px] pointer-events-none" />

          <div className="px-8 py-6 border-b border-white/[0.05] z-10 flex items-center justify-between">
            <h3 className="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
              <MessageSquare className="w-4 h-4 text-indigo-400" />
              Live Transcript
            </h3>
            
            {/* Audio Activity Indicator */}
            <div className="flex items-center gap-1.5 h-4">
              {[1, 2, 3].map((i) => (
                <motion.div
                  key={i}
                  animate={{ height: audioLevel > 0.01 ? ['20%', '100%', '20%'] : '20%' }}
                  transition={{ repeat: Infinity, duration: 0.5 + i * 0.1, ease: "easeInOut" }}
                  className="w-1 bg-indigo-500 rounded-full"
                />
              ))}
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-5 space-y-4 z-10 scrollbar-hide">
            {transcript.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-center space-y-4 opacity-40">
                <div className="w-16 h-16 rounded-[2rem] bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                  <Bot className="w-8 h-8 text-indigo-400" />
                </div>
                <p className="text-sm font-medium text-slate-400 max-w-[200px] leading-relaxed">
                  Waiting for conversation to start...
                </p>
              </div>
            ) : (
              <AnimatePresence initial={false}>
                {transcript.map((entry, index) => {
                  const isAssistant = entry.speaker === 'assistant';

                  return (
                    <motion.div
                      key={entry.id ?? `transcript-${index}`}
                      initial={{ opacity: 0, y: 8, scale: 0.98 }}
                      animate={{ opacity: 1, y: 0, scale: 1 }}
                      className={`flex items-end gap-2.5 ${
                        isAssistant ? 'flex-row' : 'flex-row-reverse'
                      }`}
                    >
                      <div
                        className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 border ${
                          isAssistant
                            ? 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30'
                            : 'bg-slate-800/90 text-slate-300 border-slate-700/80'
                        }`}
                      >
                        {isAssistant ? (
                          <span className="text-[10px] font-bold tracking-wide">AI</span>
                        ) : (
                          <User className="w-3.5 h-3.5" strokeWidth={2.25} />
                        )}
                      </div>

                      <div
                        className={`flex min-w-0 flex-col gap-1 ${
                          isAssistant ? 'items-start' : 'items-end'
                        } max-w-[calc(100%-2.75rem)]`}
                      >
                        {!isAssistant && (
                          <span className="px-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            {participantLabel}
                          </span>
                        )}

                        <div
                          className={`inline-block w-fit max-w-full px-3.5 py-2.5 text-[13px] leading-snug break-words shadow-sm ${
                            isAssistant
                              ? 'rounded-2xl rounded-tl-md bg-indigo-500/10 text-indigo-50 border border-indigo-500/20'
                              : 'rounded-2xl rounded-tr-md bg-slate-800/70 text-slate-100 border border-slate-700/40'
                          }`}
                        >
                          {entry.text}
                        </div>
                      </div>
                    </motion.div>
                  );
                })}
              </AnimatePresence>
            )}
            <div ref={transcriptEndRef} />
          </div>
        </div>
      </div>
    </div>
  );
};