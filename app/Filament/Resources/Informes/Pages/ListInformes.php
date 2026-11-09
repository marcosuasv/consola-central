<?php

namespace App\Filament\Resources\Informes\Pages;

use App\Filament\Resources\Informes\InformeResource;
use App\Models\Informe;
use App\Models\User;
use App\Servicios\Informes\GeneradorInforme;
use App\Servicios\Informes\ServicioCorreo;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListInformes extends ListRecords
{
    protected static string $resource = InformeResource::class;

    public function getSubheading(): ?string
    {
        return 'Informe de cada mañana con el semáforo de operación, ciberseguridad, mantenimiento y respaldo por app.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generar')
                ->label('Generar informe ahora')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('gray')
                ->visible(fn () => (bool) auth()->user()?->esAdmin())
                ->requiresConfirmation()
                ->modalHeading('Generar un informe rápido')
                ->modalDescription('Se genera con los datos actuales, sin el análisis de librerías (composer), y no se envía a nadie. El informe completo es el de las 07:00.')
                ->modalSubmitActionLabel('Generar')
                ->action(function () {
                    $informe = app(GeneradorInforme::class)->generar(completo: false);
                    $this->redirect(InformeResource::getUrl('view', ['record' => $informe]));
                }),

            Action::make('prueba')
                ->label('Enviar informe de prueba')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn () => (bool) auth()->user()?->esAdmin())
                ->modalHeading('Enviar informe de prueba')
                ->modalDescription(ServicioCorreo::configurado()
                    ? 'Envía el informe más reciente (o uno rápido si aún no hay) a un solo correo, para comprobar la configuración.'
                    : 'El correo no está configurado (MAIL_MAILER='.config('mail.default').'): el mensaje NO saldrá del servidor, solo quedará en storage/logs/laravel.log.')
                ->modalSubmitActionLabel('Enviar prueba')
                ->schema([
                    TextInput::make('correo')
                        ->label('Enviar a')
                        ->email()
                        ->required()
                        ->default(fn () => auth()->user()?->email)
                        ->regex('/'.preg_quote(User::DOMINIO, '/').'$/i')
                        ->validationMessages(['regex' => 'Solo correos '.User::DOMINIO.'.']),
                ])
                ->rateLimit(5)
                ->action(function (array $data) {
                    $informe = Informe::latest('created_at')->first() ?? app(GeneradorInforme::class)->generar(completo: false);
                    $r = app(ServicioCorreo::class)->enviarPrueba($informe, strtolower($data['correo']));

                    if ($r['error']) {
                        Notification::make()->danger()->persistent()->title('No se pudo enviar')->body($r['error'])->send();
                    } elseif (! ServicioCorreo::configurado()) {
                        Notification::make()->warning()->title('Correo sin configurar')->body('El mensaje quedó en el log; no salió del servidor.')->send();
                    } else {
                        Notification::make()->success()->title('Prueba enviada a '.$data['correo'])->body('Revisa la bandeja (y la carpeta de spam).')->send();
                    }
                }),
        ];
    }
}
