<?php

namespace App\Http\Requests\Admin;

use App\Models\KycDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadKycDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $type  = $this->input('document_type');
        $sides = $type ? KycDocument::requiredSides($type) : ['front'];

        $fileRules  = ['nullable', 'file',  'mimes:jpg,jpeg,png,pdf', 'max:5120'];
        $imageRules = ['nullable', 'image', 'mimes:jpg,jpeg,png',     'max:5120'];

        return [
            'document_type'   => ['required', 'in:national_id,passport,driving_license,state_id'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'expiry_date'     => ['nullable', 'date', 'after:today'],

            'file_front'  => [in_array('front',  $sides) ? 'required' : 'nullable', ...$fileRules],
            'file_back'   => [in_array('back',   $sides) ? 'required' : 'nullable', ...$fileRules],
            'file_selfie' => [in_array('selfie', $sides) ? 'required' : 'nullable', ...$imageRules],
        ];
    }

    public function messages(): array
    {
        return [
            'file_front.required'  => 'The front image of the document is required.',
            'file_back.required'   => 'The back image of the document is required for this document type.',
            'file_selfie.required' => 'A customer photo is required for all document types.',
            'file_front.max'       => 'Each file must not exceed 5 MB.',
            'file_back.max'        => 'Each file must not exceed 5 MB.',
            'file_selfie.max'      => 'Each file must not exceed 5 MB.',
            'file_front.mimes'     => 'Only JPG, PNG, or PDF files are accepted.',
            'file_back.mimes'      => 'Only JPG, PNG, or PDF files are accepted.',
            'file_selfie.mimes'    => 'Only JPG, PNG, or PDF files are accepted.',
        ];
    }
}
