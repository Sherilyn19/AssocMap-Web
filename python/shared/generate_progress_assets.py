"""Generate a reusable wave asset without requiring third-party packages."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
# Store generated source assets where Vite can bundle and version them.
DESTINATION = ROOT / "resources" / "images" / "shared" / "production-wave.svg"

# This contains geometry only. Database values remain server-authorized.
SVG = """<svg xmlns="http://www.w3.org/2000/svg"
viewBox="0 0 240 12" width="240" height="12">
<path fill="#176c73"
d="M0 6 Q30 0 60 6 T120 6 T180 6 T240 6 V12 H0 Z"/>
</svg>
"""


def main() -> None:
    DESTINATION.parent.mkdir(parents=True, exist_ok=True)
    DESTINATION.write_text(SVG, encoding="utf-8")
    print(f"Generated: {DESTINATION}")


if __name__ == "__main__":
    main()