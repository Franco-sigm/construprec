<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Entrada y salida con tokens de Sanctum.
 *
 * Tokens y no sesión con cookie porque el frontend vive en otro dominio: la
 * API va en api.construprec.surcode.cl y el sitio aparte, así que una cookie de
 * sesión obligaría a compartir dominio padre y a lidiar con CSRF entre orígenes.
 * Un token en la cabecera no tiene ese problema.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'dispositivo' => ['nullable', 'string', 'max:60'],
        ]);

        $usuario = User::where('email', $datos['email'])->first();

        // Un solo mensaje para usuario inexistente y contraseña equivocada. Si
        // fueran distintos, cualquiera podría averiguar qué correos están
        // registrados probando de a uno.
        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ningún usuario.',
            ]);
        }

        return response()->json([
            'token' => $usuario->createToken($datos['dispositivo'] ?? 'construprec')->plainTextToken,
            'usuario' => ['id' => $usuario->id, 'nombre' => $usuario->name, 'email' => $usuario->email],
        ]);
    }

    /** Cierra sólo la sesión de este dispositivo, no las de los demás. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }

    public function yo(Request $request): JsonResponse
    {
        $usuario = $request->user();

        return response()->json([
            'id' => $usuario->id,
            'nombre' => $usuario->name,
            'email' => $usuario->email,
        ]);
    }
}
