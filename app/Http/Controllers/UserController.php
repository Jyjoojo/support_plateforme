<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Client;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    //
    public function index(Request $request): JsonResponse
    {
        $users = User::with(['technicien', 'client'])
            ->when($request->role, fn ($q) => $q->where('role', $request->role))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2->where('nom', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->paginate(20);

        return response()->json(UserResource::collection($users));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:administrateur,technicien,client',
            'telephone' => 'nullable|string|max:20',
            // Champs spécifiques rôle
            'specialite' => 'required_if:role,technicien|nullable|string',
            'entreprise' => 'nullable|string',
            'secteur' => 'nullable|string',
            'adresse' => 'nullable|string|max:255',
            'pays' => 'nullable|string|max:100',
            'est_client_officiel' => 'boolean',
        ]);

        $user = User::create([
            'id' => Str::uuid(),
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'telephone' => $data['telephone'] ?? null,
        ]);

        // Créer le profil métier associé
        match ($data['role']) {
            'technicien' => Technicien::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'specialite' => $data['specialite'] ?? null,
            ]),
            'client' => Client::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'entreprise' => $data['entreprise'] ?? null,
                'secteur' => $data['secteur'] ?? null,
                'adresse' => $data['adresse'] ?? null,
                'pays' => $data['pays'] ?? null,
                'est_client_officiel' => $data['est_client_officiel'] ?? false,
            ]),
            default => null,
        };

        return response()->json([
            'message' => 'Utilisateur créé.',
            'user' => new UserResource($user->fresh()->load(['technicien', 'client'])),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(new UserResource($user->load(['technicien', 'client'])));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'telephone' => 'nullable|string|max:20',
            'specialite' => 'sometimes|string',  // technicien
            'entreprise' => 'sometimes|string',  // client
            'secteur' => 'sometimes|string',  // client
            'adresse' => 'nullable|string|max:255', // client
            'pays' => 'nullable|string|max:100', // client
        ]);

        $user->update(array_intersect_key($data, array_flip(['nom', 'prenom', 'email', 'telephone'])));

        // Mettre à jour le profil métier
        if ($user->isTechnicien() && isset($data['specialite'])) {
            $user->technicien?->update(['specialite' => $data['specialite']]);
        }
        if ($user->isClient()) {
            $user->client?->update(array_intersect_key($data, array_flip(['entreprise', 'secteur', 'adresse', 'pays'])));
        }

        return response()->json(['message' => 'Utilisateur mis à jour.', 'user' => new UserResource($user->fresh())]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete(); // soft delete

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function toggleActif(User $user): JsonResponse
    {
        $user->update(['actif' => ! $user->actif]);
        $etat = $user->actif ? 'activé' : 'désactivé';

        return response()->json(['message' => "Compte {$etat}.", 'actif' => $user->actif]);
    }
}
