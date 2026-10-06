#!/usr/bin/env python3

from pathlib import Path
import shutil
import sys

ROOT = Path(".")
WIZARD = ROOT / "brite_complete_wizard.py"

SYNC_FILES = [
    "app/bootstrap.php",
    "app/Auth.php",

    "api/auth/login.php",
    "api/auth/logout.php",
    "api/auth/register.php",

    "api/clients.php",
    "api/contractors.php",
    "api/services.php",
    "api/appointments.php",
    "api/contractor_availability.php",
    "api/invoices.php",
    "api/payments.php",
    "api/dashboard.php",
    "api/business_profile.php",

    "partials/app_header.php",
    "partials/app_footer.php",

    "dashboard.v2.php",
    "clients.v2.php",
    "contractors.v2.php",
    "services.v2.php",

    "contractor-availability.php",
    "my-availability.php",

    "scheduling.php",
    "billing.php",
    "invoice.php",
    "settings.php",

    "sign-in.v2.php",
    "sign-up.v2.php",

    "client-dashboard.php",
    "contractor-dashboard.php",

    "migrations/003_invoice_appointment_unique.sql",
    "migrations/004_tenant_business_profile.sql",
]

START = "# BEGIN LIVE-SYNC OVERRIDES"
END   = "# END LIVE-SYNC OVERRIDES"


def fail(message):
    print("ERROR:", message, file=sys.stderr)
    raise SystemExit(1)


if not WIZARD.is_file():
    fail("brite_complete_wizard.py not found")


for rel in SYNC_FILES:
    if not (ROOT / rel).is_file():
        fail(f"missing live file: {rel}")


wizard = WIZARD.read_text(encoding="utf-8")


# Remove a previous generated override section, making this script
# safe to run repeatedly.
if START in wizard:

    start = wizard.index(START)

    if END not in wizard[start:]:
        fail("existing LIVE-SYNC section has no end marker")

    end = wizard.index(END, start) + len(END)

    # Consume trailing newlines too.
    while end < len(wizard) and wizard[end] == "\n":
        end += 1

    wizard = wizard[:start] + wizard[end:]


marker = "\ndef main("

pos = wizard.find(marker)

if pos == -1:
    fail("def main() not found")


def python_string(value):
    # repr() gives us a valid Python string literal and safely handles
    # quotes, PHP syntax, JavaScript, newlines and backslashes.
    return repr(value)


section = "\n"
section += START + "\n"
section += "# Generated from the tested live application.\n"
section += "# Later assignments intentionally override older templates above.\n\n"


for rel in SYNC_FILES:

    content = (ROOT / rel).read_text(
        encoding="utf-8"
    )

    section += (
        f"FILES[{rel!r}] = "
        f"{python_string(content)}\n\n"
    )

    print("SYNC:", rel)


section += END + "\n"


wizard = (
    wizard[:pos]
    + section
    + wizard[pos:]
)


WIZARD.write_text(
    wizard,
    encoding="utf-8"
)


print()
print(
    f"Installed {len(SYNC_FILES)} "
    "live template overrides."
)
