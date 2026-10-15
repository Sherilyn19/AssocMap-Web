"""Calculate chart proportions from authorized aggregate counts.

Laravel supplies counts only. This script does not access the database,
receive member information, or write files.
"""

import json
import sys


def build_charts(series):
    charts = {}

    for name, rows in series.items():
        clean_rows = []

        for row in rows:
            count = int(row["count"])

            if count < 0:
                raise ValueError("Counts cannot be negative.")

            clean_rows.append({
                "label": str(row["label"]),
                "count": count,
            })

        total = sum(row["count"] for row in clean_rows)

        charts[name] = [
            {
                **row,
                # Empty groups remain empty; they are not treated as success.
                "percent": round(row["count"] * 100 / total, 1) if total else 0,
            }
            for row in clean_rows
        ]

    return charts


def main():
    payload = json.load(sys.stdin)
    json.dump(build_charts(payload), sys.stdout, ensure_ascii=True)


if __name__ == "__main__":
    main()