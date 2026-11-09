<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ManejaPaqueteAsistente;
use App\Filament\Resources\Aplicaciones\AplicacionResource;
use App\Models\Aplicacion;
use App\Models\Auditoria;
use App\Servicios\Asistente\GeneradorDominio;
use App\Servicios\Asistente\Paquete;
use App\Servicios\Asistente\Validador;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Asistente para publicar una app con un dominio (ej. task.gbpo.com.mx). Genera el script; no ejecuta nada.
 */
class AgregarDominio extends Page
{
    use ManejaPaqueteAsistente;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $title = 'Agregar dominio';

    protected static ?string $slug = 'asistente/dominio';

    protected static ?int $navigationSort = 31;

    protected string $view = 'filament.pages.asistente';

    /** Permite abrir el asistente con la app ya elegida: ?app=<id> */
    #[Url]
    public ?int $app = null;

    public function getSubheading(): ?string
    {
        return 'Asigna un dominio a una app (ej. task.gbpo.com.mx → GamTask): sitio de Nginx con HTTPS, certificado, firewall y pasos de DNS. La consola entrega el script; root lo revisa y lo ejecuta.';
    }

    public function mount(): void
    {
        $this->form->fill($this->valoresIniciales());
    }

    protected function valoresIniciales(): array
    {
        return [
            'aplicacion_id' => Aplicacion::whereKey($this->app)->exists() ? $this->app : null,
            'tls' => GeneradorDominio::TLS_LETSENCRYPT,
            'correo' => auth()->user()?->email,
            'redirigir_http' => false,
            'publico' => false,
            'hsts' => false,
        ];
    }

    public function form(Schema $schema): Schema
    {
        $tls = fn (Get $get) => $get('tls');

        return $schema->statePath('data')->components([
            Wizard::make([
                Step::make('Dominio')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->description('Qué app y qué nombre')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('aplicacion_id')
                                ->label('Aplicación')
                                ->options(Aplicacion::ordenadas()->get()->mapWithKeys(fn (Aplicacion $a) => [
                                    $a->id => $a->nombre.($a->dominios ? ' · '.$a->dominios : ''),
                                ]))
                                ->required()
                                ->native(false)
                                ->searchable(),
                            TextInput::make('dominio')
                                ->label('Dominio')
                                ->placeholder('task.gbpo.com.mx')
                                ->required()
                                ->maxLength(253)
                                ->regex(Aplicacion::REGEX_DOMINIO)
                                ->dehydrateStateUsing(fn (?string $state) => strtolower(trim((string) $state)))
                                ->validationMessages(['regex' => 'Escribe solo el dominio en minúsculas, sin https:// ni rutas (ej. task.gbpo.com.mx).'])
                                ->helperText('Sin https:// ni rutas.'),
                        ]),
                    ]),

                Step::make('Certificado')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->description('HTTPS')
                    ->schema([
                        Radio::make('tls')
                            ->label('¿De dónde sale el certificado?')
                            ->options([
                                GeneradorDominio::TLS_LETSENCRYPT => "Let's Encrypt",
                                GeneradorDominio::TLS_ARCHIVO => 'Certificado propio',
                                GeneradorDominio::TLS_LOCAL => 'Certificado local del servidor',
                            ])
                            ->descriptions([
                                GeneradorDominio::TLS_LETSENCRYPT => 'Gratis y se renueva solo. Requiere que el dominio apunte a este servidor desde internet y el puerto 80 abierto.',
                                GeneradorDominio::TLS_ARCHIVO => 'Por ejemplo un comodín *.gbpo.com.mx comprado. Indica dónde están los archivos en /etc.',
                                GeneradorDominio::TLS_LOCAL => 'Autofirmado (/etc/nginx/ssl/local-cert.pem). Funciona, pero el navegador muestra advertencia. Útil para red interna.',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('correo')
                            ->label("Correo para avisos de Let's Encrypt")
                            ->email()
                            ->required(fn (Get $get) => $tls($get) === GeneradorDominio::TLS_LETSENCRYPT)
                            ->visible(fn (Get $get) => $tls($get) === GeneradorDominio::TLS_LETSENCRYPT),
                        Grid::make(2)
                            ->visible(fn (Get $get) => $tls($get) === GeneradorDominio::TLS_ARCHIVO)
                            ->schema([
                                TextInput::make('certificado')
                                    ->label('Certificado (cadena completa)')
                                    ->placeholder('/etc/ssl/gbpo/fullchain.pem')
                                    ->required(fn (Get $get) => $tls($get) === GeneradorDominio::TLS_ARCHIVO)
                                    ->regex(Validador::REGEX_RUTA_ETC),
                                TextInput::make('certificado_llave')
                                    ->label('Llave privada')
                                    ->placeholder('/etc/ssl/gbpo/privkey.pem')
                                    ->required(fn (Get $get) => $tls($get) === GeneradorDominio::TLS_ARCHIVO)
                                    ->regex(Validador::REGEX_RUTA_ETC),
                            ]),
                    ]),

                Step::make('Acceso')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->description('Quién puede entrar')
                    ->schema([
                        Toggle::make('publico')
                            ->label('Accesible desde internet')
                            ->helperText('Si no, el firewall solo deja entrar a las redes internas. Publicar a internet aumenta la exposición: revisa Seguridad a diario.'),
                        Toggle::make('redirigir_http')
                            ->label('Bloque propio en el puerto 80')
                            ->helperText("Normalmente no hace falta: el sitio apps-https del servidor ya redirige http:// → https:// para cualquier nombre. Con Let's Encrypt se activa solo (para la validación)."),
                        Toggle::make('hsts')
                            ->label('Forzar HTTPS en el navegador (HSTS)')
                            ->helperText('Actívalo solo cuando el certificado ya funcione bien. No aplica al certificado local.'),
                    ]),
            ])
                ->submitAction($this->botonGenerar())
                ->contained(true),
        ]);
    }

    protected function construirPaquete(array $datos): Paquete
    {
        return app(GeneradorDominio::class)->generar($datos);
    }

    public function crearFichaAction(): Action
    {
        return Action::make('crearFicha')
            ->label('Guardar dominio en la ficha')
            ->icon(Heroicon::OutlinedBookmarkSquare)
            ->color('success')
            ->visible(fn () => $this->paquete !== null)
            ->requiresConfirmation()
            ->modalHeading('Guardar el dominio en la ficha de la app')
            ->modalDescription('Se agrega a la lista de dominios de la app y se usa como su URL de acceso. Hazlo cuando root ya haya ejecutado el script.')
            ->action(function () {
                try {
                    $app = app(GeneradorDominio::class)->guardarEnFicha($this->data);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title('No se pudo guardar')->body($e->getMessage())->send();

                    return;
                }

                Auditoria::create([
                    'user_id' => auth()->id(), 'aplicacion_id' => $app->id, 'accion' => 'asistente.registrar_dominio',
                    'objetivo' => $this->data['dominio'] ?? null, 'resultado' => 'ok', 'ip' => request()->ip(), 'created_at' => now(),
                ]);

                Notification::make()->success()->title('Dominio guardado en la ficha de '.$app->nombre)->send();
                $this->redirect(AplicacionResource::getUrl('monitor', ['record' => $app]));
            });
    }
}
