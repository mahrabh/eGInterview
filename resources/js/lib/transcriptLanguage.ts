/** Bengali script */
const BENGALI = /[\u0980-\u09FF]/;

/** Latin / English */
const LATIN = /[A-Za-z]/;

/**
 * Hindi (Devanagari), Japanese, CJK, Korean, Arabic — not allowed in loan interviews.
 * These often appear when Live STT mis-detects Bengali speech.
 */
const DISALLOWED_SCRIPTS = /[\u0900-\u097F\u3040-\u30FF\u4E00-\u9FFF\uAC00-\uD7AF\u0600-\u06FF]/;

/** Common romanized Bangla tokens from dual STT (Banglish). */
const BANGLISH_HINTS = /(?:\bami\b|\bamar\b|\bapnar\b|\bhocche\b|\bhoche\b|\bbyabsha\b|\bbyabshar\b|\bbyabosa\b|\bnam\b|\bmashe\b|\bmash\b|\btaka\b|\btakar\b|\bhoy\b|\bmoto\b|\bkorchi\b|\bkori\b|\bchai\b)/i;

export function containsDisallowedScript(text: string): boolean {
  return DISALLOWED_SCRIPTS.test(text);
}

export function hasBengaliScript(text: string): boolean {
  return BENGALI.test(text);
}

export function hasLatinScript(text: string): boolean {
  return LATIN.test(text);
}

/**
 * Latin-heavy romanized Bangla (Banglish) without real Bengali script.
 * Dual STT often emits these alongside a correct Bengali line.
 */
export function isBanglishOnly(text: string): boolean {
  const trimmed = text.trim();
  if (!trimmed || hasBengaliScript(trimmed)) {
    return false;
  }

  // Real English interview lines are not Banglish.
  if (/^Hello\b/i.test(trimmed) || /\b(?:professional|application|securely|ready to begin|please|thank you)\b/i.test(trimmed)) {
    return false;
  }

  const letters = trimmed.replace(/[^A-Za-z\u0980-\u09FF]/g, '');
  if (letters.length < 8) {
    return false;
  }

  const latinCount = (letters.match(/[A-Za-z]/g) ?? []).length;
  if (latinCount / letters.length < 0.85) {
    return false;
  }

  return BANGLISH_HINTS.test(trimmed);
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

function normalizeDigits(text: string): string {
  return text
    .replace(/[০-৯]/g, (digit) => String('০১২৩৪৫৬৭৮৯'.indexOf(digit)))
    .replace(/[०-९]/g, (digit) => String('०१२३४५६७८९'.indexOf(digit)))
    .replace(/,/g, '');
}

/**
 * Stable amount fingerprint so "দেড় লক্ষ" / "1.5 lakh" / "1,50,000" can dedupe across scripts.
 */
export function amountFingerprint(text: string): string | null {
  const normalized = normalizeDigits(text.toLowerCase());
  const values = new Set<number>();

  if (/(?:দেড়|ded|der)\s*(?:লক্ষ|লাক|lakh|lac)/u.test(normalized) || /1\.5\s*(?:lakh|lac|লক্ষ)/u.test(normalized)) {
    values.add(150000);
  }
  if (/(?:আড়াই|arai)\s*(?:লক্ষ|লাক|lakh|lac)/u.test(normalized)) {
    values.add(250000);
  }

  const lakhMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(?:লক্ষ|লাক|lakh|lac)/u);
  if (lakhMatch) {
    values.add(Math.round(parseFloat(lakhMatch[1]) * 100000));
  }

  const croreMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(?:কোটি|crore|koti)/u);
  if (croreMatch) {
    values.add(Math.round(parseFloat(croreMatch[1]) * 10000000));
  }

  for (const match of normalized.matchAll(/\d+(?:\.\d+)?/g)) {
    const n = Number(match[0]);
    if (Number.isFinite(n) && n >= 1000) {
      values.add(Math.round(n));
    }
  }

  if (values.size === 0) {
    return null;
  }

  return [...values].sort((a, b) => a - b).join('|');
}

