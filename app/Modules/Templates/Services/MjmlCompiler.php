<?php

namespace App\Modules\Templates\Services;

use Spatie\Mjml\Exceptions\CouldNotConvertMjml;
use Spatie\Mjml\Mjml;
use Spatie\Mjml\MjmlError;
use Spatie\Mjml\MjmlResult;
use Throwable;

/**
 * Server-side MJML compiler (docs/46-editor-spike.md §4 option A, docs/45
 * §45.6.1): wraps `spatie/mjml-php`, which runs the npm `mjml@4.18.0` package
 * (same parser version as the grapesjs-mjml editor) in a Node process.
 *
 * - Every MJML warning counts as a validation error: soft validation keeps
 *   the line / tag of each problem, and `isValid()` is false when any exists
 *   (same verdict as `validationLevel: strict`, with structured errors).
 * - Comments are kept (dynamic-content markers, docs/46 §3).
 * - The compiled HTML is passed through {@see HtmlSanitizer} (mj-raw / HTML
 *   blocks may carry scripts or event handlers).
 * - When Node or the `mjml` package is missing the result carries the single
 *   error {@see self::UNAVAILABLE} instead of throwing.
 */
class MjmlCompiler
{
    public const UNAVAILABLE = 'Compilateur MJML indisponible';

    public const PARSE_FAILED = 'Le code MJML est invalide et ne peut pas être compilé.';

    public const INCLUDE_FORBIDDEN = 'La balise <mj-include> n’est pas autorisée.';

    public static function containsInclude(string $mjml): bool
    {
        return preg_match('/<\s*mj-include\b/i', $mjml) === 1;
    }

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function compile(string $mjml): CompiledMjml
    {
        // mj-include would let the Node process read arbitrary server files.
        if (self::containsInclude($mjml)) {
            return new CompiledMjml('', [[
                'line' => null,
                'message' => self::INCLUDE_FORBIDDEN,
                'tag_name' => 'mj-include',
            ]]);
        }

        try {
            $result = $this->convert($mjml);
        } catch (CouldNotConvertMjml $e) {
            return new CompiledMjml('', [[
                'line' => null,
                'message' => self::PARSE_FAILED,
                'tag_name' => null,
            ]]);
        } catch (Throwable $e) {
            report($e);

            return CompiledMjml::unavailable();
        }

        $errors = array_values(array_map(
            fn (MjmlError $error): array => [
                'line' => $error->line() > 0 ? $error->line() : null,
                'message' => $error->message(),
                'tag_name' => $error->tagName() !== '' ? $error->tagName() : null,
            ],
            $result->errors(),
        ));

        return new CompiledMjml($this->sanitize($result->html()), $errors);
    }

    /**
     * Sanitises HTML that did not come from this compiler (client fallback).
     */
    public function sanitize(string $html): string
    {
        return $html === '' ? '' : $this->sanitizer->clean($html)['html'];
    }

    /**
     * Runs the Node compiler. Overridable in tests.
     *
     * @throws CouldNotConvertMjml when MJML cannot be parsed
     * @throws Throwable when Node / the mjml package is unavailable
     */
    protected function convert(string $mjml): MjmlResult
    {
        $nodePath = config('editor.node_path');
        if (is_string($nodePath) && $nodePath !== '') {
            putenv('MJML_NODE_PATH='.$nodePath);
        }

        return Mjml::new()
            ->keepComments()
            ->ignoreIncludes()
            ->convert($mjml);
    }
}
