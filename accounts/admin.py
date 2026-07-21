from django.contrib import admin
from django.contrib.auth.admin import UserAdmin as DjangoUserAdmin

from .models import User


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
        ("Operations profile", {"fields": ("role", "home_office", "sbu", "phone")}),
    )
    add_fieldsets = DjangoUserAdmin.add_fieldsets + (
        ("Operations profile", {"fields": ("role", "home_office", "sbu", "phone")}),
    )
