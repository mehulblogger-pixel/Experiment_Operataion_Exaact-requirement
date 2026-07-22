from django.contrib import admin

from .models import (
    BusinessPartner,
    PartnerAddress,
    PartnerContact,
    PartnerContract,
    PartnerNote,
    PartnerPurchaseOrder,
    PartnerRegistration,
    PartnerRelationship,
    PurchaseOrderLineItem,
)


class AddressInline(admin.TabularInline):
    model = PartnerAddress
    extra = 0


class ContactInline(admin.TabularInline):
    model = PartnerContact
    extra = 0


class RegistrationInline(admin.TabularInline):
    model = PartnerRegistration
    extra = 0


class NoteInline(admin.TabularInline):
    model = PartnerNote
    extra = 0
    readonly_fields = ("author", "created_at")


@admin.register(BusinessPartner)
class BusinessPartnerAdmin(admin.ModelAdmin):
    list_display = (
        "code", "legal_name", "roles_label", "client_type", "industry",
        "status", "gstin", "is_active",
    )
    list_filter = (
        "is_client", "is_vendor", "is_subcontractor", "status", "industry",
        "ownership_type", "registering_branch",
    )
    search_fields = ("code", "legal_name", "display_name", "gstin", "pan", "cin")
    readonly_fields = ("code", "pan", "state", "created_at", "updated_at")
    autocomplete_fields = ("parent",)
    inlines = [AddressInline, ContactInline, RegistrationInline, NoteInline]
    fieldsets = (
        ("Identity", {"fields": ("code", "registering_branch", "legal_name",
                                 "display_name", "parent")}),
        ("Roles", {"fields": ("is_client", "is_vendor", "is_subcontractor",
                              "client_type")}),
        ("Classification", {"fields": ("industry", "ownership_type", "status",
                                       "default_sbu")}),
        ("Statutory", {"fields": ("gstin", "pan", "cin", "tan", "msme_udyam",
                                  "state")}),
        ("Other", {"fields": ("website", "description", "is_active")}),
    )


@admin.register(PartnerRelationship)
class PartnerRelationshipAdmin(admin.ModelAdmin):
    list_display = ("partner", "relation_type", "related")
    autocomplete_fields = ("partner", "related")


@admin.register(PartnerContract)
class PartnerContractAdmin(admin.ModelAdmin):
    list_display = ("contract_number", "partner", "value", "start_date", "end_date", "is_active")
    search_fields = ("contract_number", "partner__legal_name")
    autocomplete_fields = ("partner",)


class LineItemInline(admin.TabularInline):
    model = PurchaseOrderLineItem
    extra = 1


@admin.register(PartnerPurchaseOrder)
class PartnerPurchaseOrderAdmin(admin.ModelAdmin):
    list_display = ("po_number", "partner", "po_type", "value", "start_date", "is_active")
    list_filter = ("po_type", "is_active")
    search_fields = ("po_number", "partner__legal_name")
    autocomplete_fields = ("partner", "contract")
    inlines = [LineItemInline]
