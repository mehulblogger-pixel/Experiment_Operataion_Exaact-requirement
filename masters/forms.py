from django import forms

from .models import Client, Vendor


class ClientForm(forms.ModelForm):
    class Meta:
        model = Client
        fields = [
            "name",
            "default_sbu",
            "contact_person",
            "contact_email",
            "contact_mobile",
            "is_active",
        ]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        for name, field in self.fields.items():
            if isinstance(field.widget, forms.CheckboxInput):
                continue
            field.widget.attrs.setdefault("class", "form-control")


class VendorForm(forms.ModelForm):
    class Meta:
        model = Vendor
        fields = [
            "name",
            "location",
            "contact_person",
            "contact_email",
            "contact_mobile",
            "is_active",
        ]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        for name, field in self.fields.items():
            if isinstance(field.widget, forms.CheckboxInput):
                continue
            field.widget.attrs.setdefault("class", "form-control")
