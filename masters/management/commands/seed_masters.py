"""Seed master data.

Loads offices, SBUs, activity codes, product categories, vendor locations,
clients and inspectors extracted from the 'Final Dashboard' workbook
(masters/seed_data.json), plus the standard job-type tree, report formats and
payment terms described in the requirements. Idempotent — safe to re-run.

    python manage.py seed_masters
"""

import json
from pathlib import Path

from django.core.management.base import BaseCommand
from django.db import transaction

from masters.models import (
    ActivityCode,
    Client,
    Inspector,
    InspectorType,
    JobType,
    Office,
    PaymentTerm,
    ProductCategory,
    ReportFormat,
    SBU,
    VendorLocation,
)

SEED_FILE = Path(__file__).resolve().parents[2] / "seed_data.json"

# Requirement 18 & 25: configurable job-type tree (parent -> sub-categories).
JOB_TYPES = {
    "Inspection": [
        "Product Inspection",
        "Expediting Visit",
        "Tender Document Review",
        "Document Review",
        "Vendor Assessment",
        "Vendor Audit",
    ],
    "Project Deputation": ["Site QA/QC", "Commissioning", "O&M", "Erection"],
    "Supply Chain Deputation": [],
    "Site Supervision": [],
    "Commissioning & Installation": [],
    "Technical Audit": [],
    "Type Test": [],
    "Vendor Assessment": [],
    "Vendor Audit": [],
    "Document Review": [],
    "Tender Review": [],
}

# Requirement 5w: deliverable report formats (multi-selectable).
REPORT_FORMATS = [
    "Flash Report",
    "Inspection Report",
    "Release Note",
    "Expediting Report",
    "Audit Report",
    "Inspection Certificate",
]

PAYMENT_TERMS = [
    ("Advance", True),
    ("Against Delivery of Report", False),
    ("Credit 30 Days", False),
    ("Credit 45 Days", False),
    ("ARC / Open Order", False),
]


class Command(BaseCommand):
    help = "Seed master/reference data from the dashboard export + requirement defaults."

    @transaction.atomic
    def handle(self, *args, **options):
        data = json.loads(SEED_FILE.read_text(encoding="utf-8"))

        # SBUs
        for code, name in data.get("sbus", {}).items():
            SBU.objects.update_or_create(code=code, defaults={"name": name})

        # Offices
        for code in data.get("offices", []):
            Office.objects.get_or_create(
                code=code,
                defaults={
                    "name": code.replace("_", " ").title(),
                    "is_head_office": code == "AHMEDABAD",
                },
            )

        # Activity codes (mapped to SBU where known)
        for code, sbu_code in data.get("activities", {}).items():
            sbu = SBU.objects.filter(code=sbu_code).first()
            ActivityCode.objects.get_or_create(code=code, sbu=sbu)

        # Product categories
        for name in data.get("product_categories", []):
            ProductCategory.objects.get_or_create(name=name[:200])

        # Vendor locations
        for name in data.get("vendor_locations", []):
            VendorLocation.objects.get_or_create(name=name[:120])

        # Clients
        for name in data.get("clients", []):
            Client.objects.get_or_create(name=name[:255])

        # Inspectors
        type_map = {
            "Employee": InspectorType.EMPLOYEE,
            "Subcon": InspectorType.SUBCON,
            "Expert": InspectorType.EXPERT,
        }
        for row in data.get("inspectors", []):
            Inspector.objects.get_or_create(
                name=row["name"][:160],
                defaults={
                    "inspector_type": type_map.get(
                        row.get("type"), InspectorType.EMPLOYEE
                    ),
                    "discipline": row.get("discipline", "")[:80],
                },
            )

        # Job types
        for parent_name, children in JOB_TYPES.items():
            parent, _ = JobType.objects.get_or_create(name=parent_name, parent=None)
            if children:
                parent.allow_multiple_selection = True
                parent.save(update_fields=["allow_multiple_selection"])
            for child in children:
                JobType.objects.get_or_create(name=child, parent=parent)

        # Report formats
        for name in REPORT_FORMATS:
            ReportFormat.objects.get_or_create(name=name)

        # Payment terms
        for name, is_adv in PAYMENT_TERMS:
            PaymentTerm.objects.update_or_create(
                name=name, defaults={"is_advance": is_adv}
            )

        self.stdout.write(
            self.style.SUCCESS(
                "Seeded: {} offices, {} SBUs, {} activities, {} product categories, "
                "{} vendor locations, {} clients, {} inspectors, {} job types.".format(
                    Office.objects.count(),
                    SBU.objects.count(),
                    ActivityCode.objects.count(),
                    ProductCategory.objects.count(),
                    VendorLocation.objects.count(),
                    Client.objects.count(),
                    Inspector.objects.count(),
                    JobType.objects.count(),
                )
            )
        )
