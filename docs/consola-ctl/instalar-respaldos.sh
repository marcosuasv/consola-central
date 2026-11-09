#!/bin/bash
# Agrega la acción «respaldo» a consola-ctl. Respalda el original y lo restaura si algo falla.
set -euo pipefail
CTL=${CTL:-/usr/local/sbin/consola-ctl}
BLOQUE=${BLOQUE:-/var/www/html/consola-central/docs/consola-ctl/respaldos.sh}
RESPALDO="$CTL.antes-respaldos-$(date +%Y%m%d-%H%M%S)"

grep -q 'respaldo_crear' "$CTL" && { echo "consola-ctl ya tiene la acción respaldo; no se cambió nada."; exit 0; }
grep -q '^valida_jail()' "$CTL"   || { echo "No encuentro valida_jail() en $CTL; no se cambió nada." >&2; exit 1; }
grep -q '^  ayuda)$' "$CTL"       || { echo "No encuentro la rama ayuda) en $CTL; no se cambió nada." >&2; exit 1; }
grep -q '^  cola reiniciar  <carpeta-app>$' "$CTL" || { echo "No encuentro el texto de ayuda; no se cambió nada." >&2; exit 1; }
cp -a "$CTL" "$RESPALDO"

TMP=$(mktemp -d)
# 1) Funciones: desde RESPALDOS_DIR= hasta el final del bloque
sed -n '/^RESPALDOS_DIR=/,$p' "$BLOQUE" > "$TMP/funciones"
# 2) Rama del case (en consola-ctl la acción ya se quitó con shift: $1=operación, $2=base, $3=días)
cat > "$TMP/rama" <<'RAMA'
  respaldo)
    op="${1:-}"
    case "$op" in
      crear)  [ $# -eq 2 ] || error "uso: respaldo crear <base>";         respaldo_crear "$2" ;;
      purgar) [ $# -eq 3 ] || error "uso: respaldo purgar <base> <dias>"; respaldo_purgar "$2" "$3" ;;
      *)      error "respaldo: operación no permitida: $op" ;;
    esac ;;

RAMA
# 3) Ayuda
printf '%s\n' '  respaldo crear  <base>' '  respaldo purgar <base> <dias>' > "$TMP/ayuda"

{
  awk -v f="$TMP/funciones" -v r="$TMP/rama" -v a="$TMP/ayuda" '
    { print }
    /^valida_jail\(\)/                 { print ""; while ((getline l < f) > 0) print l }
    /^  cola reiniciar  <carpeta-app>$/ { while ((getline l < a) > 0) print l }
  ' "$CTL" | awk -v r="$TMP/rama" '
    /^  ayuda\)$/ { while ((getline l < r) > 0) print l }
    { print }
  '
} > "$TMP/nuevo"

if bash -n "$TMP/nuevo"; then
  cat "$TMP/nuevo" > "$CTL"     # conserva dueño y permisos del original
  rm -rf "$TMP"
  echo "Listo. Original respaldado en $RESPALDO"
else
  rm -rf "$TMP"
  echo "La versión nueva tiene un error de sintaxis; consola-ctl NO se modificó." >&2
  exit 1
fi
