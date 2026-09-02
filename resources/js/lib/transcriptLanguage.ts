/** Bengali script */
const BENGALI = /[\u0980-\u09FF]/;

/** Latin / English */
const LATIN = /[A-Za-z]/;

/**
 * Hindi (Devanagari), Japanese, CJK, Korean, Arabic — not allowed in loan interviews.
 * These often appear when Live STT mis-detects Bengali speech.
 */
const DISALLOWED_SCRIPTS = /[\u0900-\u097F\u3040-\u30FF\u4E00-\u9FFF\uAC00-\uD7AF\u0600-\u06FF]/;

export function containsDisallowedScript(text: string): boolean {
  return DISALLOWED_SCRIPTS.test(text);
}

/**
 * Keep Bengali/English participant text. Drop turns that are mostly wrong-script
 * mis-transcriptions (e.g. Hindi Devanagari from Bengali speech).
 */
export function sanitizeParticipantTranscript(text: string): string | null {
  const trimmed = text.trim();
  if (!trimmed) {
    return null;
  }

  const disallowedMatches = trimmed.match(new RegExp(DISALLOWED_SCRIPTS.source, 'g')) ?? [];
  const disallowedCount = disallowedMatches.join('').length;

  if (disallowedCount === 0) {
    return trimmed;
  }

  const compact = trimmed.replace(/\s+/g, '');
  const ratio = compact.length === 0 ? 0 : disallowedCount / compact.length;
  if (ratio >= 0.25) {
    return null;
  }

  const cleaned = trimmed
    .replace(new RegExp(DISALLOWED_SCRIPTS.source, 'g'), ' ')
    .replace(/\s+/g, ' ')
    .trim();

  if (!cleaned || (!BENGALI.test(cleaned) && !LATIN.test(cleaned) && !/\d/.test(cleaned))) {
    return null;
  }

  return cleaned;
}
