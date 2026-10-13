#!/bin/sh
# End-to-end contract smoke test against a running API (T-16, T-17, T-13).
# Usage: BASE=http://localhost:8000/api/v1 ADMIN_USERNAME=... ADMIN_PASSWORD=... sh tests/e2e/smoke.sh
set -u
BASE=${BASE:-http://localhost:8000/api/v1}
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "ok   $1"; else FAIL=$((FAIL+1)); echo "FAIL $1 (esperado $3, obtenido $2)"; fi; }
req() { curl -s -o /tmp/smoke.body -w '%{http_code}' "$@"; }
field() { php -r '$j=json_decode(file_get_contents("/tmp/smoke.body"),true); $p=explode(".",$argv[1]); foreach($p as $k){$j=$j[$k]??null;} echo is_array($j)?json_encode($j):$j;' "$1"; }
RUN=$(date +%s)

check "health" "$(req "$BASE/health")" 200
check "sin token -> 401" "$(req "$BASE/books")" 401
check "sin token -> UNAUTHENTICATED" "$(field code)" UNAUTHENTICATED
check "login malo -> 401" "$(req -X POST -H 'Content-Type: application/json' -d '{"username":"nadie","password":"x"}' "$BASE/auth/login")" 401
check "login malo -> INVALID_CREDENTIALS" "$(field code)" INVALID_CREDENTIALS
check "login admin -> 200" "$(req -X POST -H 'Content-Type: application/json' -d "{\"username\":\"$ADMIN_USERNAME\",\"password\":\"$ADMIN_PASSWORD\"}" "$BASE/auth/login")" 200
check "token 8 h" "$(field expires_in)" 28800
ADMIN="Authorization: Bearer $(field access_token)"
J='Content-Type: application/json'

check "crear bibliotecario" "$(req -X POST -H "$ADMIN" -H "$J" -d "{\"username\":\" Lib$RUN \",\"password\":\"clave-$RUN\",\"role\":\"librarian\"}" "$BASE/users")" 201
check "username normalizado" "$(field username)" "lib$RUN"
check "sin password en respuesta" "$(grep -c password /tmp/smoke.body)" 0
check "pedir rol admin -> 422" "$(req -X POST -H "$ADMIN" -H "$J" -d "{\"username\":\"x$RUN\",\"password\":\"p\",\"role\":\"admin\"}" "$BASE/users")" 422
check "usuario repetido -> 409" "$(req -X POST -H "$ADMIN" -H "$J" -d "{\"username\":\"lib$RUN\",\"password\":\"p\",\"role\":\"librarian\"}" "$BASE/users")" 409
req -X POST -H "$J" -d "{\"username\":\"lib$RUN\",\"password\":\"clave-$RUN\"}" "$BASE/auth/login" >/dev/null
LIB="Authorization: Bearer $(field access_token)"
check "librarian no crea libros -> 403" "$(req -X POST -H "$LIB" -H "$J" -d '{}' "$BASE/books")" 403
check "categorias" "$(req -H "$LIB" "$BASE/categories")" 200

ISBN=$(printf '978%010d' "$RUN" | cut -c1-13)
check "crear libro" "$(req -X POST -H "$ADMIN" -H "$J" -d "{\"title\":\"Libro $RUN\",\"author\":\"Autor\",\"isbn\":\"$ISBN\",\"category_id\":\"11111111-1111-4111-8111-111111111111\",\"total_copies\":1}" "$BASE/books")" 201
BOOK=$(field id)
check "disponibles = total" "$(field available_copies)" 1
check "cover_key null" "$(field cover_key)" ""
check "isbn repetido -> 409" "$(req -X POST -H "$ADMIN" -H "$J" -d "{\"title\":\"Otro\",\"author\":\"A\",\"isbn\":\"$ISBN\",\"category_id\":\"11111111-1111-4111-8111-111111111111\",\"total_copies\":1}" "$BASE/books")" 409
check "buscar -> 200" "$(req -H "$LIB" "$BASE/books?q=$RUN&size=5")" 200
check "buscar total" "$(field total)" 1
check "size 51 -> 422" "$(req -H "$LIB" "$BASE/books?size=51")" 422

