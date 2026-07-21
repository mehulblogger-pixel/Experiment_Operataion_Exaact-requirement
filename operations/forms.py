from django import forms
from django.utils import timezone

from masters.models import Inspector
from .models import InspectionCall, ScheduleAssignment


class InspectionCallForm(forms.ModelForm):
    """Call Register data-entry form. All picklists are DB-backed dropdowns so
    the operator 'only enters the data' (requirement 7)."""

    class Meta:
        model = InspectionCall
        fields = [
            "client",
            "quotation_number",
            "contract_number",
            "job_type",
            "engagement_type",
            "sbu",
            "activity_code",
            "sub_activity_code",
            "product_category",
            "contracting_office",
            "executing_office",
            "location_type",
            "inspection_address",
            "vendor",
            "vendor_location",
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
        # Apply a consistent CSS hook and only offer active master records.
        for name, field in self.fields.items():
            if isinstance(field.widget, (forms.CheckboxInput, forms.CheckboxSelectMultiple)):
                continue
            field.widget.attrs.setdefault("class", "form-control")
        for fname in ("client", "vendor", "vendor_location", "payment_term",
                      "product_category", "activity_code", "sub_activity_code"):
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
