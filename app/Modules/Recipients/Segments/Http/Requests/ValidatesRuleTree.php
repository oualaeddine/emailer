<?php

namespace App\Modules\Recipients\Segments\Http\Requests;

use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * docs/45 §45.5.2 — rule-tree validation with dot paths into the tree
 * (`rules.children.1.children.0.value`), run after the basic rules pass.
 */
trait ValidatesRuleTree
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('rules') || ! is_array($this->input('rules'))) {
                return;
            }

            try {
                app(RuleTreeCompiler::class)->validate($this->input('rules'), 'rules');
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
