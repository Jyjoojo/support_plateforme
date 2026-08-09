<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfilRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$userId,
            'telephone' => 'nullable|string|max:20',
            'ancien_mot_de_passe' => 'required_with:nouveau_mot_de_passe|string|current_password:sanctum',
            'nouveau_mot_de_passe' => [
                'sometimes',
                'string',
                Password::min(8)->mixedCase()->numbers(),
            ],
            'confirmation_mot_de_passe' => 'required_with:nouveau_mot_de_passe|string|same:nouveau_mot_de_passe',
        ];
    }
}
