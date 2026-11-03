<?php

namespace App\Filament\Resources\Usuarios\Pages;

use App\Filament\Resources\Usuarios\UsuarioResource;
use Filament\Resources\Pages\EditRecord;

class EditUsuario extends EditRecord
{
    protected static string $resource = UsuarioResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['email'] = strtolower($data['email']);

        // Nadie se quita a sí mismo el rol de admin ni se desactiva (evita quedarse sin administradores)
        if ($this->record->is(auth()->user())) {
            unset($data['rol'], $data['activo']);
        }

        return $data;
    }
}
