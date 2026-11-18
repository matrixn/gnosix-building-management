"""Build a local Romania/foreign index from the official DB-IP Country Lite CSV."""
import argparse
import csv
import gzip
import hashlib
import ipaddress
from pathlib import Path

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("csv_gz", type=Path)
parser.add_argument("release")
args = parser.parse_args()
out = Path(__file__).resolve().parents[1] / "resources" / "geo"
out.mkdir(exist_ok=True)
ranges = {4: [], 6: []}
endpoints = {4: -1, 6: -1}
with gzip.open(args.csv_gz, "rt", encoding="utf-8", newline="") as stream:
    for first, last, country in csv.reader(stream):
        begin = ipaddress.ip_address(first)
        end = ipaddress.ip_address(last)
        low, high = int(begin), int(end)
        family = begin.version
        if end.version != family or low > high or low <= endpoints[family]:
            raise ValueError(f"Unexpected DB-IP range: {first} through {last}")
        endpoints[family] = high
        if country in {"ZZ", "XX", ""}:
            continue
        status = b"R" if country == "RO" else b"F"
        records = ranges[family]
        if records and records[-1][1] + 1 == low and records[-1][2] == status:
            records[-1] = (records[-1][0], high, status)
        else:
            records.append((low, high, status))

for family, size in [(4, 4), (6, 16)]:
    records = ranges[family]
    if not records:
        raise ValueError(f"No ranges for IPv{family}")
    path = out / f"ipv{family}.bin"
    path.write_bytes(b"".join(low.to_bytes(size, "big") + high.to_bytes(size, "big") + status for low, high, status in records))
    print(f"{path.name}: {len(records)} ranges / {path.stat().st_size} bytes")
digest = hashlib.sha256(args.csv_gz.read_bytes()).hexdigest()
(out / "SOURCE.md").write_text(f"""# Landing geolocation data

The bundled indexes are derived from [DB-IP Country Lite {args.release}](https://db-ip.com/db/lite.php).
Source: https://download.db-ip.com/free/dbip-country-lite-{args.release}.csv.gz
Source SHA-256: {digest}

IP geolocation data by [DB-IP.com](https://db-ip.com), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
GNOSIX reduces country labels to Romania, foreign or unknown and merges adjacent ranges with the same classification.
""", encoding="utf-8")
