import type { LanguageValue } from './i18n';

export type Option = { value: string; label: string };

export type StatusOption = Option & { color: string };

export type ApplicationQuestion = { id: string; question: string };

export type Application = {
    id: string;
    company_name: string;
    role_title: string;
    job_url: string | null;
    location: string | null;
    work_mode: Option | null;
    language: { value: LanguageValue; label: string };
    company_notes: string | null;
    job_description: string;
    wants_cover_letter: boolean;
    wants_recruiter_message: boolean;
    status: StatusOption;
    applied_at: string | null;
    created_at: string;
    updated_at: string;
    questions: ApplicationQuestion[];
};

export type GenerationReport = {
    jd_analysis: {
        must_haves: string[];
        nice_to_haves: string[];
        seniority: string;
        domain: string;
        keywords: string[];
        knockouts: string[];
    };
    matches: {
        requirement: string;
        evidence: string;
        strength: 'strong' | 'partial' | 'missing';
    }[];
    keyword_coverage: {
        matched: number;
        total: number;
        missing: string[];
        percentage: number;
    };
    red_flags: string[];
    gaps: string[];
    todos: string[];
    interview_questions: string[];
};

export type GeneratedDocument = {
    id: string;
    type: 'cv' | 'cover_letter' | 'recruiter_message';
    label: string;
    has_pdf: boolean;
    content: string;
    /** The CV rendered as the PDF renders it; null for other documents. */
    html: string | null;
    edited: boolean;
    updated_at: string;
    todos: string[];
    warnings: string[];
};

export type GeneratedAnswer = {
    id: string;
    question: string;
    content: string;
    edited: boolean;
    updated_at: string;
    todos: string[];
};

export type GenerationStatus = {
    value: 'queued' | 'running' | 'succeeded' | 'failed';
    label: string;
    finished: boolean;
};

/** One kept, successful version in the switcher. */
export type GenerationVersion = {
    version: number;
    language: { value: LanguageValue; label: string };
    finished_at: string | null;
    current: boolean;
};

/** The run in progress (queued or running); it gets a version number only if it succeeds. */
export type GenerationRun = {
    status: { value: 'queued' | 'running'; label: string };
    provider: string;
    model: string;
    queued_at: string;
    started_at: string | null;
};

/** One entry of the application's failure log (the last 3 failed runs). */
export type GenerationFailure = {
    id: string;
    provider: string;
    model: string;
    language: { value: LanguageValue; label: string };
    error: string | null;
    /** The cause is fixed on the AI keys page (rejected key, no credit, unknown model): retrying would fail the same way. */
    fix_in_ai_keys: boolean;
    queued_at: string;
    started_at: string | null;
    finished_at: string | null;
};

/** One of the user's AI keys, for the "Using" switcher next to Generate. */
export type AiKeyOption = {
    id: string;
    name: string;
    provider: string;
    model: string;
    active: boolean;
};

export type Generation = {
    id: string;
    version: number;
    status: GenerationStatus;
    language: { value: LanguageValue; label: string };
    provider: string;
    model: string;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    report: GenerationReport | null;
    documents: GeneratedDocument[];
    answers: GeneratedAnswer[];
};

export type ApplicationFormOptions = {
    workModes: Option[];
    limits: { jobDescription: number; questions: number };
};
