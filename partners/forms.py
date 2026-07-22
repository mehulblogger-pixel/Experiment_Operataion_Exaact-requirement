from django import forms

from masters.models import Office, SBU
from .models import (
    BusinessPartner,
    PartnerAddress,
    PartnerContact,
    PartnerContract,
    PartnerNote,
    PartnerPurchaseOrder,
    PartnerRegistration,
    PurchaseOrderLineItem,
)
from .utils import is_valid_gstin


class _Styled(forms.ModelForm):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        for name, field in self.fields.items():
            if isinstance(field.widget, forms.CheckboxInput):
                continue
            field.widget.attrs.setdefault("class", "form-control")


class BusinessPartnerForm(_Styled):
    class Meta:
        model = BusinessPartner
        fields = [
            "registering_branch", "legal_name", "display_name",
            "is_client", "is_vendor", "is_subcontractor",
            "client_type", "industry", "ownership_type", "status",
            "default_sbu", "parent",
            "gstin", "cin", "tan", "msme_udyam",
            "website", "description",
        ]
        widgets = {"description": forms.Textarea(attrs={"rows": 2})}

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["registering_branch"].queryset = Office.objects.filter(is_active=True)
        self.fields["default_sbu"].queryset = SBU.objects.filter(is_active=True)
        qs = BusinessPartner.objects.all()
        if self.instance and self.instance.pk:
            qs = qs.exclude(pk=self.instance.pk)
        self.fields["parent"].queryset = qs

    def clean_gstin(self):
        g = (self.cleaned_data.get("gstin") or "").strip().upper()
        if g and not is_valid_gstin(g):
            raise forms.ValidationError(
                "GSTIN should be 15 characters, e.g. 24ADUPL3517E2ZJ."
            )
        return g

    def clean(self):
        cleaned = super().clean()
        if not any([cleaned.get("is_client"), cleaned.get("is_vendor"),
                    cleaned.get("is_subcontractor")]):
            raise forms.ValidationError(
                "Select at least one role (Client, Vendor or Sub-contractor)."
            )
        return cleaned


class PartnerAddressForm(_Styled):
    class Meta:
        model = PartnerAddress
        fields = ["address_type", "label", "line1", "line2", "city", "state",
                  "pincode", "country", "is_primary"]


class PartnerContactForm(_Styled):
    class Meta:
        model = PartnerContact
        fields = ["name", "designation", "department", "email", "mobile",
                  "phone", "address", "is_primary"]

    def __init__(self, *args, partner=None, **kwargs):
        super().__init__(*args, **kwargs)
        if partner is not None:
            self.fields["address"].queryset = partner.addresses.all()
            self.fields["address"].required = False


class PartnerRegistrationForm(_Styled):
    class Meta:
        model = PartnerRegistration
        fields = ["doc_type", "number", "valid_from", "valid_to", "document", "notes"]
        widgets = {
            "valid_from": forms.DateInput(attrs={"type": "date"}),
            "valid_to": forms.DateInput(attrs={"type": "date"}),
        }


class PartnerNoteForm(_Styled):
    class Meta:
        model = PartnerNote
        fields = ["note"]
        widgets = {"note": forms.Textarea(attrs={"rows": 3})}


class PartnerContractForm(_Styled):
    class Meta:
        model = PartnerContract
        fields = ["contract_number", "title", "value", "start_date", "end_date",
                  "is_active", "notes"]
        widgets = {
            "start_date": forms.DateInput(attrs={"type": "date"}),
            "end_date": forms.DateInput(attrs={"type": "date"}),
        }


class PartnerPurchaseOrderForm(_Styled):
    class Meta:
        model = PartnerPurchaseOrder
        fields = ["po_number", "po_type", "title", "contract", "value",
                  "start_date", "end_date", "is_active", "notes"]
        widgets = {
            "start_date": forms.DateInput(attrs={"type": "date"}),
            "end_date": forms.DateInput(attrs={"type": "date"}),
        }

    def __init__(self, *args, partner=None, **kwargs):
        super().__init__(*args, **kwargs)
        if partner is not None:
            self.fields["contract"].queryset = partner.contracts.all()
            self.fields["contract"].required = False


class POLineItemForm(_Styled):
    class Meta:
        model = PurchaseOrderLineItem
        fields = ["description", "item_type", "quantity", "rate", "consumed"]
