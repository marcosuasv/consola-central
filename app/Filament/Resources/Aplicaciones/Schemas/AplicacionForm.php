<?php

namespace App\Filament\Resources\Aplicaciones\Schemas;

use App\Models\Aplicacion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AplicacionForm
{
    public static function configure(Schema $schema): Schema
    {
        $esOctane = fn (Get $get): bool => $get('tipo') === Aplicacion::TIPO_OCTANE;
        $esFpm = fn (Get $get): bool => $get('tipo') === Aplicacion::TIPO_FPM;

        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('carpeta')
                            ->label('Carpeta')
                            ->helperText('Nombre de la carpeta en '.Aplicacion::RUTA_BASE.'. Solo letras, números, "-" y "_".')
                            ->required()
                            ->maxLength(100)
                            ->regex(Aplicacion::REGEX_CARPETA)
                            ->unique(ignoreRecord: true),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(Aplicacion::TIPOS)
                            ->default(Aplicacion::TIPO_OCTANE)
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                Section::make('Servicios')
                    ->description('Unidades de systemd (Octane) o pool de PHP-FPM.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('servicio')
                            ->label('Servicio principal')
                            ->placeholder('app-ejemplo')
                            ->helperText('Debe empezar con "app-". Minúsculas, números y "-".')
                            ->maxLength(100)
                            ->regex(Aplicacion::REGEX_SERVICIO)
                            ->required($esOctane)
                            ->visible($esOctane),
                        TextInput::make('servicio_cola')
                            ->label('Servicio de cola')
                            ->placeholder('app-ejemplo-queue')
                            ->maxLength(100)
                            ->regex(Aplicacion::REGEX_SERVICIO)
                            ->visible($esOctane),
                        TextInput::make('timer_scheduler')
                            ->label('Timer del scheduler')
                            ->placeholder('app-ejemplo-scheduler.timer')
                            ->helperText('Debe terminar en ".timer".')
                            ->maxLength(100)
                            ->regex(Aplicacion::REGEX_TIMER)
                            ->visible($esOctane),
                        TextInput::make('pool_fpm')
                            ->label('Pool de PHP-FPM')
                            ->placeholder('intranet')
                            ->maxLength(50)
                            ->regex(Aplicacion::REGEX_POOL)
                            ->required($esFpm)
                            ->visible($esFpm),
                    ]),

                Section::make('Red')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('puerto_interno')
                            ->label('Puerto interno')
                            ->helperText('Puerto local donde escucha Octane.')
                            ->integer()
                            ->minValue(1024)
                            ->maxValue(65535)
                            ->required($esOctane)
                            ->visible($esOctane),
                        TextInput::make('puertos_publicos')
                            ->label('Puertos públicos')
                            ->placeholder('443,8070')
                            ->helperText('Separados por comas, sin espacios.')
                            ->maxLength(100)
                            ->regex(Aplicacion::REGEX_PUERTOS),
                        TextInput::make('url')
                            ->label('URL de acceso')
                            ->placeholder('https://10.2.0.170:8070/')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('dominios')
                            ->label('Dominios')
                            ->placeholder('task.gbpo.com.mx')
                            ->helperText('Separados por comas. Para configurarlos usa Configuración → Agregar dominio.')
                            ->maxLength(500)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? strtolower(preg_replace('/\s+/', '', $state)) : null)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                foreach (array_filter(explode(',', strtolower(preg_replace('/\s+/', '', (string) $value)))) as $dominio) {
                                    if (! preg_match(Aplicacion::REGEX_DOMINIO, $dominio)) {
                                        $fail("Dominio inválido: {$dominio}");
                                    }
                                }
                            }),
                        TextInput::make('url_salud')
                            ->label('URL de salud')
                            ->placeholder('http://127.0.0.1:18070/')
                            ->helperText('Solo direcciones locales (127.0.0.1 o localhost). Usa el puerto interno, no el 443.')
                            ->maxLength(255)
                            ->regex(Aplicacion::REGEX_URL_SALUD),
                    ]),

                Section::make('Base de datos y logs')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('base_datos')
                            ->label('Base de datos')
                            ->maxLength(64)
                            ->regex(Aplicacion::REGEX_BD),
                        TextInput::make('usuario_bd')
                            ->label('Usuario de BD')
                            ->helperText('Solo referencia; la contraseña nunca se guarda aquí.')
                            ->maxLength(80)
                            ->regex(Aplicacion::REGEX_BD),
                        Toggle::make('sesiones_en_bd')
                            ->label('Sesiones en base de datos')
                            ->helperText('Desactivar si la app guarda sesiones en archivos.')
                            ->default(true),
                        TextInput::make('log_nginx')
                            ->label('Log de acceso de Nginx')
                            ->placeholder('/var/log/nginx/app-ejemplo.access.log')
                            ->maxLength(255)
                            ->regex(Aplicacion::REGEX_LOG_NGINX),
                    ]),

                Section::make('Respaldos')
                    ->description('Respaldo diario de la base de datos (Paso 14).')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('respaldo_activo')
                            ->label('Respaldar cada noche')
                            ->default(true)
                            ->helperText('Los entornos de pruebas normalmente no se respaldan.'),
                        TextInput::make('respaldo_dias')
                            ->label('Conservar copias (días)')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(14)
                            ->helperText('Siempre se conservan al menos las 3 copias más recientes.'),
                    ]),

                Section::make('Comportamiento')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('es_pruebas')
                            ->label('Es de pruebas')
                            ->helperText('Apagada se muestra en gris, no en rojo.'),
                        Toggle::make('arranque_automatico')
                            ->label('Arranca con el servidor')
                            ->default(true),
                        Toggle::make('publica_internet')
                            ->label('Publicada a internet'),
                        Toggle::make('monitorear')
                            ->label('Monitorear')
                            ->default(true),
                        TextInput::make('orden')
                            ->label('Orden')
                            ->helperText('Posición en el tablero (menor primero).')
                            ->integer()
                            ->minValue(0)
                            ->default(0),
                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Vacía los campos que no aplican al tipo de app antes de guardar.
     */
    public static function limpiarDatos(array $data): array
    {
        if (($data['tipo'] ?? null) === Aplicacion::TIPO_FPM) {
            $data['servicio'] = null;
            $data['servicio_cola'] = null;
            $data['timer_scheduler'] = null;
            $data['puerto_interno'] = null;
        } else {
            $data['pool_fpm'] = null;
        }

        return $data;
    }
}
