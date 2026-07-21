from django.contrib import admin

from .models import (
    ActivityCode,
    Client,
    Inspector,
    JobType,
    Office,
    PaymentTerm,
    ProductCategory,
    ReportFormat,
    SBU,
    SubActivityCode,
    Vendor,
    VendorLocation,
)


class ActiveAdmin(admin.ModelAdmin):
    """Shared defaults: expose the is_active toggle and quick filters."""

    list_filter = ("is_active",)
    list_editable = ("is_active",)


@admin.register(Office)
class OfficeAdmin(ActiveAdmin):
    list_display = ("code", "name", "city", "is_head_office", "is_active")
    search_fields = ("code", "name", "city")


@admin.register(SBU)
class SBUAdmin(ActiveAdmin):
    list_display = ("code", "name", "is_active")
    search_fields = ("code", "name")


class SubActivityInline(admin.TabularInline):
    model = SubActivityCode
    extra = 1


@admin.register(ActivityCode)
class ActivityCodeAdmin(ActiveAdmin):
    list_display = ("code", "description", "sbu", "is_active")
    list_filter = ("sbu", "is_active")
    search_fields = ("code", "description")
    inlines = [SubActivityInline]


@admin.register(ProductCategory)
class ProductCategoryAdmin(ActiveAdmin):
    list_display = ("name", "is_active")
    search_fields = ("name",)


@admin.register(VendorLocation)
class VendorLocationAdmin(ActiveAdmin):
    list_display = ("name", "state", "is_active")
    search_fields = ("name", "state")


@admin.register(JobType)
class JobTypeAdmin(ActiveAdmin):
    list_display = ("__str__", "parent", "allow_multiple_selection", "is_active")
    list_filter = ("parent", "is_active")
    search_fields = ("name",)


@admin.register(ReportFormat)
class ReportFormatAdmin(ActiveAdmin):
    list_display = ("name", "is_active")
    search_fields = ("name",)


@admin.register(PaymentTerm)
class PaymentTermAdmin(ActiveAdmin):
    list_display = ("name", "is_advance", "is_active")
    list_editable = ("is_advance", "is_active")


@admin.register(Client)
class ClientAdmin(ActiveAdmin):
    list_display = ("name", "default_sbu", "contact_person", "contact_email", "is_active")
    search_fields = ("name", "contact_person", "contact_email")
    list_filter = ("default_sbu", "is_active")


@admin.register(Vendor)
class VendorAdmin(ActiveAdmin):
    list_display = ("name", "location", "contact_person", "contact_mobile", "is_active")
    search_fields = ("name", "contact_person")
    list_filter = ("location", "is_active")


@admin.register(Inspector)
class InspectorAdmin(ActiveAdmin):
    list_display = (
        "name",
        "inspector_type",
        "discipline",
        "email",
        "home_office",
        "is_active",
    )
    list_filter = ("inspector_type", "discipline", "home_office", "is_active")
    search_fields = ("name", "email")
    filter_horizontal = ("competencies",)
