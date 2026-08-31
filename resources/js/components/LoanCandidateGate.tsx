import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShieldCheck, User, Fingerprint, Loader2 } from 'lucide-react';

interface LoanCandidateGateProps {
  interviewData: any;
  onSuccess: (data: any) => void;
}

export const LoanCandidateGate: React.FC<LoanCandidateGateProps> = ({ interviewData, onSuccess }) => {
  const [nid, setNid] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (nid.length < 10 || nid.length > 17) {
      setError('Please enter a valid NID number (10 to 17 digits).');
      return;
    }

    setIsSubmitting(true);
    setError('');

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      
      const response = await fetch(`/loan-interview/${interviewData.public_url}/start`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token || '',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ nid })
      });

      if (!response.ok) {
        throw new Error('Failed to verify session.');
      }

      onSuccess(interviewData);
    } catch (err: any) {
      setError('An error occurred. Please try again or contact support.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-slate-950 text-white p-6 relative overflow-hidden font-sans">
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-indigo-900/20 via-slate-950 to-slate-950 z-0" />
      
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-lg z-10"
      >
        <div className="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-[2.5rem] p-10 shadow-2xl overflow-hidden relative">
          <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-blue-500 to-emerald-500" />
          
          <div className="text-center space-y-6 mb-10">
            <div className="w-20 h-20 bg-indigo-500/10 rounded-[1.5rem] flex items-center justify-center mx-auto text-indigo-500 ring-1 ring-indigo-500/20 shadow-inner">
              <ShieldCheck className="w-10 h-10" />
            </div>
            
            <div className="space-y-2">
              <h2 className="text-3xl font-black tracking-tight text-white">Identity Verification</h2>
              <p className="text-slate-400 text-sm">Please verify your identity to begin the loan application interview.</p>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="space-y-4">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                  <User className="w-5 h-5" />
                </div>
                <input
                  type="text"
                  value={interviewData?.candidate_name || ''}
                  disabled
                  className="w-full pl-12 pr-4 py-4 bg-slate-950/50 border border-slate-800 rounded-2xl text-slate-300 font-medium cursor-not-allowed"
                />
              </div>

              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                  <Fingerprint className="w-5 h-5" />
                </div>
                <input
                  type="password"
                  value={nid}
                  onChange={(e) => setNid(e.target.value.replace(/\D/g, ''))}
                  placeholder="National ID Number (NID)"
                  maxLength={17}
                  className="w-full pl-12 pr-4 py-4 bg-slate-950/50 border border-slate-700 rounded-2xl text-white font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all placeholder:text-slate-600"
                  required
                />
              </div>
            </div>

            <AnimatePresence>
              {error && (
                <motion.div
                  initial={{ opacity: 0, height: 0 }}
                  animate={{ opacity: 1, height: 'auto' }}
                  exit={{ opacity: 0, height: 0 }}
                  className="text-red-400 text-sm text-center font-medium bg-red-500/10 py-3 rounded-xl border border-red-500/20"
                >
                  {error}
                </motion.div>
              )}
            </AnimatePresence>

            <button
              type="submit"
              disabled={isSubmitting || !nid}
              className="w-full py-4 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 rounded-2xl font-bold text-white shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                "Verify & Continue"
              )}
            </button>
            
            <p className="text-xs text-center text-slate-500 font-medium">
              Your NID is securely encrypted. We only store the last 4 digits for reference.
            </p>
          </form>
        </div>
      </motion.div>
    </div>
  );
};
