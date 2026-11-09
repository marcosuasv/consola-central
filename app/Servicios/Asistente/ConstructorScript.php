<?php

namespace App\Servicios\Asistente;

/**
 * Arma un script bash para que root lo revise y ejecute.
 * Los archivos se escriben con heredoc entre comillas (sin expansión) y se respaldan si ya existían.
 */
final class ConstructorScript
{
    private array $lineas = [];

    private int $paso = 0;

    public function __construct(string $titulo, string $descripcion)
    {
        $fecha = now()->format('Y-m-d H:i');
        $this->lineas[] = <<<BASH
        #!/usr/bin/env bash
        # ============================================================================
        # {$titulo}
        # Generado por Consola Central el {$fecha} por {$this->autor()}.
        # {$descripcion}
        #
        # REVÍSALO COMPLETO ANTES DE EJECUTARLO. Ejecutar como root:
        #   bash este-archivo.sh
        # ============================================================================
        set -euo pipefail

        if [[ \$EUID -ne 0 ]]; then
            echo "Ejecuta este script como root." >&2
            exit 1
        fi

        SELLO=\$(date +%Y%m%d-%H%M%S)

        # Escribe un archivo; si ya existe, guarda un respaldo .bak-<fecha>
        escribir() {
            local destino="\$1"
            if [[ -e "\$destino" ]]; then
                cp -a "\$destino" "\$destino.bak-\$SELLO"
                echo "  · respaldo: \$destino.bak-\$SELLO"
            fi
            cat > "\$destino"
            echo "  · escrito:  \$destino"
        }

        paso() { echo; echo "==> \$*"; }
        BASH;
    }

    public function paso(string $titulo): self
    {
        $this->paso++;
        $this->lineas[] = "\npaso ".escapeshellarg("{$this->paso}. {$titulo}");

        return $this;
    }

    /** Escribe un archivo con su contenido literal. */
    public function archivo(string $ruta, string $contenido, string $modo = '0644'): self
    {
        $delimitador = 'CONSOLA_FIN_'.strtoupper(substr(md5($ruta), 0, 8));
        Validador::exigir(! str_contains($contenido, $delimitador), 'Contenido inválido para el script.');

        $this->lineas[] = 'escribir '.escapeshellarg($ruta)." <<'{$delimitador}'\n".rtrim($contenido)."\n{$delimitador}";
        $this->lineas[] = 'chmod '.$modo.' '.escapeshellarg($ruta);

        return $this;
    }

    /** Comando bash literal (solo se usa con valores ya validados). */
    public function comando(string $bash): self
    {
        $this->lineas[] = $bash;

        return $this;
    }

    public function comentario(string $texto): self
    {
        $this->lineas[] = '# '.str_replace("\n", "\n# ", $texto);

        return $this;
    }

    /** @param list<string> $pasos */
    public function final(array $pasos): string
    {
        $this->lineas[] = "\necho\necho '============================================================'\necho 'Listo. Pasos que faltan (manuales):'";
        foreach ($pasos as $i => $p) {
            $this->lineas[] = 'echo '.escapeshellarg(' '.($i + 1).'. '.$p);
        }
        $this->lineas[] = "echo '============================================================'";

        return implode("\n", $this->lineas)."\n";
    }

    private function autor(): string
    {
        $correo = (string) (auth()->user()?->email ?? 'consola');

        return preg_match(Validador::REGEX_CORREO, $correo) ? $correo : 'consola';
    }
}
