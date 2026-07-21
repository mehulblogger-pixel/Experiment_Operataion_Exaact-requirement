from django.contrib import admin

from .models import (
    CallEvent,
    InspectionCall,
    NetworkCredit,
    ReportDeliverable,
    ScheduleAssignment,
)


class ScheduleInline(admin.TabularInline):
    model = ScheduleAssignment
    extra = 0
    autocomplete_fields = ("inspector",)


class DeliverableInline(admin.TabularInline):
    model = ReportDeliverable
    extra = 0


class EventInline(admin.TabularInline):
    model = CallEvent
    extra = 0
    readonly_fields = ("kind", "note", "actor", "created_at")
    can_delete = False


@admin.register(InspectionCall)
class InspectionCallAdmin(admin.ModelAdmin):
    list_display = (
        "call_ref",
        "client",
        "job_type",
        "engagement_type",
        "contracting_office",
        "executing_office",
        "date_inspection_required",
        "status",
        "colour_status",
    )
    list_filter = (
        "status",
        "engagement_type",
        "executing_office",
        "contracting_office",
        "sbu",
        "is_advance_payment",
    )
    search_fields = ("call_ref", "client__name", "quotation_number", "contract_number")
    date_hierarchy = "date_inspection_required"
    autocomplete_fields = ("client", "vendor")
    filter_horizontal = ("report_formats",)
    readonly_fields = ("call_ref", "lead_time_days", "scheduling_delay_days")
    inlines = [ScheduleInline, DeliverableInline, EventInline]
    fieldsets = (
        ("Reference", {"fields": ("call_ref", "status")}),
        (
            "Commercial",
            {
                "fields": (
                    "client",
                    "quotation_number",
                    "contract_number",
                    "revenue",
                    "credit_to_executing_office",
                    "credit_amount",
                )
            },
        ),
        (
            "Job classification",
            {
                "fields": (
                    "job_type",
                    "engagement_type",
                    "sbu",
                    "activity_code",
                    "sub_activity_code",
                    "product_category",
                )
            },
        ),
        (
            "Offices",
            {"fields": ("contracting_office", "executing_office")},
        ),
        (
            "Location",
            {
                "fields": (
                    "location_type",
                    "inspection_address",
                    "vendor",
                    "vendor_location",
                )
            },
        ),
        (
            "Client contact",
            {
                "fields": (
                    "client_contact_person",
                    "client_contact_mobile",
                    "client_contact_email",
                )
            },
        ),
        (
            "Vendor contact",
            {
                "fields": (
                    "vendor_contact_person",
                    "vendor_contact_mobile",
                    "vendor_contact_email",
                )
            },
        ),
        (
            "Payment",
            {
                "fields": (
                    "payment_term",
                    "is_advance_payment",
                    "deliverable_against_payment",
                )
            },
        ),
        (
            "Dates",
            {
                "fields": (
                    "date_call_received",
                    "date_inspection_required",
                    "date_call_allocated",
                    "lead_time_days",
                    "scheduling_delay_days",
                )
            },
        ),
        (
            "Reporting",
            {
                "fields": (
                    "reporting_required",
                    "reporting_frequency",
                    "custom_reporting_frequency",
                    "report_formats",
                    "sharepoint_link",
                )
            },
        ),
        (
            "Lifecycle",
            {
                "fields": (
                    "rejection_reason",
                    "arranged_by_subcontractor",
                    "invoice_required",
                    "created_by",
                )
            },
        ),
    )


@admin.register(ScheduleAssignment)
class ScheduleAssignmentAdmin(admin.ModelAdmin):
    list_display = (
        "call",
        "inspector",
        "inspector_kind",
        "scheduled_date",
        "is_tentative",
        "is_active",
    )
    list_filter = ("inspector_kind", "is_tentative", "is_active", "scheduled_date")
    search_fields = ("call__call_ref", "inspector__name")
    autocomplete_fields = ("call", "inspector")


@admin.register(ReportDeliverable)
class ReportDeliverableAdmin(admin.ModelAdmin):
    list_display = ("call", "report_format", "due_date", "submitted_at", "is_overdue")
    list_filter = ("report_format", "uploaded_to_sharepoint")
    search_fields = ("call__call_ref",)


@admin.register(NetworkCredit)
class NetworkCreditAdmin(admin.ModelAdmin):
    list_display = (
        "call",
        "contracting_office",
        "executing_office",
        "amount",
        "month",
        "is_settled",
    )
    list_filter = ("contracting_office", "executing_office", "is_settled", "month")


@admin.register(CallEvent)
class CallEventAdmin(admin.ModelAdmin):
    list_display = ("call", "kind", "actor", "created_at")
    list_filter = ("kind",)
    search_fields = ("call__call_ref",)
