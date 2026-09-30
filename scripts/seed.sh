#!/usr/bin/env bash
# Date demo prin WP-CLI: 20 magazine, 60 cupoane, 10 deal-uri (inclusiv expirate).
#
# Local (wp-env):      bash scripts/seed.sh
# Ștergere date demo:  bash scripts/seed.sh reset
# Pe alt server:       WP="wp --path=/cale/wordpress" SEED_FILE=scripts/seed.php bash scripts/seed.sh
#
# ATENȚIE: nu rula pe producție. Datele demo sunt fictive.

set -euo pipefail

cd "$(dirname "$0")/.."

WP=${WP:-"npx wp-env run cli wp"}
# În containerul wp-env, folderul scripts/ e montat în rădăcina WordPress.
SEED_FILE=${SEED_FILE:-"scripts/seed.php"}

$WP eval-file "$SEED_FILE" "$@"