check "crear socio" "$(req -X POST -H "$LIB" -H "$J" -d "{\"full_name\":\"Socio $RUN\",\"document_number\":\"D$RUN\"}" "$BASE/members")" 201
M1=$(field id)
check "documento repetido -> 409" "$(req -X POST -H "$LIB" -H "$J" -d "{\"full_name\":\"Otro\",\"document_number\":\"D$RUN\"}" "$BASE/members")" 409
req -X POST -H "$LIB" -H "$J" -d "{\"full_name\":\"Socio2 $RUN\",\"document_number\":\"E$RUN\"}" "$BASE/members" >/dev/null; M2=$(field id)

check "prestar" "$(req -X POST -H "$LIB" -H "$J" -d "{\"member_id\":\"$M1\",\"items\":[{\"book_id\":\"$BOOK\"}]}" "$BASE/loans")" 201
LOAN=$(field id); ITEM=$(field items.0.id)
check "estado open" "$(field status)" open
check "titulo congelado" "$(field items.0.book_title)" "Libro $RUN"
check "sin ejemplares -> 409" "$(req -X POST -H "$LIB" -H "$J" -d "{\"member_id\":\"$M2\",\"items\":[{\"book_id\":\"$BOOK\"}]}" "$BASE/loans")" 409
check "BOOK_NOT_AVAILABLE" "$(field code)" BOOK_NOT_AVAILABLE
check "libro repetido -> 422" "$(req -X POST -H "$LIB" -H "$J" -d "{\"member_id\":\"$M2\",\"items\":[{\"book_id\":\"$BOOK\"},{\"book_id\":\"$BOOK\"}]}" "$BASE/loans")" 422
check "baja con prestamo abierto -> 409" "$(req -X DELETE -H "$ADMIN" "$BASE/books/$BOOK")" 409
check "socio active_loans" "$(req -H "$LIB" "$BASE/members/$M1")" 200
check "active_loans = 1" "$(field active_loans)" 1
check "detalle prestamo" "$(req -H "$LIB" "$BASE/loans/$LOAN")" 200
check "listar prestamos" "$(req -H "$LIB" "$BASE/loans?member_id=$M1&status=open")" 200
check "listar total" "$(field total)" 1
check "vencidos" "$(req -H "$LIB" "$BASE/loans/overdue")" 200

check "renombrar libro" "$(req -X PUT -H "$ADMIN" -H "$J" -d "{\"title\":\"Renombrado $RUN\",\"author\":\"Autor\",\"category_id\":\"22222222-2222-4222-8222-222222222222\",\"total_copies\":1}" "$BASE/books/$BOOK")" 200
TODAY=$(date -u +%F)
check "reporte admin" "$(req -H "$ADMIN" "$BASE/reports/most-borrowed?from=$TODAY&to=$TODAY")" 200
check "reporte conserva titulo viejo" "$(grep -c "Libro $RUN" /tmp/smoke.body)" 1
check "reporte sin socios" "$(grep -c member /tmp/smoke.body)" 0
check "reporte librarian -> 403" "$(req -H "$LIB" "$BASE/reports/most-borrowed?from=$TODAY&to=$TODAY")" 403
check "rango invertido -> 422" "$(req -H "$ADMIN" "$BASE/reports/most-borrowed?from=2026-10-31&to=2026-10-01")" 422
check "INVALID_DATE_RANGE" "$(field code)" INVALID_DATE_RANGE

check "devolver" "$(req -X POST -H "$LIB" "$BASE/loans/$LOAN/items/$ITEM/return")" 200
check "devolver otra vez -> 409" "$(req -X POST -H "$LIB" "$BASE/loans/$LOAN/items/$ITEM/return")" 409
check "ITEM_ALREADY_RETURNED" "$(field code)" ITEM_ALREADY_RETURNED
check "libro de nuevo disponible" "$(req -H "$LIB" "$BASE/books/$BOOK")" 200
check "disponibles = 1 (no suma dos veces)" "$(field available_copies)" 1
check "prestamo cerrado" "$(req -H "$LIB" "$BASE/loans/$LOAN")" 200
check "estado closed" "$(field status)" closed
check "baja logica" "$(req -X DELETE -H "$ADMIN" "$BASE/books/$BOOK")" 204
check "libro dado de baja -> 404" "$(req -H "$LIB" "$BASE/books/$BOOK")" 404

echo "PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
