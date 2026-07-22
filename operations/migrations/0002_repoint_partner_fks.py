"""Re-point InspectionCall.client / .vendor from masters.Client/Vendor to the
unified partners.BusinessPartner (remapping existing rows by name), and add the
partner-linked fields (client_contact, vendor_contact, site_address, contract,
purchase_order)."""

import re

import django.db.models.deletion
from django.db import migrations, models


def _norm(name):
    n = (name or "").lower()
    n = re.sub(r"^m/?s\.?\s+", "", n)
    n = re.sub(r"[^a-z0-9 ]", " ", n)
    n = re.sub(r"\b(private|pvt|limited|ltd|llp|company|co)\b", " ", n)
    return re.sub(r"\s+", " ", n).strip()


def snapshot_names(apps, schema_editor):
    Call = apps.get_model("operations", "InspectionCall")
    for call in Call.objects.all():
        Call.objects.filter(pk=call.pk).update(
            client_ref_name=call.client.name if call.client_id else "",
            vendor_ref_name=call.vendor.name if call.vendor_id else "",
        )


def remap(apps, schema_editor):
    Call = apps.get_model("operations", "InspectionCall")
    BP = apps.get_model("partners", "BusinessPartner")
    lookup = {_norm(bp.legal_name): bp.id for bp in BP.objects.all()}
    for call in Call.objects.all():
        updates = {}
        if call.client_ref_name:
            bid = lookup.get(_norm(call.client_ref_name))
            if bid:
                updates["client_tmp_id"] = bid
        if call.vendor_ref_name:
            bid = lookup.get(_norm(call.vendor_ref_name))
            if bid:
                updates["vendor_tmp_id"] = bid
        if updates:
            Call.objects.filter(pk=call.pk).update(**updates)


def noop(apps, schema_editor):
    pass


class Migration(migrations.Migration):
    dependencies = [
        ("operations", "0001_initial"),
        ("partners", "0003_partnercontract_partnerpurchaseorder_and_more"),
    ]

    operations = [
        migrations.AddField(
            model_name="inspectioncall", name="client_ref_name",
            field=models.CharField(blank=True, default="", max_length=255),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="vendor_ref_name",
            field=models.CharField(blank=True, default="", max_length=255),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="client_tmp",
            field=models.ForeignKey(
                null=True, on_delete=django.db.models.deletion.PROTECT,
                related_name="client_calls_tmp", to="partners.businesspartner"),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="vendor_tmp",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.PROTECT,
                related_name="vendor_calls_tmp", to="partners.businesspartner"),
        ),
        migrations.RunPython(snapshot_names, noop),
        migrations.RunPython(remap, noop),
        migrations.RemoveField(model_name="inspectioncall", name="client"),
        migrations.RemoveField(model_name="inspectioncall", name="vendor"),
        migrations.RenameField(
            model_name="inspectioncall", old_name="client_tmp", new_name="client"),
        migrations.RenameField(
            model_name="inspectioncall", old_name="vendor_tmp", new_name="vendor"),
        migrations.AlterField(
            model_name="inspectioncall", name="client",
            field=models.ForeignKey(
                null=True, on_delete=django.db.models.deletion.PROTECT,
                related_name="client_calls",
                limit_choices_to={"is_client": True},
                to="partners.businesspartner"),
        ),
        migrations.AlterField(
            model_name="inspectioncall", name="vendor",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.PROTECT,
                related_name="vendor_calls",
                limit_choices_to={"is_vendor": True},
                to="partners.businesspartner"),
        ),
        migrations.RemoveField(model_name="inspectioncall", name="client_ref_name"),
        migrations.RemoveField(model_name="inspectioncall", name="vendor_ref_name"),
        # New partner-linked fields.
        migrations.AddField(
            model_name="inspectioncall", name="client_contact",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.SET_NULL,
                related_name="client_calls", to="partners.partnercontact",
                help_text="Contact person at the client for this call."),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="vendor_contact",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.SET_NULL,
                related_name="vendor_calls", to="partners.partnercontact",
                help_text="Contact person at the vendor / manufacturer."),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="site_address",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.SET_NULL,
                related_name="site_calls", to="partners.partneraddress",
                help_text="Inspection site (a plant/factory/site address of the vendor)."),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="contract",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.SET_NULL,
                related_name="calls", to="partners.partnercontract"),
        ),
        migrations.AddField(
            model_name="inspectioncall", name="purchase_order",
            field=models.ForeignKey(
                null=True, blank=True, on_delete=django.db.models.deletion.SET_NULL,
                related_name="calls", to="partners.partnerpurchaseorder"),
        ),
    ]
