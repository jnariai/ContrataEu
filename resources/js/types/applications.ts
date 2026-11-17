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

/** One kept version in the switcher. */
export type GenerationVersion = {
    version: number;
    status: GenerationStatus;
    language: { value: LanguageValue; label: string };
    error: string | null;
    finished_at: string | null;
    current: boolean;
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
