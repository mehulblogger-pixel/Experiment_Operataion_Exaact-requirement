from django import forms
from django.utils import timezone

from masters.models import Inspector
from partners.models import (
    BusinessPartner,
    PartnerAddress,
    PartnerContact,
    PartnerContract,
    PartnerPurchaseOrder,
)
from .models import InspectionCall, ScheduleAssignment


class LinkedSelect(forms.Select):
    """A <select> whose options carry a data-partner attribute so the browser can
    show only the entries belonging to the chosen client/vendor (see
    dependent-select.js). Falls back to a normal select if JS is off."""

    def __init__(self, *args, depends="", data_map=None, **kwargs):
        self.depends = depends
        self.data_map = data_map or {}
        super().__init__(*args, **kwargs)

    def build_attrs(self, base, extra=None):
        attrs = super().build_attrs(base, extra)
        if self.depends:
            attrs["data-depends"] = self.depends
            attrs["data-no-search"] = "1"
        return attrs

    def create_option(self, name, value, label, selected, index, subindex=None, attrs=None):
        opt = super().create_option(name, value, label, selected, index, subindex, attrs)
        pk = getattr(value, "value", value)
        partner = self.data_map.get(pk) or self.data_map.get(str(pk))
        if partner:
            opt["attrs"]["data-partner"] = str(partner)
        return opt


class InspectionCallForm(forms.ModelForm):
    """Call Register data-entry form. Picking a client/vendor makes its contacts,
    site addresses, contracts and POs available (requirement 5, 7)."""

    class Meta:
        model = InspectionCall
        fields = [
            "client",
            "client_contact",
            "quotation_number",
            "contract_number",
            "contract",
            "purchase_order",
            "job_type",
            "engagement_type",
            "sbu",
            "activity_code",
            "sub_activity_code",
            "product_category",
            "contracting_office",
            "executing_office",
            "location_type",
            "vendor",
            "vendor_contact",
            "site_address",
            "vendor_location",
            "inspection_address",
            "client_contact_person",
            "client_contact_mobile",
            "client_contact_email",
            "vendor_contact_person",
            "vendor_contact_mobile",
            "vendor_contact_email",
            "payment_term",
            "is_advance_payment",
            "deliverable_against_payment",
            "date_call_received",
            "date_inspection_required",
            "reporting_required",
            "reporting_frequency",
            "custom_reporting_frequency",
            "report_formats",
            "sharepoint_link",
            "revenue",
            "credit_to_executing_office",
            "credit_amount",
        ]
        widgets = {
            "date_call_received": forms.DateInput(attrs={"type": "date"}),
            "date_inspection_required": forms.DateInput(attrs={"type": "date"}),
            "inspection_address": forms.Textarea(attrs={"rows": 2}),
            "report_formats": forms.CheckboxSelectMultiple,
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)

        # Role-scoped partner dropdowns.
        self.fields["client"].queryset = BusinessPartner.objects.filter(
            is_client=True, is_active=True
        )
        self.fields["vendor"].queryset = BusinessPartner.objects.filter(
            is_vendor=True, is_active=True
        )

        # Dependent dropdowns filtered by the chosen client / vendor.
        contact_map = {c.pk: c.partner_id for c in PartnerContact.objects.all()}
        addr_map = {a.pk: a.partner_id for a in PartnerAddress.objects.all()}
        contract_map = {c.pk: c.partner_id for c in PartnerContract.objects.all()}
        po_map = {p.pk: p.partner_id for p in PartnerPurchaseOrder.objects.all()}

        self.fields["client_contact"].widget = LinkedSelect(
            depends="client", data_map=contact_map,
            choices=self.fields["client_contact"].widget.choices,
        )
        self.fields["vendor_contact"].widget = LinkedSelect(
            depends="vendor", data_map=contact_map,
            choices=self.fields["vendor_contact"].widget.choices,
        )
        self.fields["site_address"].widget = LinkedSelect(
            depends="vendor", data_map=addr_map,
            choices=self.fields["site_address"].widget.choices,
        )
        self.fields["contract"].widget = LinkedSelect(
            depends="client", data_map=contract_map,
            choices=self.fields["contract"].widget.choices,
        )
        self.fields["purchase_order"].widget = LinkedSelect(
            depends="client", data_map=po_map,
            choices=self.fields["purchase_order"].widget.choices,
        )

        for name, field in self.fields.items():
            if isinstance(field.widget, (forms.CheckboxInput, forms.CheckboxSelectMultiple)):
                continue
            field.widget.attrs.setdefault("class", "form-control")

        for fname in ("vendor_location", "payment_term", "product_category",
                      "activity_code", "sub_activity_code"):
            if fname in self.fields and hasattr(self.fields[fname].queryset, "filter"):
                self.fields[fname].queryset = self.fields[fname].queryset.filter(
                    is_active=True
                )

    def clean(self):
        cleaned = super().clean()
        received = cleaned.get("date_call_received")
        required = cleaned.get("date_inspection_required")
        if received and required and required < received:
            self.add_error(
                "date_inspection_required",
                "Inspection required date cannot be before the call received date.",
            )
        return cleaned

    def save(self, commit=True):
        """Auto-fill the contact snapshots from the chosen partner contacts so
        history is preserved even if the master later changes."""
        call = super().save(commit=False)
        cc = self.cleaned_data.get("client_contact") or (
            call.client.primary_contact if call.client_id else None
        )
        if cc:
            call.client_contact_person = call.client_contact_person or cc.name
            call.client_contact_mobile = call.client_contact_mobile or cc.mobile
            call.client_contact_email = call.client_contact_email or cc.email
        vc = self.cleaned_data.get("vendor_contact")
        if vc:
            call.vendor_contact_person = call.vendor_contact_person or vc.name
            call.vendor_contact_mobile = call.vendor_contact_mobile or vc.mobile
            call.vendor_contact_email = call.vendor_contact_email or vc.email
        sa = self.cleaned_data.get("site_address")
        if sa and not call.inspection_address:
            call.inspection_address = sa.one_line
        if commit:
            call.save()
            self.save_m2m()
        return call


class AllocateInspectorForm(forms.Form):
    """Schedule Register allocation (requirement 6c)."""

    inspector = forms.ModelChoiceField(
        queryset=Inspector.objects.filter(is_active=True),
        widget=forms.Select(attrs={"class": "form-control"}),
    )
    inspector_kind = forms.ChoiceField(
        choices=ScheduleAssignment._meta.get_field("inspector_kind").choices,
        widget=forms.Select(attrs={"class": "form-control"}),
    )
    scheduled_date = forms.DateField(
        initial=timezone.localdate,
        widget=forms.DateInput(attrs={"type": "date", "class": "form-control"}),
    )
    is_tentative = forms.BooleanField(required=False, label="Tentative allocation")
    notes = forms.CharField(
        required=False,
        widget=forms.Textarea(attrs={"rows": 2, "class": "form-control"}),
    )


class RejectCallForm(forms.Form):
    reason = forms.CharField(
        widget=forms.Textarea(attrs={"rows": 3, "class": "form-control"}),
        help_text="Recorded permanently and reflected in branch-wise reports.",
    )


class DeliverableSubmitForm(forms.Form):
    document = forms.FileField(
        required=False,
        widget=forms.ClearableFileInput(attrs={"class": "form-control"}),
        help_text="Upload the report file (optional).",
    )
    uploaded_to_sharepoint = forms.BooleanField(
        required=False, label="Also uploaded to SharePoint"
    )
    notes = forms.CharField(
        required=False,
        widget=forms.Textarea(attrs={"rows": 2, "class": "form-control"}),
    )
