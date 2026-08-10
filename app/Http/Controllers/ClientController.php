<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClientSelectionResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    /**
     * Liste des clients actifs disponibles lors de la création d'un ticket.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $search = trim($data['search'] ?? '');

        $clients = Client::query()
            ->select('clients.*')
            ->join('users', 'users.id', '=', 'clients.user_id')
            ->with('user')
            ->where('users.actif', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('users.nom', 'like', "%{$search}%")
                        ->orWhere('users.prenom', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('clients.entreprise', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return ClientSelectionResource::collection($clients);
    }
}
