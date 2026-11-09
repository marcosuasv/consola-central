<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ManejaPaqueteAsistente;
use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Servicios\Asistente\GeneradorAplicacion;
use App\Servicios\Asistente\Paquete;
use App\Servicios\Asistente\Validador;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Paso 13: asistente para agregar una aplicación. Genera el script; no ejecuta nada en el servidor.
 */
class AgregarAplicacion extends Page
{
    use ManejaPaqueteAsistente;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $title = 'Agregar aplicación';

    protected static ?string $slug = 'asistente/aplicacion';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.asistente';

    public function getSubheading(): ?string
    {
        return 'Genera los archivos de systemd, Nginx, PHP-FPM, base de datos, firewall y Fail2ban para una app nueva. La consola no ejecuta nada: entrega un script para que root lo revise.';
    }

    public function mount(): void
    {
        $this->form->fill($this->valoresIniciales());
    }

    protected function valoresIniciales(): array
    {
        return [
            'tipo' => Aplicacion::TIPO_OCTANE,
            'usuario' => config('consola.asistente.usuario_apps'),
            'octane_servidor' => config('consola.asistente.octane_servidor'),
            'puerto_interno' => Validador::puertoInternoSugerido(),
            'crear_bd' => true,
            'sesiones_en_bd' => true,
            'publica' => false,
            'pruebas' => false,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $esOctane = fn (Get $get) => $get('tipo') === Aplicacion::TIPO_OCTANE;
        $ocupados = Validador::puertosOcupados();
        $textoOcupados = collect($ocupados)->keys()->sort()->implode(', ');

        $noOcupado = fn () => function (string $attribute, $value, \Closure $fail) use ($ocupados) {
            foreach (preg_split('/\s*,\s*/', (string) $value) as $p) {
                if (isset($ocupados[(int) $p])) {
                    $fail("El puerto {$p} ya lo usa {$ocupados[(int) $p]}.");
                }
            }
        };

        return $schema->statePath('data')->components([
            Wizard::make([
                Step::make('Aplicación')
                    ->icon(Heroicon::OutlinedCube)
                    ->description('Nombre y carpeta')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nombre')
                                ->label('Nombre visible')
                                ->required()
                                ->regex(Validador::REGEX_NOMBRE)
                                ->maxLength(100),
                            TextInput::make('carpeta')
                                ->label('Carpeta en /var/www/html')
                                ->required()
                                ->regex(Aplicacion::REGEX_CARPETA)
                                ->unique(Aplicacion::class, 'carpeta')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                                    $clave = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $state)), '-');
                                    $set('clave', $clave);
                                    $set('pool', str_replace('-', '_', $clave));
                                    $bd = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $state);
                                    $get('base_datos') ?: $set('base_datos', $bd);
                                    $get('usuario_bd') ?: $set('usuario_bd', substr(str_replace('-', '_', strtolower($bd)), 0, 32));
                                })
                                ->helperText('La app ya debe estar copiada o clonada ahí.'),
                        ]),
                        ToggleButtons::make('tipo')
                            ->label('Cómo corre')
                            ->options(Aplicacion::TIPOS)
                            ->icons([Aplicacion::TIPO_OCTANE => Heroicon::OutlinedBolt, Aplicacion::TIPO_FPM => Heroicon::OutlinedCube])
                            ->inline()
                            ->required()
                            ->live(),
                        Grid::make(2)->schema([
                            Toggle::make('pruebas')
                                ->label('Es entorno de pruebas')
                                ->helperText('No se habilita al arrancar el servidor.'),
                            Toggle::make('publica')
                                ->label('Publicada a internet')
                                ->helperText('Si no, el firewall solo permite redes internas (10/8, 172.16/12, 192.168/16).'),
                        ]),
                    ]),

                Step::make('Servicio y red')
                    ->icon(Heroicon::OutlinedServer)
                    ->description('Puertos y servicio')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('clave')
                                ->label('Clave de servicio')
                                ->prefix('app-')
                                ->required()
                                ->regex(Validador::REGEX_CLAVE)
                                ->helperText('Nombre de las unidades: app-<clave>, app-<clave>-queue…'),
                            TextInput::make('usuario')
                                ->label('Usuario del sistema que la ejecuta')
                                ->required()
                                ->regex(Validador::REGEX_USUARIO_SO),
                            Select::make('octane_servidor')
                                ->label('Servidor de Octane')
                                ->live()
                                ->options(['roadrunner' => 'RoadRunner (el que usan las apps actuales)', 'swoole' => 'Swoole', 'frankenphp' => 'FrankenPHP'])
                                ->required($esOctane)
                                ->visible($esOctane)
                                ->native(false),
                            TextInput::make('puerto_interno')
                                ->label('Puerto interno (127.0.0.1)')
                                ->integer()
                                ->minValue(1024)
                                ->maxValue(65535)
                                ->required($esOctane)
                                ->visible($esOctane)
                                ->rule($noOcupado)
                                ->helperText('Sugerido: el siguiente libre del rango 18xxx.'),
                            TextInput::make('puerto_rpc')
                                ->label('Puerto RPC de RoadRunner')
                                ->integer()
                                ->minValue(1024)
                                ->maxValue(65535)
                                ->placeholder(fn (Get $get) => $get('puerto_interno') ? (string) ((int) $get('puerto_interno') - 2000) : null)
                                ->helperText('Vacío = puerto interno − 2000 (como GamTask: 18070 → 16070).')
                                ->visible(fn (Get $get) => $esOctane($get) && $get('octane_servidor') === 'roadrunner'),
                            TextInput::make('pool')
                                ->label('Pool de PHP-FPM')
                                ->regex(Aplicacion::REGEX_POOL)
                                ->required(fn (Get $get) => ! $esOctane($get))
                                ->visible(fn (Get $get) => ! $esOctane($get)),
                            TextInput::make('puertos_publicos')
                                ->label('Puertos públicos (HTTPS)')
                                ->placeholder('8100,4900')
                                ->required()
                                ->regex(Aplicacion::REGEX_PUERTOS)
                                ->rule($noOcupado)
                                ->helperText("Separados por comas. Ya ocupados: {$textoOcupados}."),
                        ]),
                    ]),

                Step::make('Base de datos')
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->description('MariaDB')
                    ->schema([
                        Toggle::make('crear_bd')
                            ->label('Crear la base de datos y su usuario')
                            ->helperText('La contraseña la genera el script al ejecutarse y solo se muestra a root. La consola nunca la guarda.')
                            ->live(),
                        Grid::make(2)->schema([
                            TextInput::make('base_datos')
                                ->label('Base de datos')
                                ->required()
                                ->regex(Aplicacion::REGEX_BD)
                                ->maxLength(64),
                            TextInput::make('usuario_bd')
                                ->label('Usuario de BD')
                                ->required()
                                ->regex(Validador::REGEX_USUARIO_BD),
                            Toggle::make('sesiones_en_bd')
                                ->label('La app guarda sesiones en la base de datos')
                                ->helperText('Para contar usuarios conectados (permiso de lectura en sessions).'),
                        ]),
                    ]),
            ])
                ->submitAction($this->botonGenerar())
                ->contained(true),
        ]);
    }

    protected function construirPaquete(array $datos): Paquete
    {
        return app(GeneradorAplicacion::class)->generar($datos);
    }

    public function crearFichaAction(): Action
    {
        return Action::make('crearFicha')
            ->label('Registrar en la consola')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->color('success')
            ->visible(fn () => $this->paquete !== null)
            ->requiresConfirmation()
            ->modalHeading('Registrar la app en la consola')
            ->modalDescription('Crea la ficha para que el Tablero la vigile. Hazlo cuando root ya haya ejecutado el script (o lo vaya a ejecutar en breve).')
            ->action(function () {
                try {
                    $app = Aplicacion::create(app(GeneradorAplicacion::class)->ficha($this->data));
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title('No se pudo registrar')->body($e->getMessage())->send();

                    return;
                }

                Auditoria::create([
                    'user_id' => auth()->id(), 'aplicacion_id' => $app->id, 'accion' => 'asistente.registrar_app',
                    'objetivo' => $app->carpeta, 'resultado' => 'ok', 'ip' => request()->ip(), 'created_at' => now(),
                ]);

                Notification::make()->success()->title("{$app->nombre} registrada")->send();
                $this->redirect(AplicacionResource::getUrl('monitor', ['record' => $app]));
            });
    }
}
