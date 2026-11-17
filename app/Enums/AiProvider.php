<?php

namespace App\Enums;

enum AiProvider: string
{
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case OpenRouter = 'openrouter';
    case Groq = 'groq';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::Gemini => 'Google Gemini',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
        };
    }

    /** Suggested model when the user doesn't pick one; they can type any model id the provider supports. */
    public function defaultModel(): string
    {
        return match ($this) {
            self::Anthropic => 'claude-sonnet-5-5',
            self::Gemini => 'gemini-2.5-pro',
            self::OpenRouter => 'anthropic/claude-sonnet-5.5',
            self::Groq => 'llama-3.3-70b-versatile',
        };
    }

    public function keysUrl(): string
    {
        return match ($this) {
            self::Anthropic => 'https://console.anthropic.com/settings/keys',
            self::Gemini => 'https://aistudio.google.com/apikey',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::Groq => 'https://console.groq.com/keys',
        };
    }

    /** @return list<array{value: string, label: string, defaultModel: string, keysUrl: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'defaultModel' => $case->defaultModel(),
            'keysUrl' => $case->keysUrl(),
        ], self::cases());
    }
}
