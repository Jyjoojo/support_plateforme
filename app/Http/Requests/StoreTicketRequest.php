<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
        $rules = [
            'titre' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10',
            'priorite' => 'sometimes|in:'.implode(',', Ticket::PRIORITES),
            'categorie_id' => 'nullable|uuid|exists:categories,id',
            'fichiers' => 'sometimes|array|max:5',
            'fichiers.*' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
        ];

        // Si technicien ou admin crée un ticket, le client_id est obligatoire
        if ($this->user()->isTechnicien() || $this->user()->isAdmin()) {
            $rules['client_id'] = 'required|uuid|exists:clients,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre du ticket est obligatoire.',
            'titre.min' => 'Le titre doit contenir au moins 5 caractères.',
            'description.required' => 'La description est obligatoire.',
            'description.min' => 'La description doit contenir au moins 10 caractères.',
            'client_id.required' => 'Le client concerné doit être sélectionné.',
            'client_id.exists' => 'Le client sélectionné n\'existe pas.',
        ];
    }
}
