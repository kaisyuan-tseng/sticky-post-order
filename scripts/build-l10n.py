"""One-off helper: regenerate locale artifacts from the zh_CN PO file.

Usage: python build-l10n.py
Reads languages/sticky-order-zh_CN.po, writes:
  - languages/sticky-order.pot             (template, empty msgstr)
  - languages/sticky-order-zh_CN.mo        (compiled PHP/gettext catalog)

No Jed/.json catalog is produced: the plugin's admin.js has no user-facing
strings, so wp.i18n / wp_set_script_translations is not needed.
"""

import os
import struct
import re

HERE = os.path.dirname(os.path.abspath(__file__))
LANG_DIR = os.path.normpath(os.path.join(HERE, "..", "languages"))
PO_PATH = os.path.join(LANG_DIR, "sticky-order-zh_CN.po")

# Keep the POT header version in sync with the plugin header / readme stable tag.
PROJECT_NAME = "Sticky Post Order"
PROJECT_VERSION = "2.0.0"
DOMAIN = "sticky-order"
POT_PLURAL_FORMS = "nplurals=2; plural=(n != 1);"

ENTRY_RE = re.compile(
    r'(?:#\.[^\n]*\n)*'                     # extractor comments (skipped)
    r'(?:#: [^\n]*\n)*'                     # references (skipped)
    r'(?:msgid ""\n(?:(?:"[^\n]*"\n)+)\n?)' # header block handled separately
    r'|(?:msgid (?P<msgid>"(?:[^"\\]|\\.)*"\n(?:\s*"(?:[^"\\]|\\.)*"\n)*)\n?'
    r'msgstr (?P<msgstr>"(?:[^"\\]|\\.)*"\n(?:\s*"(?:[^"\\]|\\.)*"\n)*))',
    re.M,
)

STRING_RE = re.compile(r'"((?:[^"\\]|\\.)*)"')


def unquote(block: str) -> str:
    parts = STRING_RE.findall(block)
    return "".join(p.encode("utf-8").decode("unicode_escape").encode("latin-1").decode("utf-8") for p in parts)


def parse_po(text: str):
    header = None
    entries = []
    for m in ENTRY_RE.finditer(text):
        msgid = unquote(m.group("msgid") or "")
        msgstr = unquote(m.group("msgstr") or "")
        if msgid == "" and header is None:
            header = msgstr
            continue
        entries.append((msgid, msgstr))
    return header, entries


def header_value(header: str, key: str, default: str = "") -> str:
    """Read a single field from a PO header block."""
    m = re.search(r"^" + re.escape(key) + r":\s*(.*)$", header or "", re.M)
    return m.group(1).strip() if m else default


def po_quote(value: str) -> str:
    """Escape a value for use inside a PO double-quoted string."""
    return value.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def write_pot(header: str, entries) -> None:
    out = [
        'msgid ""\nmsgstr ""\n'
        f'"Project-Id-Version: {PROJECT_NAME} {PROJECT_VERSION}\\n"\n'
        '"MIME-Version: 1.0\\n"\n'
        '"Content-Type: text/plain; charset=UTF-8\\n"\n'
        '"Content-Transfer-Encoding: 8bit\\n"\n'
        f'"Plural-Forms: {POT_PLURAL_FORMS}\\n"\n'
        '"X-Generator: manual\\n"\n'
    ]
    for msgid, _ in entries:
        out.append(f'\nmsgid "{po_quote(msgid)}"\nmsgstr ""\n')
    with open(os.path.join(LANG_DIR, f"{DOMAIN}.pot"), "w", encoding="utf-8") as fh:
        fh.write("".join(out))


def write_mo(header: str, entries) -> None:
    # MO format: magic, version, N, O, S, T, then tables of (len, offset) pairs.
    keys, vals = [], []
    # Header entry (msgid "") carries the charset declaration; gettext relies on it.
    keys.append(b"")
    # Take version and plural rules from the PO header so zh_CN keeps its own
    # (correct) nplurals=1 instead of inheriting the English template's rules.
    version = header_value(header, "Project-Id-Version", f"{PROJECT_NAME} {PROJECT_VERSION}")
    plural = header_value(header, "Plural-Forms", "nplurals=1; plural=0;")
    vals.append(
        (
            f"Project-Id-Version: {version}\n"
            "MIME-Version: 1.0\n"
            "Content-Type: text/plain; charset=UTF-8\n"
            "Content-Transfer-Encoding: 8bit\n"
            f"Plural-Forms: {plural}\n"
        ).encode("utf-8")
    )
    for msgid, msgstr in entries:
        keys.append(msgid.encode("utf-8"))
        vals.append(msgstr.encode("utf-8"))

    n = len(keys)
    keystart = 7 * 4 + 16 * n
    valuestart = keystart + sum(len(k) + 1 for k in keys)

    koffsets, voffsets = [], []
    kdata, vdata = b"", b""
    offset = keystart
    for k in keys:
        koffsets.append((len(k), offset))
        kdata += k + b"\x00"
        offset += len(k) + 1
    offset = valuestart
    for v in vals:
        voffsets.append((len(v), offset))
        vdata += v + b"\x00"
        offset += len(v) + 1

    output = struct.pack("Iiiiiii", 0x950412DE, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    for length, off in koffsets:
        output += struct.pack("ii", length, off)
    for length, off in voffsets:
        output += struct.pack("ii", length, off)
    output += kdata + vdata

    with open(os.path.join(LANG_DIR, f"{DOMAIN}-zh_CN.mo"), "wb") as fh:
        fh.write(output)


def main() -> None:
    with open(PO_PATH, encoding="utf-8") as fh:
        text = fh.read()
    header, entries = parse_po(text)
    print(f"parsed {len(entries)} entries; header={'ok' if header else 'missing'}")
    write_pot(header, entries)
    write_mo(header, entries)
    print(f"wrote {DOMAIN}.pot and {DOMAIN}-zh_CN.mo")


if __name__ == "__main__":
    main()
