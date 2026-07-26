#!/usr/bin/env bash

set -u

URL="${1:-http://127.0.0.1:8000/articles/search}"
NUM_REQUESTS="${NUM_REQUESTS:-20}"
PARALLEL_REQUESTS="${PARALLEL_REQUESTS:-5}"

echo "Target locale/autorizzato: $URL"
echo "Baseline (codice HTTP e tempo totale):"
curl --silent --output /dev/null --get \
    --data-urlencode "query=baseline" \
    --write-out "HTTP %{http_code} - %{time_total}s\n" \
    "$URL"

echo "Burst controllato: $NUM_REQUESTS richieste, concorrenza $PARALLEL_REQUESTS"
seq "$NUM_REQUESTS" | xargs -P "$PARALLEL_REQUESTS" -I REQUEST_NUMBER \
    curl --silent --output /dev/null --get \
        --data-urlencode "query=dos-simulation-REQUEST_NUMBER" \
        --write-out "HTTP %{http_code} - %{time_total}s\n" \
        "$URL"

echo "Dopo il burst (deve restituire HTTP 429 durante il blocco):"
curl --silent --output /dev/null --get \
    --data-urlencode "query=after-burst" \
    --write-out "HTTP %{http_code} - %{time_total}s\n" \
    "$URL"
