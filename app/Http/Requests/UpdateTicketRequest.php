<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
        $user = $this->user();

        // Client ne peut modifier que titre/description (si ticket encore nouveau)
        if ($user->isClient()) {
            return [
                'titre'       => 'sometimes|string|min:5|max:255',
                'description' => 'sometimes|string|min:10',
            ];
        }

        // Technicien peut changer statut + priorité
        if ($user->isTechnicien()) {
            return [
                'statut'   => 'prohibited',
                'priorite' => 'sometimes|in:' . implode(',', Ticket::PRIORITES),
            ];
        }

        // Admin : tout modifier
        return [
            //
            'titre'        => 'sometimes|string|min:5|max:255',
            'description'  => 'sometimes|string|min:10',
            'statut'       => 'sometimes|in:' . implode(',', Ticket::STATUTS),
            'priorite'     => 'sometimes|in:' . implode(',', Ticket::PRIORITES),
            'categorie_id' => 'nullable|uuid|exists:categories,id',
        ];
    }
}
