#!/bin/sh
# RNF-2 / T-13: two loans of the last copy at the same time -> one 201, one 409.
# T-15: PNG of 2 MB ok, 3 MB -> 422, no cover -> cover_key null; GET cover returns the binary.
set -u
BASE=${BASE:-http://localhost:8000/api/v1}
PASS=0; FAIL=0
check() { if [ "$2" = "$3" ]; then PASS=$((PASS+1)); echo "ok   $1"; else FAIL=$((FAIL+1)); echo "FAIL $1 (esperado $3, obtenido $2)"; fi; }
J='Content-Type: application/json'
jf() { php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j[$argv[2]] ?? "";' "$1" "$2"; }
RUN=$(date +%s)
curl -s -X POST -H "$J" -d "{\"username\":\"$ADMIN_USERNAME\",\"password\":\"$ADMIN_PASSWORD\"}" "$BASE/auth/login" > /tmp/c.login
A="Authorization: Bearer $(jf /tmp/c.login access_token)"
ISBN=$(printf '979%010d' "$RUN" | cut -c1-13)
curl -s -X POST -H "$A" -H "$J" -d "{\"title\":\"Ultimo $RUN\",\"author\":\"A\",\"isbn\":\"$ISBN\",\"category_id\":\"33333333-3333-4333-8333-333333333333\",\"total_copies\":1}" "$BASE/books" > /tmp/c.book
BOOK=$(jf /tmp/c.book id)
check "sin portada -> cover_key null" "$(jf /tmp/c.book cover_key)" ""
for n in 1 2; do curl -s -X POST -H "$A" -H "$J" -d "{\"full_name\":\"C$n $RUN\",\"document_number\":\"C$n$RUN\"}" "$BASE/members" > /tmp/c.m$n; done
M1=$(jf /tmp/c.m1 id); M2=$(jf /tmp/c.m2 id)
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H "$A" -H "$J" -d "{\"member_id\":\"$M1\",\"items\":[{\"book_id\":\"$BOOK\"}]}" "$BASE/loans" > /tmp/c.r1 &
curl -s -o /dev/null -w '%{http_code}\n' -X POST -H "$A" -H "$J" -d "{\"member_id\":\"$M2\",\"items\":[{\"book_id\":\"$BOOK\"}]}" "$BASE/loans" > /tmp/c.r2 &
wait
check "concurrencia: resultados" "$(cat /tmp/c.r1 /tmp/c.r2 | sort | tr '\n' ' ')" "201 409 "
curl -s -H "$A" "$BASE/books/$BOOK" > /tmp/c.after
check "concurrencia: disponibles = 0, nunca negativos" "$(jf /tmp/c.after available_copies)" 0

php -r 'file_put_contents("/tmp/c.2mb.png", "\x89PNG\r\n\x1A\n".str_repeat("a", 2*1024*1024-8));'
php -r 'file_put_contents("/tmp/c.3mb.png", "\x89PNG\r\n\x1A\n".str_repeat("a", 3*1024*1024));'
php -r 'file_put_contents("/tmp/c.txt.png", "no soy una imagen");'
check "portada PNG 2 MB -> 200" "$(curl -s -o /tmp/c.cover -w '%{http_code}' -X PUT -H "$A" -F file=@/tmp/c.2mb.png "$BASE/books/$BOOK/cover")" 200
check "cover_key opaca" "$(jf /tmp/c.cover cover_key | grep -c '/')" 0
check "GET portada -> binario" "$(curl -s -o /tmp/c.got -w '%{http_code}' -H "$A" "$BASE/books/$BOOK/cover")" 200
check "GET portada -> mismo tamaño" "$(wc -c < /tmp/c.got | tr -d ' ')" 2097152
check "portada 3 MB -> 422" "$(curl -s -o /dev/null -w '%{http_code}' -X PUT -H "$A" -F file=@/tmp/c.3mb.png "$BASE/books/$BOOK/cover")" 422
check "portada no imagen -> 422" "$(curl -s -o /dev/null -w '%{http_code}' -X PUT -H "$A" -F file=@/tmp/c.txt.png "$BASE/books/$BOOK/cover")" 422
check "quitar portada -> 204" "$(curl -s -o /dev/null -w '%{http_code}' -X DELETE -H "$A" "$BASE/books/$BOOK/cover")" 204
check "sin portada -> GET 404" "$(curl -s -o /dev/null -w '%{http_code}' -H "$A" "$BASE/books/$BOOK/cover")" 404
echo "PASS=$PASS FAIL=$FAIL"
[ "$FAIL" -eq 0 ]