function stripPunctuation(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function isNearDuplicateUtterance(a: string, b: string): boolean {
  const left = stripPunctuation(a);
  const right = stripPunctuation(b);

  if (!left || !right) {
    return false;
  }

  if (left === right) {
    return true;
  }

  if (left.length >= 6 && right.length >= 6 && (left.includes(right) || right.includes(left))) {
    return true;
  }

  const leftAmount = amountFingerprint(a);
  const rightAmount = amountFingerprint(b);
  if (leftAmount && rightAmount && leftAmount === rightAmount) {
    return true;
  }

  // Bengali line + Banglish remake of the same answer.
  if (
    (hasBengaliScript(a) && isBanglishOnly(b))
    || (hasBengaliScript(b) && isBanglishOnly(a))
  ) {
    if (leftAmount && rightAmount && leftAmount === rightAmount) {
      return true;
    }

    // Name-style answers without shared digits: both mention name/business keywords.
    const nameHints = /(?:নাম|name|ব্যবসা|byabsha|byabosa|trade|ট্রেড|plc|পিএলসি|প্রতিষ্ঠান)/iu;
    if (nameHints.test(a) && nameHints.test(b)) {
      return true;
    }
  }

  return false;
}

/** Prefer native Bengali (or clearer English) over Banglish remakes. */
export function preferTranscriptText(existing: string, incoming: string): string {
  const existingBanglish = isBanglishOnly(existing);
  const incomingBanglish = isBanglishOnly(incoming);
  const existingBengali = hasBengaliScript(existing);
  const incomingBengali = hasBengaliScript(incoming);

  if (existingBengali && incomingBanglish) {
    return existing;
  }

  if (incomingBengali && existingBanglish) {
    return incoming;
  }

  return incoming.length >= existing.length ? incoming : existing;
}

/**
 * Drop assistant turns that only echo the applicant, and strip a trailing
 * applicant amount/name that got glued onto the AI question transcription.
 */
export function cleanAssistantTranscriptText(text: string, lastApplicantText?: string | null): string | null {
  let cleaned = text.trim();
  if (!cleaned) {
    return null;
  }

  // Never drop short acknowledgements or the fixed greeting opener.
  if (
    /^(?:ধন্যবাদ|ঠিক আছে|আচ্ছা|বুঝতে পেরেছি|হ্যাঁ|okay|ok|thanks|thank you)\.?$/iu.test(cleaned)
    || /^Hello\b/i.test(cleaned)
  ) {
    return cleaned;
  }

  const applicant = (lastApplicantText ?? '').trim();
  if (!applicant) {
    return cleaned;
  }

  const applicantCore = stripPunctuation(applicant);
  const assistantCore = stripPunctuation(cleaned);

  // Pure echo of the applicant answer (e.g. "মেহেরাব ইউনি ট্রেড।").
  if (
    assistantCore.length > 0
    && (
      assistantCore === applicantCore
      || (applicantCore.length >= 6 && assistantCore.includes(applicantCore))
      || (assistantCore.length >= 6 && applicantCore.includes(assistantCore))
    )
    && !/[?？]|বলুন|জানান|কত|কি |কী /u.test(cleaned)
  ) {
    return null;
  }

  const applicantAmount = amountFingerprint(applicant);
  if (applicantAmount) {
    // Strip trailing money phrases accidentally merged into the AI question.
    cleaned = cleaned
      .replace(
        /[।.!]?\s*(?:(?:\d|[০-৯])+[\d,০-৯]*(?:\.\d+)?|(?:\d+(?:\.\d+)?|(?:এক|দুই|তিন|দেড়|আড়াই|১|২|৩))\s*(?:লক্ষ|লাখ)(?:\s*(?:\d+|[০-৯]+)\s*হাজার)?|(?:\d+|[০-৯]+)\s*হাজার)\s*টাকা\.?[।.]?$/iu,
        ''
      )
      .replace(/[।.!]?\s*১\s*লক্ষ\s*৫০\s*হাজার\s*টাকা\.?[।.]?$/iu, '')
      .replace(/\s*[।.]\s*$/u, '।')
      .trim();
  }

  // Strip a trailing exact copy of the applicant phrase.
  if (applicantCore.length >= 8) {
    const escaped = applicant.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    cleaned = cleaned.replace(new RegExp(`[।.!\\s]*${escaped}[।.!\\s]*$`, 'iu'), '').trim();
  }

  cleaned = cleaned.replace(/\s{2,}/g, ' ').trim();

  if (!cleaned || (isNearDuplicateUtterance(cleaned, applicant) && !/[?？]|বলুন|জানান/u.test(cleaned))) {
    return null;
  }

  return cleaned;
}
