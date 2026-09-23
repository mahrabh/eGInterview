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
const BANGLISH_HINTS = /(?:\bami\b|\bamar\b|\bapnar\b|\baapni\b|\bhocche\b|\bhoche\b|\bbyabsha\b|\bbyabshar\b|\bbyabosa\b|\bnam\b|\bmashe\b|\bmash\b|\bmasher\b|\btaka\b|\btakar\b|\bhoy\b|\bmoto\b|\bkorchi\b|\bkori\b|\bkorte\b|\bchai\b|\bbarir\b|\bbari\b|\bjonno\b|\bkoto\b|\bmodhye\b|\bpartan\b|\bamake\b|\bkhetre\b|\bjodi\b|\brupaye\b|\bcalculate\b|\bekchuwali\b|\bactually\b)/i;

/** Loose romanized Bangla syllables often seen in bad STT (e.g. "Barir jonno", "shikay"). */
const BANGLISH_LOOSE = /(?:barir|bari|jonno|masher|modhye|korto|korte|partan|amake|aapni|shikay|rupaye|byabsha|hocche|nam\s+hocche)/i;

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
  if (
    /^Hello\b/i.test(trimmed)
    || /\b(?:professional|application|securely|ready to begin|please|thank you|months?|income|business|employer|down payment|personal|home loan|car loan)\b/i.test(trimmed)
  ) {
    return false;
  }

  const letters = trimmed.replace(/[^A-Za-z\u0980-\u09FF]/g, '');
  if (letters.length < 6) {
    // Very short latin fillers like "Hey." alone are junk when not clear English answers.
    if (/^(?:hey|hmm+|mhm+|uh+|um+|ah+)$/i.test(trimmed.replace(/[^\w]/g, ''))) {
      return true;
    }
    return false;
  }

  const latinCount = (letters.match(/[A-Za-z]/g) ?? []).length;
  if (latinCount / letters.length < 0.85) {
    return false;
  }

  if (BANGLISH_HINTS.test(trimmed) || BANGLISH_LOOSE.test(trimmed)) {
    return true;
  }

  // Short latin lines with no clear English loan vocabulary → treat as Banglish junk.
  if (
    trimmed.length <= 48
    && !/\b(?:yes|no|ready|loan|month|lakh|lac|crore|income|business|salary|none)\b/i.test(trimmed)
    && /[aeiou].*[aeiou]/i.test(trimmed)
  ) {
    return true;
  }

  return false;
}

/**
 * Keep Bengali/English participant text. Drop turns that are mostly wrong-script
 * mis-transcriptions (e.g. Hindi Devanagari from Bengali speech).
 * Banglish is kept so applicant answers are not lost when Bengali STT fails;
 * orchestrator still prefers a Bengali twin when both arrive.
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

    // Purpose / home / bari remakes (e.g. "Hey. Barir jonno" vs "নতুন বাড়ি বানাবো").
    const purposeHints = /(?:বাড়ি|বাড়ি|barir|bari|jonno|purpose|বানাব|কিনব|home|house)/iu;
    if (purposeHints.test(a) && purposeHints.test(b)) {
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
 * Collapse accidental duplicated sentences inside one AI turn
 * (e.g. the same employer question spoken twice back-to-back).
 */
export function dedupeRepeatedAssistantSentences(text: string): string {
  const trimmed = text.trim();
  if (!trimmed) {
    return trimmed;
  }

  const parts = trimmed
    .split(/(?<=[?？।.!])\s*/)
    .map((part) => part.trim())
    .filter(Boolean);

  if (parts.length < 2) {
    return trimmed;
  }

  const kept: string[] = [];
  for (const part of parts) {
    const prev = kept[kept.length - 1];
    if (prev && isNearDuplicateUtterance(prev, part)) {
      if (part.length > prev.length) {
        kept[kept.length - 1] = part;
      }
      continue;
    }
    kept.push(part);
  }

  return kept.join(' ').replace(/\s{2,}/g, ' ').trim();
}

/**
 * Drop assistant turns that only echo the applicant, and strip a trailing
 * applicant amount/name that got glued onto the AI question transcription.
 */
export function cleanAssistantTranscriptText(text: string, lastApplicantText?: string | null): string | null {
  let cleaned = dedupeRepeatedAssistantSentences(text.trim());
  if (!cleaned) {
    return null;
  }

  // Never drop short acknowledgements or the fixed greeting opener.
  if (
    /^(?:ধন্যবাদ|ঠিক আছে|আচ্ছা|বুঝতে পেরেছি|হ্যাঁ|okay|ok|thanks|thank you|great|i see|got it)\.?$/iu.test(cleaned)
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

  cleaned = dedupeRepeatedAssistantSentences(cleaned.replace(/\s{2,}/g, ' ').trim());

  if (!cleaned || (isNearDuplicateUtterance(cleaned, applicant) && !/[?？]|বলুন|জানান/u.test(cleaned))) {
    return null;
  }

  return cleaned;
}
