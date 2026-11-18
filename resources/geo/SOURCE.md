# Landing geolocation data

The bundled indexes are derived from [DB-IP Country Lite 2026-11](https://db-ip.com/db/lite.php).
Source: https://download.db-ip.com/free/dbip-country-lite-2026-11.csv.gz
Source SHA-256: e5c8459d638a272ece7f4d7cfa49dce18d91b4f403f6940ba9249bf0db3781fd

IP geolocation data by [DB-IP.com](https://db-ip.com), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
GNOSIX keeps the country codes and merges neighboring ranges where the code is the same. Unknown ranges use ZZ. Each packed record contains a range start and two ASCII bytes for the country code. The next record starts the next range.
