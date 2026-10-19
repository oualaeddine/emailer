<?php

namespace App\Modules\Recipients\Lists\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** docs/45 §45.3 `FolderInput` + `kind`. Authorization is done in the controller (depends on kind). */
class ListFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $folder = $this->route('id');
        $kind = $this->isMethod('POST')
            ? $this->input('kind', 'list')
            : \App\Modules\Recipients\Lists\Models\ListFolder::query()->where('uuid', $folder)->value('kind');

        return [
            'kind' => ['sometimes', 'in:list,segment'],
            'name' => [
                'required', 'string', 'min:1', 'max:100',
                Rule::unique('list_folders', 'name')->where('kind', $kind ?? 'list')
                    ->ignore($folder, 'uuid'),
            ],
        ];
    }
}
