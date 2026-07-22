"""Migrate existing masters.Client / masters.Vendor rows into BusinessPartner.

Clients and vendors sharing a normalized name merge into one partner tagged with
both roles. Safe/no-op on a fresh database with no client/vendor rows."""

import re

from django.db import migrations


def _norm(name):
    n = (name or "").lower()
    n = re.sub(r"^m/?s\.?\s+", "", n)
    n = re.sub(r"[^a-z0-9 ]", " ", n)
    n = re.sub(r"\b(private|pvt|limited|ltd|llp|company|co)\b", " ", n)
    return re.sub(r"\s+", " ", n).strip()


def _token(name):
    first = (_norm(name).split() or ["PARTNER"])[0]
    return re.sub(r"[^A-Z0-9]", "", first.upper())[:8] or "PARTNER"


def _code(BP, name):
    prefix = f"GEN-{_token(name)}-"
    last = BP.objects.filter(code__startswith=prefix).order_by("code").last()
    seq = int(last.code.split("-")[-1]) + 1 if last else 1
    return f"{prefix}{seq:04d}"


def forwards(apps, schema_editor):
    Client = apps.get_model("masters", "Client")
    Vendor = apps.get_model("masters", "Vendor")
    BP = apps.get_model("partners", "BusinessPartner")
    Contact = apps.get_model("partners", "PartnerContact")

    by_norm = {}

    def get_or_create(name, sbu=None):
        key = _norm(name)
        if key in by_norm:
            return by_norm[key]
        bp = BP.objects.create(
            code=_code(BP, name), legal_name=name, default_sbu=sbu,
            status="ACTIVE", is_active=True,
        )
        by_norm[key] = bp
        return bp

    for c in Client.objects.all():
        bp = get_or_create(c.name, getattr(c, "default_sbu", None))
        bp.is_client = True
        bp.save()
        if getattr(c, "contact_person", "") or getattr(c, "contact_email", ""):
            Contact.objects.get_or_create(
                partner=bp, name=c.contact_person or c.name,
                defaults={"email": c.contact_email or "",
                          "mobile": c.contact_mobile or "", "is_primary": True},
            )

    for v in Vendor.objects.all():
        bp = get_or_create(v.name)
        bp.is_vendor = True
        bp.save()
        if getattr(v, "contact_person", "") or getattr(v, "contact_email", ""):
            Contact.objects.get_or_create(
                partner=bp, name=v.contact_person or v.name,
                defaults={"email": v.contact_email or "",
                          "mobile": v.contact_mobile or ""},
            )


def backwards(apps, schema_editor):
    apps.get_model("partners", "BusinessPartner").objects.all().delete()


class Migration(migrations.Migration):
    dependencies = [
        ("partners", "0001_initial"),
        ("masters", "0001_initial"),
    ]
    operations = [migrations.RunPython(forwards, backwards)]
