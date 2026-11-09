<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ResumenRespaldos;
use App\Models\Aplicacion;
use App\Models\Respaldo;
use App\Servicios\Respaldos\ServicioRespaldos;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Paso 14: estado de los respaldos de bases de datos por app, con «Respaldar ahora».
 */
class Respaldos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoreo';

    protected static ?string $title = 'Respaldos';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.respaldos';

    public function getSubheading(): ?string
    {
        return 'Respaldo diario de las bases de datos de las apps activas. Root los genera con consola-ctl en '.config('consola.respaldos.carpeta').'; la consola solo los lista.';
    }

    public static function getNavigationBadge(): ?string
    {
        $fallos = Respaldo::where('resultado', 'error')->where('created_at', '>=', now()->subDay())->count();

        return $fallos > 0 ? (string) $fallos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Respaldos con error en 24 h';
    }

    protected function getHeaderWidgets(): array
    {
        return [ResumenRespaldos::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    private function ultimo(Aplicacion $app): ?Respaldo
    {
        return Respaldo::where('base_datos', $app->base_datos)->latest('created_at')->first();
    }

    private function ultimoOk(Aplicacion $app): ?Respaldo
    {
        return Respaldo::where('base_datos', $app->base_datos)->where('resultado', 'ok')->latest('created_at')->first();
    }

    public function table(Table $table): Table
    {
        $limite = (int) config('consola.respaldos.horas_alerta', 26);

        return $table
            ->query(Aplicacion::query()->whereNotNull('base_datos'))
            ->defaultSort('orden')
            ->paginated(false)
            ->poll('60s')
            ->columns([
                TextColumn::make('nombre')
                    ->label('Aplicación')
                    ->weight('semibold')
                    ->description(fn (Aplicacion $r) => 'BD '.$r->base_datos),
                IconColumn::make('respaldo_activo')
                    ->label('Diario')
                    ->boolean()
                    ->tooltip(fn (Aplicacion $r) => $r->respaldo_activo ? 'Se respalda cada noche' : 'No se respalda (cámbialo en la ficha de la app)'),
                TextColumn::make('ultimo_ok')
                    ->label('Último respaldo correcto')
                    ->state(fn (Aplicacion $r) => $this->ultimoOk($r)?->created_at)
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->badge()
                    ->color(fn ($state, Aplicacion $r) => match (true) {
                        ! $r->respaldo_activo => 'gray',
                        $state === null => 'danger',
                        $state->lt(now()->subHours($limite)) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('tamano')
                    ->label('Tamaño')
                    ->state(fn (Aplicacion $r) => Formato::bytes($this->ultimoOk($r)?->tamano_bytes, 1)),
                TextColumn::make('estado_ultimo')
                    ->label('Último intento')
                    ->state(fn (Aplicacion $r) => $this->ultimo($r)?->resultado)
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'ok' => 'Correcto', 'error' => 'Error', default => '—'
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ok' => 'success', 'error' => 'danger', default => 'gray'
                    })
                    ->tooltip(fn (Aplicacion $r) => $this->ultimo($r)?->resultado === 'error' ? mb_strimwidth((string) $this->ultimo($r)->mensaje, 0, 200, '…') : null),
                TextColumn::make('en_disco')
                    ->label('Copias en disco')
                    ->state(function (Aplicacion $r) {
                        $archivos = app(ServicioRespaldos::class)->archivosEnDisco($r->base_datos);

                        return $archivos === null ? 'sin acceso' : count($archivos).' · '.Formato::bytes(array_sum(array_column($archivos, 'bytes')), 1);
                    })
                    ->color('gray'),
                TextColumn::make('respaldo_dias')
                    ->label('Conservar')
                    ->suffix(' días'),
            ])
            ->recordActions([
                Action::make('respaldarAhora')
                    ->label('Respaldar ahora')
                    ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
                    ->color('primary')
                    ->visible(fn () => (bool) auth()->user()?->esAdmin())
                    ->disabled(fn () => ! auth()->user()?->puedeControlar())
                    ->tooltip(fn () => auth()->user()?->puedeControlar() ? null : 'Activa la verificación en dos pasos en tu perfil para usar los controles.')
                    ->requiresConfirmation()
                    ->modalIcon(Heroicon::OutlinedArchiveBox)
                    ->modalHeading(fn (Aplicacion $record) => "Respaldar {$record->base_datos} ahora")
                    ->modalDescription('Root genera un volcado completo con mysqldump (sin bloquear las tablas InnoDB). En bases grandes puede tardar algunos minutos.')
                    ->modalSubmitActionLabel('Sí, respaldar')
                    ->rateLimit(5)
                    ->action(function (Aplicacion $record) {
                        if (! auth()->user()?->puedeControlar()) {
                            Notification::make()->danger()->title('No autorizado')->send();

                            return;
                        }

                        @set_time_limit((int) config('consola.respaldos.timeout', 1800) + 30);
                        $r = app(ServicioRespaldos::class)->respaldar($record, $record->base_datos, max(1, (int) $record->respaldo_dias), 'manual');

                        $r->exitoso()
                            ? Notification::make()->success()->title("Respaldo de {$record->base_datos} listo")->body(Formato::bytes($r->tamano_bytes, 1).' · '.basename((string) $r->archivo))->send()
                            : Notification::make()->danger()->persistent()->title("El respaldo de {$record->base_datos} falló")->body(mb_strimwidth((string) $r->mensaje, 0, 300, '…'))->send();
                    }),
            ])
            ->emptyStateHeading('Ninguna app tiene base de datos registrada');
    }

    protected function getViewData(): array
    {
        return [
            'historial' => Respaldo::with('aplicacion', 'user')->latest('created_at')->limit(30)->get(),
        ];
    }
}
