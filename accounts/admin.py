from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as DjangoUserAdmin

from .models import LicenseConfig, User


@admin.register(LicenseConfig)
class LicenseConfigAdmin(admin.ModelAdmin):
    list_display = ("customer_name", "licensed_seats", "active_seats_used", "valid_until")
    readonly_fields = ("instance_key", "active_seats_used", "last_checked", "updated_at")

    def has_add_permission(self, request):
        # Singleton — only one row.
        return not LicenseConfig.objects.exists()

    def has_delete_permission(self, request, obj=None):
        return False


@admin.register(User)
class UserAdmin(DjangoUserAdmin):
    list_display = (
        "username",
        "get_full_name",
        "role",
        "home_office",
        "sbu",
        "is_active",
    )
    list_filter = ("role", "home_office", "sbu", "is_active", "is_staff")
    search_fields = ("username", "first_name", "last_name", "email")
    fieldsets = DjangoUserAdmin.fieldsets + (
        ("Operations profile", {"fields": ("role", "home_office", "sbu", "phone", "inspector_profile")}),
    )
    add_fieldsets = DjangoUserAdmin.add_fieldsets + (
        ("Operations profile", {"fields": ("role", "home_office", "sbu", "phone", "inspector_profile")}),
    )
