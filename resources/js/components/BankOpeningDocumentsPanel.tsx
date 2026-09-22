import React, { useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import {
  CheckCircle2,
  FileText,
  Loader2,
  Trash2,
  Upload,
} from 'lucide-react';

export interface DocumentRequirement {
  key: string;
  label: string;
  description?: string | null;
  required: boolean;
  max_files: number;
  conditional?: boolean;
}

export interface AccountTypeOption {
  slug: string;
  label: string;
  group_label?: string;
  short_description?: string;
}

export interface UploadedDocument {
  id: string;
  document_type: string;
  label?: string;
  original_name: string;
  mime_type?: string | null;
  size_bytes?: number;
  is_image?: boolean;
  created_at?: string | null;
}

interface BankOpeningDocumentsPanelProps {
  token: string;
  applicantName: string;
  accountType: string | null;
  accountLabel: string | null;
  accountConfirmed: boolean;
  accountTypeOptions: AccountTypeOption[];
  requirements: DocumentRequirement[];
  documents: UploadedDocument[];
  applicationSubmitted: boolean;
  resubmissionReason?: string | null;
  completionMessage?: string | null;
  documentsWindowHours?: number;
  publicTokenExpiry?: string | null;
  documentsWindowExpiresAt?: string | null;
  csrfToken: string;
  onBootstrapUpdate: (data: any) => void;
  onDocumentsChange: (docs: UploadedDocument[]) => void;
  onSubmitted: () => void;
}

function formatBytes(bytes?: number) {
  if (!bytes || bytes <= 0) return '';
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export const BankOpeningDocumentsPanel: React.FC<BankOpeningDocumentsPanelProps> = ({
  token,
  applicantName,
  accountType,
  accountLabel,
  accountConfirmed,
  accountTypeOptions,
  requirements,
  documents,
  applicationSubmitted,
  resubmissionReason,
  completionMessage,
  documentsWindowHours = 24,
  publicTokenExpiry,
  documentsWindowExpiresAt,
  csrfToken,
  onBootstrapUpdate,
  onDocumentsChange,
  onSubmitted,
}) => {
  const [selectedSlug, setSelectedSlug] = useState(accountType || '');
  const [accountBusy, setAccountBusy] = useState(false);
  const [uploadBusyKey, setUploadBusyKey] = useState<string | null>(null);
  const [removingId, setRemovingId] = useState<string | null>(null);
  const [submitBusy, setSubmitBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<string | null>(null);
  const inputRefs = useRef<Record<string, HTMLInputElement | null>>({});
  const toastTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    setSelectedSlug(accountType || '');
  }, [accountType]);

  useEffect(() => () => {
    if (toastTimerRef.current) clearTimeout(toastTimerRef.current);
  }, []);

  const showToast = (message: string) => {
    if (toastTimerRef.current) clearTimeout(toastTimerRef.current);
    setToast(message);
    toastTimerRef.current = setTimeout(() => setToast(null), 2500);
  };

  const docsByType = useMemo(() => {
    const map: Record<string, UploadedDocument[]> = {};
    for (const doc of documents) {
      const key = doc.document_type;
      if (!map[key]) map[key] = [];
      map[key].push(doc);
    }
    return map;
  }, [documents]);

  const requiredGroups = requirements.filter((g) => g.required);
  const otherGroups = requirements.filter((g) => !g.required);
  const completedFields = requirements.filter((g) => (docsByType[g.key] || []).length > 0).length;
  const progressPct = requirements.length
    ? Math.round((completedFields / requirements.length) * 100)
    : 0;
  const canSubmit = accountConfirmed
    && requiredGroups.every((g) => (docsByType[g.key] || []).length > 0)
    && !applicationSubmitted;
  const accountDirty = !!selectedSlug && selectedSlug !== (accountType || '');

  const groupedOptions = useMemo(() => {
    const groups: Record<string, AccountTypeOption[]> = {};
    for (const opt of accountTypeOptions) {
      const key = opt.group_label || 'Accounts';
      if (!groups[key]) groups[key] = [];
      groups[key].push(opt);
    }
    return groups;
  }, [accountTypeOptions]);

  const refreshDocuments = async () => {
    const response = await fetch(`/bank-opening/${token}/documents`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const data = await response.json();
    if (response.ok) {
      if (Array.isArray(data.documents)) onDocumentsChange(data.documents);
      onBootstrapUpdate(data);
    }
  };

  const saveAccountType = async () => {
    if (!selectedSlug || accountBusy) return;
    setAccountBusy(true);
    setError(null);
    try {
      const response = await fetch(`/bank-opening/${token}/documents/account-type`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ account_type: selectedSlug }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Could not save account type');
      onBootstrapUpdate(data);
      if (Array.isArray(data.documents)) onDocumentsChange(data.documents);
      showToast('Account type saved');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save account type');
    } finally {
      setAccountBusy(false);
    }
  };

  const uploadForGroup = async (groupKey: string, files: FileList | null) => {
    if (!files || files.length === 0) return;
    setError(null);
    setUploadBusyKey(groupKey);

    try {
      for (const file of Array.from(files)) {
        const form = new FormData();
        form.append('document_type', groupKey);
        form.append('file', file);
        const response = await fetch(`/bank-opening/${token}/documents`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: form,
          credentials: 'same-origin',
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Upload failed');
      }
      showToast('File uploaded');
      await refreshDocuments();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Upload failed');
    } finally {
      setUploadBusyKey(null);
      const input = inputRefs.current[groupKey];
      if (input) input.value = '';
    }
  };

  const removeDoc = async (docId: string) => {
    setError(null);
    setRemovingId(docId);
    try {
      const response = await fetch(`/bank-opening/${token}/documents/${docId}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || 'Could not remove file');
      onDocumentsChange(documents.filter((d) => d.id !== docId));
      showToast('File removed');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not remove file');
    } finally {
      setRemovingId(null);
    }
  };

  const submit = async () => {
    if (!canSubmit || submitBusy) return;
    setSubmitBusy(true);
    setError(null);
    try {
      const response = await fetch(`/bank-opening/${token}/submit`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Submit failed');
      onSubmitted();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Submit failed');
    } finally {
      setSubmitBusy(false);
    }
  };

  if (applicationSubmitted) {
    return (
      <div className="min-h-screen bg-slate-50 text-slate-900 flex items-center justify-center px-4 py-10 font-sans">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"
        >
          <div className="mx-auto w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
            <CheckCircle2 className="w-8 h-8" />
          </div>
          <h2 className="mt-5 text-2xl font-black tracking-tight">Submitted for review</h2>
          <p className="mt-3 text-sm text-slate-600 leading-relaxed">
            Thank you. Your information and documents have been submitted for manual review.
          </p>
          <p className="mt-4 text-xs text-slate-400">You may close this page.</p>
        </motion.div>
      </div>
    );
  }

  const renderGroupCard = (group: DocumentRequirement) => {
    const files = docsByType[group.key] || [];
    const atMax = files.length >= group.max_files;
    const done = files.length > 0;
    const busy = uploadBusyKey === group.key;

    return (
      <div
        key={group.key}
        className={`h-full flex flex-col rounded-2xl border p-4 space-y-3 shadow-sm ${
          done
            ? 'border-emerald-200 bg-emerald-50/70'
            : 'border-slate-200 bg-white'
        }`}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h3 className="text-sm font-bold text-slate-900 leading-snug">{group.label}</h3>
            {group.description && (
              <p className="mt-1 text-xs text-slate-500 leading-relaxed line-clamp-2">{group.description}</p>
            )}
            <p className="mt-1.5 text-[11px] text-slate-400">
              Up to {group.max_files} · JPG/PNG/PDF/WEBP · 10 MB
            </p>
          </div>
          <span className={`shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-md ${
            done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'
          }`}>
            {done ? `${files.length}` : 'Empty'}
          </span>
        </div>

        {files.length > 0 && (
          <ul className="space-y-1.5 max-h-28 overflow-y-auto">
            {files.map((doc) => (
              <li
                key={doc.id}
                className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1.5"
              >
                <FileText className="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                <div className="min-w-0 flex-1">
                  <p className="text-[11px] font-semibold text-slate-800 truncate">{doc.original_name}</p>
                  {doc.size_bytes ? (
                    <p className="text-[10px] text-slate-400">{formatBytes(doc.size_bytes)}</p>
                  ) : null}
                </div>
                <button
                  type="button"
                  disabled={removingId === doc.id || submitBusy}
                  onClick={() => void removeDoc(doc.id)}
                  className="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 disabled:opacity-40"
                  title="Remove"
                >
                  {removingId === doc.id ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Trash2 className="w-3.5 h-3.5" />}
                </button>
              </li>
            ))}
          </ul>
        )}

        <div className="mt-auto pt-1">
          <input
            ref={(el) => {
              inputRefs.current[group.key] = el;
            }}
            type="file"
            accept=".jpg,.jpeg,.png,.pdf,.webp,image/*,application/pdf"
            multiple
            disabled={busy || atMax || submitBusy}
            className="hidden"
            onChange={(e) => void uploadForGroup(group.key, e.target.files)}
          />
          <button
            type="button"
            disabled={busy || atMax || submitBusy}
            onClick={() => inputRefs.current[group.key]?.click()}
            className="w-full py-2.5 rounded-xl border border-dashed border-slate-300 hover:border-indigo-400 hover:bg-indigo-50 text-xs font-semibold text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-colors"
          >
            {busy ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Upload className="w-3.5 h-3.5" />}
            {atMax ? 'Max reached' : files.length ? 'Add file' : 'Upload'}
          </button>
        </div>
      </div>
    );
  };

  return (
    <div className="min-h-screen bg-slate-100 text-slate-900 font-sans">
      {/* Top completion banner */}
      <div className="bg-indigo-600 text-white">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-5">
          <p className="text-sm sm:text-[15px] font-medium leading-relaxed">
            {completionMessage
              || 'Thank you. Your interview is complete. Please submit the requested documents for manual review.'}
          </p>
        </div>
      </div>

      <div className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur-md shadow-sm">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div className="min-w-0">
              <p className="text-[11px] font-bold uppercase tracking-widest text-indigo-600">Document upload</p>
              <h1 className="text-xl sm:text-2xl font-black tracking-tight mt-0.5 truncate text-slate-900">
                {applicantName || 'Applicant'}
              </h1>
              <p className="text-sm text-slate-500 mt-1">
                {accountConfirmed && accountLabel
                  ? <>Account type: <span className="text-slate-800 font-semibold">{accountLabel}</span></>
                  : 'Select an account type to continue'}
              </p>
              <p className="text-[11px] text-slate-400 mt-1">
                Upload window: {documentsWindowHours} hours
                {(documentsWindowExpiresAt || publicTokenExpiry)
                  ? ` · expires ${new Date(documentsWindowExpiresAt || publicTokenExpiry as string).toLocaleString()}`
                  : ''}
              </p>
            </div>

            <div className="w-full lg:w-72 shrink-0">
              <div className="flex items-center justify-between text-xs mb-1.5">
                <span className="text-slate-500">Progress</span>
                <span className="font-semibold text-slate-700">
                  {accountConfirmed
                    ? `${completedFields}/${Math.max(requirements.length, 1)} · ${progressPct}%`
                    : 'Waiting'}
                </span>
              </div>
              <div className="h-2 rounded-full bg-slate-200 overflow-hidden">
                <div
                  className="h-full bg-indigo-500 transition-all"
                  style={{ width: `${accountConfirmed ? progressPct : 0}%` }}
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-32">
        {resubmissionReason && (
          <div className="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p className="font-bold text-xs uppercase tracking-wide text-amber-700 mb-1">Resubmission requested</p>
            {resubmissionReason}
          </div>
        )}

        {error && (
          <div className="mb-5 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 text-sm px-4 py-3">
            {error}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-[280px_minmax(0,1fr)] gap-5 items-start">
          <aside className="lg:sticky lg:top-28">
            <div className="rounded-2xl border border-slate-200 bg-white p-5 space-y-4 shadow-sm">
              <div>
                <h2 className="text-sm font-bold text-slate-900">Account type</h2>
                <p className="mt-1 text-xs text-slate-500 leading-relaxed">
                  Choose the UCB account type. Document fields update after you save.
                </p>
              </div>

              <select
                value={selectedSlug}
                onChange={(e) => setSelectedSlug(e.target.value)}
                disabled={accountBusy || submitBusy}
                className="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-200 text-sm text-slate-900 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
              >
                <option value="">Select account type…</option>
                {Object.entries(groupedOptions).map(([groupLabel, opts]) => (
                  <optgroup key={groupLabel} label={groupLabel}>
                    {opts.map((opt) => (
                      <option key={opt.slug} value={opt.slug}>{opt.label}</option>
                    ))}
                  </optgroup>
                ))}
              </select>

              <button
                type="button"
                disabled={!selectedSlug || accountBusy || submitBusy || (accountConfirmed && !accountDirty)}
                onClick={() => void saveAccountType()}
                className="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white text-sm font-semibold flex items-center justify-center gap-2"
              >
                {accountBusy ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                {accountConfirmed ? (accountDirty ? 'Save account type' : 'Saved') : 'Continue'}
              </button>

              {accountDirty && (
                <p className="text-[11px] text-amber-700">
                  Header updates only after you save.
                </p>
              )}
            </div>
          </aside>

          <main className="min-w-0 w-full space-y-6">
            {!accountConfirmed ? (
              <div className="min-h-[280px] rounded-2xl border border-dashed border-slate-300 bg-white flex items-center justify-center px-6 text-center shadow-sm">
                <div className="max-w-md space-y-2">
                  <p className="text-base font-semibold text-slate-800">Select an account type first</p>
                  <p className="text-sm text-slate-500">
                    Use the panel on the left, then save. Matching document fields will appear here.
                  </p>
                </div>
              </div>
            ) : (
              <>
                {requiredGroups.length > 0 && (
                  <section className="space-y-3">
                    <div className="flex items-end justify-between gap-3">
                      <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">Identity document</h2>
                      <p className="text-[11px] text-slate-400">Needed to submit</p>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 w-full">
                      {requiredGroups.map(renderGroupCard)}
                    </div>
                  </section>
                )}

                {otherGroups.length > 0 && (
                  <section className="space-y-3">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-500">Additional documents</h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 w-full">
                      {otherGroups.map(renderGroupCard)}
                    </div>
                  </section>
                )}
              </>
            )}
          </main>
        </div>
      </div>

      {/* Transient toast — top-right, auto fades */}
      {toast && (
        <div className="fixed top-4 right-4 z-50 pointer-events-none">
          <motion.div
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm font-semibold px-4 py-2.5 shadow-lg"
          >
            {toast}
          </motion.div>
        </div>
      )}

      <div className="fixed bottom-0 inset-x-0 z-20 border-t border-slate-200 bg-white/95 backdrop-blur-md shadow-[0_-4px_20px_rgba(15,23,42,0.06)]">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col sm:flex-row sm:items-center gap-3">
          <p className="text-[11px] text-slate-500 sm:flex-1">
            {!accountConfirmed
              ? 'Select and save an account type first.'
              : canSubmit
                ? 'Ready to submit for manual review.'
                : 'Upload the identity document to enable submit.'}
          </p>
          <button
            type="button"
            disabled={!canSubmit || submitBusy || !!uploadBusyKey || accountBusy}
            onClick={() => void submit()}
            className="w-full sm:w-auto sm:min-w-[260px] py-3.5 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-sm flex items-center justify-center gap-2 transition-colors"
          >
            {submitBusy ? <><Loader2 className="w-4 h-4 animate-spin" /> Submitting…</> : 'Submit for manual review'}
          </button>
        </div>
      </div>
    </div>
  );
};
