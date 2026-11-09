# ============================================================================
# Consola Central — Paso 14: respaldos de bases de datos
# Bloque para AGREGAR a /usr/local/sbin/consola-ctl (como root).
#
# Instalación automática (recomendada), como root:
#   bash /var/www/html/consola-central/docs/consola-ctl/instalar-respaldos.sh
# Respalda consola-ctl, inserta este bloque, valida la sintaxis y solo entonces lo reemplaza.
#
# Instalación manual:
# 1) Pega las funciones de abajo después de valida_jail().
# 2) Antes de la rama `ayuda)` del case principal agrega (consola-ctl ya hizo `shift` de la acción,
#    así que aquí $1 = operación, $2 = base, $3 = días):
#
#      respaldo)
#        op="${1:-}"
#        case "$op" in
#          crear)  [ $# -eq 2 ] || error "uso: respaldo crear <base>";         respaldo_crear "$2" ;;
#          purgar) [ $# -eq 3 ] || error "uso: respaldo purgar <base> <dias>"; respaldo_purgar "$2" "$3" ;;
#          *)      error "respaldo: operación no permitida: $op" ;;
#        esac ;;
#
# Usa las funciones `registrar` y `error` que ya existen en consola-ctl.
# mysqldump corre como root (autenticación unix_socket de MariaDB): no hay contraseñas en archivos.
# Archivos: /var/backups/consola/<base>/<base>-AAAAMMDD-HHMMSS.sql.gz
#   carpetas root:consola 0750 (la consola puede listarlos), archivos root:root 0640 (no puede leerlos).
# ============================================================================

RESPALDOS_DIR=/var/backups/consola

# Falla de ejecución (no es un rechazo de seguridad): se registra como FALLO.
fallo() { echo "ERROR: $*" >&2; registrar "FALLO: $*"; exit 1; }

valida_base() {
  [[ "$1" =~ ^[A-Za-z0-9_-]{1,64}$ ]] || error "base de datos inválida: $1"
  local existe
  existe=$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$1'") \
    || fallo "no se pudo consultar MariaDB"
  [[ "$existe" == "1" ]] || error "la base no existe: $1"
}

respaldo_crear() {
  local base="$1" dir destino tmp
  valida_base "$base"
  dir="$RESPALDOS_DIR/$base"
  install -d -m 0750 -o root -g consola "$RESPALDOS_DIR"
  install -d -m 0750 -o root -g consola "$dir"
  destino="$dir/$base-$(date +%Y%m%d-%H%M%S).sql.gz"
  tmp="$destino.parcial"

  # --single-transaction: copia consistente de InnoDB sin bloquear las tablas
  if ! ( set -o pipefail; umask 077
         mysqldump --single-transaction --quick --routines --triggers --events --hex-blob \
                   --default-character-set=utf8mb4 --databases "$base" | gzip -6 > "$tmp" ); then
    rm -f -- "$tmp"; fallo "mysqldump falló para $base"
  fi
  gzip -t "$tmp" || { rm -f -- "$tmp"; fallo "el archivo de $base quedó dañado"; }
  chown root:root "$tmp"; chmod 0640 "$tmp"
  mv -- "$tmp" "$destino"

  registrar "respaldo crear $base -> $destino"
  echo "OK archivo=$destino bytes=$(stat -c %s "$destino") sha256=$(sha256sum "$destino" | cut -d' ' -f1)"
}

respaldo_purgar() {
  local base="$1" dias="$2" dir f n=0 i=0
  [[ "$base" =~ ^[A-Za-z0-9_-]{1,64}$ ]] || error "base de datos inválida: $base"
  [[ "$dias" =~ ^[0-9]{1,3}$ ]] && (( dias >= 1 && dias <= 365 )) || error "días inválidos: $dias"
  dir="$RESPALDOS_DIR/$base"
  [[ -d "$dir" ]] || { echo "OK borrados=0"; return 0; }

  # Del más nuevo al más viejo: los 3 primeros nunca se borran; de los demás, los de más de $dias días.
  while IFS= read -r f; do
    i=$((i + 1))
    (( i <= 3 )) && continue
    if [[ -n "$(find "$f" -maxdepth 0 -mtime +"$dias" -print)" ]]; then
      rm -f -- "$f"; n=$((n + 1))
    fi
  done < <(ls -1t -- "$dir"/"$base"-*.sql.gz 2>/dev/null)

  registrar "respaldo purgar $base $dias -> $n borrados"
  echo "OK borrados=$n"
}
