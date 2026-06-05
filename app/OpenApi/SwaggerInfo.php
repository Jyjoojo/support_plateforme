<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="Support Plateforme API",
 *     version="1.0.0",
 *     description="API de gestion des tickets de support"
 * )
 * @OA\Server(
 *     url="/api",
 *     description="Serveur local"
 * )
 * @OA\SecurityScheme(
 *     type="http",
 *     description="Login with username and password to get the authentication token",
 *     name="Token based authentication",
 *     in="header",
 *     scheme="bearer",
 *     securityScheme="bearerAuth"
 * )
 */
class SwaggerInfo
{
}
