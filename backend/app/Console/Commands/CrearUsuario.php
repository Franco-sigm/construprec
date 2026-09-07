<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Crea un usuario para poder entrar a la aplicación.
 *
 * La contraseña se pide de forma oculta y no se acepta como argumento a
 * propósito: un `--password=...` queda escrito en el historial del shell, donde
 * cualquiera que se siente en el equipo puede leerlo con flecha arriba.
 */
class CrearUsuario extends Command
{
    protected $signature = 'construprec:usuario {--email=} {--nombre=}';

    protected $description = 'Crea un usuario de la aplicación, pidiendo la contraseña de forma oculta';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Correo');
        $nombre = $this->option('nombre') ?: $this->ask('Nombre', 'Franco');

        $validador = Validator::make(
            ['email' => $email, 'name' => $nombre],
            [
                'email' => ['required', 'email', Rule::unique('users', 'email')],
                'name' => ['required', 'string', 'max:255'],
            ],
        );

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $clave = $this->secret('Contraseña (no se muestra al escribir)');

        if (strlen((string) $clave) < 8) {
            $this->error('La contraseña necesita al menos 8 caracteres.');

            return self::FAILURE;
        }

        if ($clave !== $this->secret('Repítela')) {
            $this->error('Las dos contraseñas no coinciden.');

            return self::FAILURE;
        }

        $usuario = User::create([
            'name' => $nombre,
            'email' => $email,
            'password' => Hash::make($clave),
        ]);

        $this->info("Usuario {$usuario->email} creado. Ya puedes entrar desde la aplicación.");

        return self::SUCCESS;
    }
}
