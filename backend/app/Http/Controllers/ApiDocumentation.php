<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Real-time Chat API",
 *     version="1.0.0",
 *     description="Real-time chat application API",
 *     @OA\Contact(email="contact@test.com")
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Servidor Local de Desenvolvimento"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class ApiDocumentation {}
