<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Endpoints d'authentification"
 * )
 */
class ExampleSwaggerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/example",
     *     operationId="getExample",
     *     tags={"Auth"},
     *     summary="Exemple d'endpoint",
     *     description="Cet endpoint montre la structure pour les annotations Swagger",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Réponse réussie",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function example(): JsonResponse
    {
        return response()->json(['message' => 'Ceci est un exemple']);
    }
}
