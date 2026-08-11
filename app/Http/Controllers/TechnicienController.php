<?php

namespace App\Http\Controllers;

use App\Http\Resources\TechnicienSelectionResource;
use App\Models\Technicien;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TechnicienController extends Controller
{
    /**
     * Liste des techniciens actifs disponibles pour un transfert de ticket.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $search = trim($data['search'] ?? '');

        $techniciens = Technicien::query()
            ->select('techniciens.*')
            ->join('users', 'users.id', '=', 'techniciens.user_id')
            ->with('user')
            ->where('users.actif', true)
            ->when($request->user()->isTechnicien(), function ($query) use ($request) {
                $query->where('techniciens.id', '!=', $request->user()->technicien->id);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('users.nom', 'like', "%{$search}%")
                        ->orWhere('users.prenom', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('techniciens.specialite', 'like', "%{$search}%");
                });
            })
            ->orderBy('users.nom')
            ->orderBy('users.prenom')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return TechnicienSelectionResource::collection($techniciens);
    }
}
