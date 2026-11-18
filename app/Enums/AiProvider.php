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
    case OpenAICompatible = 'openai_compatible';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::OpenAI => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
            self::DeepSeek => 'DeepSeek',
            self::OpenAICompatible => 'OpenAI-compatible',
        };
    }

    /**
     * Suggested model when the user doesn't pick one; they can type any model id the provider supports. An
     * OpenAI-compatible server can host anything, so there is no suggestion and the model is required.
     */
    public function defaultModel(): ?string
    {
        return match ($this) {
            self::Anthropic => 'claude-sonnet-5-5',
            self::OpenAI => 'gpt-6-astra',
            self::Gemini => 'gemini-2.5-pro',
            self::OpenRouter => 'anthropic/claude-sonnet-5.5',
            self::Groq => 'llama-3.3-70b-versatile',
            self::DeepSeek => 'deepseek-chat',
            self::OpenAICompatible => null,
        };
    }

    /**
     * Whether a PDF can be sent to this provider as a document (CV import). OpenRouter and OpenAI-compatible servers
     * pass it on to the chosen model, which must read PDFs (the server's error says so when it can't); Groq and
     * DeepSeek have no document input.
     */
    public function readsPdf(): bool
    {
        return match ($this) {
            self::Anthropic, self::OpenAI, self::Gemini, self::OpenRouter, self::OpenAICompatible => true,
            self::Groq, self::DeepSeek => false,
        };
    }

    /** Whether the user tells us where the server is (an OpenAI-compatible key); the others have a fixed address. */
    public function needsBaseUrl(): bool
    {
        return $this === self::OpenAICompatible;
    }

    /** Where to create a key; an OpenAI-compatible server could be anyone's, so there is no link. */
    public function keysUrl(): ?string
    {
        return match ($this) {
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::OpenAI => 'https://platform.openai.com/api-keys',
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::Groq => 'https://console.groq.com/keys',
            self::DeepSeek => 'https://platform.deepseek.com/api_keys',
            self::OpenAICompatible => null,
        };
    }

    /** Something the user should know before sending their CV to this provider, shown when adding a key. */
    public function notice(): ?string
    {
        if ($this === self::OpenAICompatible) {
            return __('For any service that speaks the OpenAI Chat Completions API, such as OrcaRouter, Together or Fireworks. Pick a model that supports JSON schema output; CV import also needs a model that reads PDFs.');
        }

        if ($this->readsPdf()) {
            return null;
        }

        if ($this === self::DeepSeek) {
            return __('DeepSeek processes requests on servers in China, and your CV holds personal data. It can’t read PDFs, so CV import needs another key.');
        }

        return __(':provider can’t read PDFs, so CV import needs another key.', ['provider' => $this->label()]);
    }

    /** @return list<array{value: string, label: string, defaultModel: string|null, keysUrl: string|null, needsBaseUrl: bool, notice: string|null}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'defaultModel' => $case->defaultModel(),
            'keysUrl' => $case->keysUrl(),
            'needsBaseUrl' => $case->needsBaseUrl(),
            'notice' => $case->notice(),
        ], self::cases());
    }
}
