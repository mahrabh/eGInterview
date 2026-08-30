import React from "react";
import { motion } from "framer-motion";
import { Clock, AlertCircle, CheckCircle2, Play, Sparkles } from "lucide-react";

interface CandidateGateProps {
  interviewData: any;
  onSuccess: (interviewData: any) => void;
}

export const CandidateGate: React.FC<CandidateGateProps> = ({
  interviewData,
  onSuccess,
}) => {
  const now = Date.now();
  const expiresAt =
    typeof interviewData?.window_expires_at === "number"
      ? interviewData.window_expires_at
      : 0;

  const isCompleted = interviewData?.status === "completed";
  const isExpired =
    interviewData?.session_status === "expired" ||
    (expiresAt > 0 && now >= expiresAt && interviewData?.session_status !== "in-progress");

  const isPending = interviewData?.session_status === "pending";

  const BackgroundElements = () => (
    <div className="absolute inset-0 overflow-hidden pointer-events-none">
      <div className="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-600/20 blur-[120px]" />
      <div className="absolute bottom-[-20%] right-[-10%] w-[60%] h-[60%] rounded-full bg-blue-600/10 blur-[120px]" />
    </div>
  );

  const CardWrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => (
    <div className="relative flex flex-col items-center justify-center min-h-screen bg-slate-950 text-white p-6 overflow-hidden font-sans">
      <BackgroundElements />
      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.95 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
        className="relative z-10 max-w-md w-full backdrop-blur-2xl bg-white/[0.03] border border-white/[0.08] rounded-[2rem] p-10 text-center space-y-8 shadow-[0_0_40px_rgba(0,0,0,0.5)]"
      >
        {children}
      </motion.div>
    </div>
  );

  if (isCompleted) {
    return (
      <CardWrapper>
        <motion.div 
          initial={{ scale: 0 }} 
          animate={{ scale: 1 }} 
          transition={{ type: "spring", delay: 0.2 }}
          className="w-24 h-24 bg-emerald-500/10 rounded-[2rem] flex items-center justify-center mx-auto text-emerald-400 shadow-[inset_0_0_20px_rgba(16,185,129,0.1)] border border-emerald-500/20"
        >
          <CheckCircle2 className="w-12 h-12" />
        </motion.div>
        <div className="space-y-3">
          <h3 className="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-400">Interview Completed</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            You have already completed this interview. Your responses have been saved successfully and our team will be in touch.
          </p>
        </div>
      </CardWrapper>
    );
  }

  if (isExpired) {
    return (
      <CardWrapper>
        <motion.div 
          initial={{ scale: 0 }} 
          animate={{ scale: 1 }} 
          transition={{ type: "spring", delay: 0.2 }}
          className="w-24 h-24 bg-rose-500/10 rounded-[2rem] flex items-center justify-center mx-auto text-rose-400 shadow-[inset_0_0_20px_rgba(244,63,94,0.1)] border border-rose-500/20"
        >
          <AlertCircle className="w-12 h-12" />
        </motion.div>
        <div className="space-y-3">
          <h3 className="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-400">Link Expired</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            This interview link is no longer active. Please contact your recruiter to request a new session link.
          </p>
        </div>
      </CardWrapper>
    );
  }

  if (isPending) {
    return (
      <CardWrapper>
        <motion.div 
          initial={{ scale: 0 }} 
          animate={{ scale: 1 }} 
          transition={{ type: "spring", delay: 0.2 }}
          className="w-24 h-24 bg-amber-500/10 rounded-[2rem] flex items-center justify-center mx-auto text-amber-400 shadow-[inset_0_0_20px_rgba(245,158,11,0.1)] border border-amber-500/20"
        >
          <Clock className="w-12 h-12 animate-[spin_4s_linear_infinite]" />
        </motion.div>
        <div className="space-y-3">
          <h3 className="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-400">Waiting Room</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            Your interview is not open yet. Please wait for the recruiter to activate your secure session.
          </p>
        </div>
      </CardWrapper>
    );
  }

  return (
    <CardWrapper>
      <motion.div 
        initial={{ scale: 0 }} 
        animate={{ scale: 1 }} 
        transition={{ type: "spring", delay: 0.2 }}
        className="relative w-24 h-24 mx-auto"
      >
        <div className="absolute inset-0 bg-blue-500/20 rounded-[2rem] blur-xl animate-pulse" />
        <div className="relative w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 rounded-[2rem] flex items-center justify-center text-white shadow-xl border border-white/10">
          <Sparkles className="w-10 h-10" />
        </div>
      </motion.div>

      <div className="space-y-3">
        <h3 className="text-3xl font-black tracking-tight text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-300">
          Welcome to Your AI Interview
        </h3>
        <p className="text-sm text-slate-400 leading-relaxed px-4">
          Hi {interviewData?.candidate_name?.split(' ')[0] || 'Candidate'}, you have applied for the <span className="text-white font-medium">{interviewData?.applied_role}</span> role.
        </p>
      </div>

      <motion.button
        whileHover={{ scale: 1.02 }}
        whileTap={{ scale: 0.98 }}
        onClick={() => onSuccess(interviewData)}
        className="w-full py-4 bg-white text-slate-950 hover:bg-slate-100 rounded-2xl font-black transition-colors shadow-[0_0_30px_rgba(255,255,255,0.15)] flex items-center justify-center gap-2 group mt-4"
      >
        Continue to Setup
        <Play className="w-4 h-4 fill-slate-950 group-hover:translate-x-1 transition-transform" />
      </motion.button>
    </CardWrapper>
  );
};