from django import forms

from masters.models import Office, SBU
from .models import Role, User


class _StyledMixin:
    def _style(self):
        for name, field in self.fields.items():
            if isinstance(field.widget, forms.CheckboxInput):
                continue
            field.widget.attrs.setdefault("class", "form-control")


class UserCreateForm(_StyledMixin, forms.ModelForm):
    password = forms.CharField(
        widget=forms.PasswordInput, min_length=6,
        help_text="At least 6 characters.",
    )

    class Meta:
        model = User
        fields = ["username", "first_name", "last_name", "email", "role",
                  "home_office", "sbu", "phone"]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["home_office"].queryset = Office.objects.filter(is_active=True)
        self.fields["sbu"].queryset = SBU.objects.filter(is_active=True)
        self.fields["home_office"].required = False
        self.fields["sbu"].required = False
        self._style()

    def save(self, commit=True):
        user = super().save(commit=False)
        user.set_password(self.cleaned_data["password"])
        user.is_active = True
        if commit:
            user.save()
        return user


class UserEditForm(_StyledMixin, forms.ModelForm):
    class Meta:
        model = User
        fields = ["first_name", "last_name", "email", "role",
                  "home_office", "sbu", "phone"]

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["home_office"].queryset = Office.objects.filter(is_active=True)
        self.fields["sbu"].queryset = SBU.objects.filter(is_active=True)
        self.fields["home_office"].required = False
        self.fields["sbu"].required = False
        self._style()


class SetPasswordForm(_StyledMixin, forms.Form):
    password = forms.CharField(
        widget=forms.PasswordInput, min_length=6, label="New password"
    )

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._style()
