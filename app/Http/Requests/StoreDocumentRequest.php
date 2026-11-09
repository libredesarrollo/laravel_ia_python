<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation rules for knowledge-base document uploads.
 */
final class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The panel is intentionally open while the product core is being
        // developed; an authentication middleware gates this route later.
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'document' => ['required', 'file', 'max:'.config('rag.uploads.max_size_kb')],
        ];

        $allowedMimes = config('rag.uploads.allowed_mimes');

        if ($allowedMimes !== null && $allowedMimes !== []) {
            $rules['document'][] = 'mimes:'.implode(',', $allowedMimes);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Debes seleccionar un documento para subir.',
            'document.file' => 'El archivo subido no es válido.',
            'document.mimes' => 'Formato no soportado. Soportados: :values.',
            'document.max' => 'El archivo excede el tamaño máximo permitido.',
        ];
    }
}
