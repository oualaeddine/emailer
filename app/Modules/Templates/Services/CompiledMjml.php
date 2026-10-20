<?php

namespace App\Modules\Templates\Services;

/**
 * Result of {@see MjmlCompiler::compile()} (docs/45 §45.6.1 `MjmlValidationResult`).
 */
final class CompiledMjml
{
    /**
     * @param  list<array{line: int|null, message: string, tag_name: string|null}>  $errors
     * @param  bool  $compilerAvailable  false when Node / mjml could not run (the HTML, if any, then comes from the client fallback)
     */
    public function __construct(
        public readonly string $html,
        public readonly array $errors,
        public readonly bool $compilerAvailable = true,
    ) {}

    public static function unavailable(): self
    {
        return new self('', [[
            'line' => null,
            'message' => MjmlCompiler::UNAVAILABLE,
            'tag_name' => null,
        ]], false);
    }

    public function isValid(): bool
    {
        return $this->errors === [] && $this->html !== '';
    }

    public function sizeBytes(): int
    {
        return strlen($this->html);
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(
            fn (array $error): string => $error['line'] !== null
                ? sprintf('Ligne %d : %s', $error['line'], $error['message'])
                : $error['message'],
            $this->errors,
        );
    }
}
