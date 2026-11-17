<?php

namespace App\Enums;

enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAI = 'openai';
    case Gemini = 'gemini';
    case OpenRouter = 'openrouter';
    case Groq = 'groq';
    case DeepSeek = 'deepseek';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::OpenAI => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
            self::DeepSeek => 'DeepSeek',
        };
    }

    /** Suggested model when the user doesn't pick one; they can type any model id the provider supports. */
    public function defaultModel(): string
    {
        return match ($this) {
            self::Anthropic => 'claude-sonnet-5-5',
            self::OpenAI => 'gpt-6-astra',
            self::Gemini => 'gemini-2.5-pro',
            self::OpenRouter => 'anthropic/claude-sonnet-5.5',
            self::Groq => 'llama-3.3-70b-versatile',
            self::DeepSeek => 'deepseek-chat',
        };
    }

    /**
     * Whether a PDF can be sent to this provider as a document (CV import). OpenRouter passes it on to the chosen
     * model, which must read PDFs; Groq and DeepSeek have no document input.
     */
    public function readsPdf(): bool
    {
        return match ($this) {
            self::Anthropic, self::OpenAI, self::Gemini, self::OpenRouter => true,
            self::Groq, self::DeepSeek => false,
        };
    }

    public function keysUrl(): string
    {
        return match ($this) {
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::OpenAI => 'https://platform.openai.com/api-keys',
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::Groq => 'https://console.groq.com/keys',
            self::DeepSeek => 'https://platform.deepseek.com/api_keys',
        };
    }

    /** Something the user should know before sending their CV to this provider, shown when adding a key. */
    public function notice(): ?string
    {
        if ($this->readsPdf()) {
            return null;
        }

        return $this === self::DeepSeek
            ? __('DeepSeek processes requests on servers in China, and your CV holds personal data. It can’t read PDFs, so CV import needs another key.')
            : __(':provider can’t read PDFs, so CV import needs another key.', ['provider' => $this->label()]);
    }

    /** @return list<array{value: string, label: string, defaultModel: string, keysUrl: string, notice: string|null}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'defaultModel' => $case->defaultModel(),
            'keysUrl' => $case->keysUrl(),
            'notice' => $case->notice(),
        ], self::cases());
    }
}
