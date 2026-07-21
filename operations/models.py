"""Operations module: Call Register, Scheduling, Reporting, Credits.

Faithful to the requirement's Call Register form (item 5, fields a-ee) and the
Schedule Register (item 6c). Computed helpers implement the lead-time,
scheduling-delay, colour-status and escalation rules (items 5s, 5t, 5gg, 5ii).
"""

from datetime import timedelta

from django.conf import settings
from django.db import models
from django.utils import timezone


# ---------------------------------------------------------------------------
# Enumerations
# ---------------------------------------------------------------------------
class EngagementType(models.TextChoices):
    MANDAY = "MANDAY", "Man-day Inspection"
    DEPUTATION = "DEPUTATION", "Project Deputation"


class LocationType(models.TextChoices):
    AGREED = "AGREED", "Agreed location"
    PAN_INDIA = "PAN_INDIA", "General / Pan India"


class ReportingFrequency(models.TextChoices):
    NONE = "NONE", "No Reporting"
    DAILY = "DAILY", "Daily"
    ALTERNATE = "ALTERNATE", "Alternate day"
    WEEKLY = "WEEKLY", "Weekly"
    FORTNIGHTLY = "FORTNIGHTLY", "Fortnightly"
    MONTHLY = "MONTHLY", "Monthly"
    CUSTOM = "CUSTOM", "Custom"


class CallStatus(models.TextChoices):
    NEW = "NEW", "New / Registered"
    PENDING_SCHEDULE = "PENDING_SCHEDULE", "Pending scheduling"
    SCHEDULED = "SCHEDULED", "Scheduled"
    IN_PROGRESS = "IN_PROGRESS", "In progress"
    REPORT_PENDING = "REPORT_PENDING", "Report pending"
    COMPLETED = "COMPLETED", "Completed"
    INVOICE_PENDING = "INVOICE_PENDING", "Invoice pending"
    REJECTED = "REJECTED", "Rejected by executing office"
    CANCELLED = "CANCELLED", "Cancelled by client"


class ColourStatus(models.TextChoices):
    GREEN = "GREEN", "On track"
    AMBER = "AMBER", "Due soon"
    RED = "RED", "Overdue / not scheduled"


# ---------------------------------------------------------------------------
# Call Register (requirement 5)
# ---------------------------------------------------------------------------
class InspectionCall(models.Model):
    call_ref = models.CharField(
        max_length=32, unique=True, editable=False, db_index=True,
        help_text="Auto-generated call reference number.",
    )

    # Commercial linkage
    client = models.ForeignKey("masters.Client", on_delete=models.PROTECT)
    quotation_number = models.CharField(max_length=64, blank=True)
    contract_number = models.CharField(max_length=64, blank=True)

    # Job classification
    job_type = models.ForeignKey(
        "masters.JobType", on_delete=models.PROTECT, related_name="calls",
        help_text="Top-level or sub-category job type.",
    )
    engagement_type = models.CharField(
        max_length=16, choices=EngagementType.choices, default=EngagementType.MANDAY,
    )
    sbu = models.ForeignKey("masters.SBU", on_delete=models.PROTECT)
    activity_code = models.ForeignKey(
        "masters.ActivityCode", on_delete=models.PROTECT, null=True, blank=True
    )
    sub_activity_code = models.ForeignKey(
        "masters.SubActivityCode", on_delete=models.PROTECT, null=True, blank=True
    )
    product_category = models.ForeignKey(
        "masters.ProductCategory", on_delete=models.PROTECT, null=True, blank=True
    )

    # Offices: contracting = holds the client contract; executing = performs it.
    contracting_office = models.ForeignKey(
        "masters.Office", on_delete=models.PROTECT, related_name="contracted_calls"
    )
    executing_office = models.ForeignKey(
        "masters.Office", on_delete=models.PROTECT, related_name="executing_calls"
    )

    # Where the inspection happens
    location_type = models.CharField(
        max_length=16, choices=LocationType.choices, default=LocationType.AGREED
    )
    inspection_address = models.TextField(blank=True)
    vendor = models.ForeignKey(
        "masters.Vendor", on_delete=models.PROTECT, null=True, blank=True
    )
    vendor_location = models.ForeignKey(
        "masters.VendorLocation", on_delete=models.PROTECT, null=True, blank=True
    )

    # Client contact (denormalised snapshot so history is preserved)
    client_contact_person = models.CharField(max_length=120, blank=True)
    client_contact_mobile = models.CharField(max_length=32, blank=True)
    client_contact_email = models.EmailField(blank=True)

    # Vendor contact snapshot
    vendor_contact_person = models.CharField(max_length=120, blank=True)
    vendor_contact_mobile = models.CharField(max_length=32, blank=True)
    vendor_contact_email = models.EmailField(blank=True)

    # Payment
    payment_term = models.ForeignKey(
        "masters.PaymentTerm", on_delete=models.SET_NULL, null=True, blank=True
    )
    is_advance_payment = models.BooleanField(
        default=False,
        help_text="Payment must be received before scheduling (requirement 21).",
    )
    deliverable_against_payment = models.BooleanField(
        default=False,
        help_text="Report is released only against payment (requirement 22).",
    )

    # Dates (requirement 5p-5t)
    date_call_received = models.DateField(default=timezone.localdate)
    date_inspection_required = models.DateField()
    date_call_allocated = models.DateTimeField(
        null=True, blank=True,
        help_text="When the call was allocated to an inspector / branch coordinator.",
    )

    # Reporting (requirement 5u-5w)
    reporting_required = models.BooleanField(default=True)
    reporting_frequency = models.CharField(
        max_length=16, choices=ReportingFrequency.choices,
        default=ReportingFrequency.NONE,
    )
    custom_reporting_frequency = models.CharField(max_length=120, blank=True)
    report_formats = models.ManyToManyField(
        "masters.ReportFormat", blank=True, related_name="calls"
    )
    sharepoint_link = models.URLField(blank=True)

    # Commercials / credit system (requirement 5cc)
    revenue = models.DecimalField(
        max_digits=14, decimal_places=2, null=True, blank=True,
        help_text="Man-day charge to the client (hidden from executing office when "
        "credited instead).",
    )
    credit_to_executing_office = models.BooleanField(
        default=False,
        help_text="Set when a different office executes: the executing office is "
        "credited by the contracting office instead of seeing client revenue.",
    )
    credit_amount = models.DecimalField(
        max_digits=14, decimal_places=2, null=True, blank=True
    )

    # Status / lifecycle
    status = models.CharField(
        max_length=20, choices=CallStatus.choices, default=CallStatus.NEW, db_index=True
    )
    rejection_reason = models.TextField(blank=True)
    arranged_by_subcontractor = models.BooleanField(
        default=False,
        help_text="Contracting office arranged via a subcontractor after the "
        "executing office rejected/denied (requirement 5jj).",
    )
    invoice_required = models.BooleanField(null=True, blank=True)

    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True,
        related_name="calls_created",
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["-date_inspection_required", "-created_at"]
        indexes = [
            models.Index(fields=["executing_office", "status"]),
            models.Index(fields=["date_inspection_required"]),
        ]

    def __str__(self):
        return f"{self.call_ref} — {self.client}"

    # --- Reference number generation --------------------------------------
    def save(self, *args, **kwargs):
        if not self.call_ref:
            self.call_ref = self._generate_ref()
        # Keep advance flag in sync with the chosen payment term.
        if self.payment_term_id and self.payment_term.is_advance:
            self.is_advance_payment = True
        super().save(*args, **kwargs)

    def _generate_ref(self):
        today = timezone.localdate()
        prefix = f"CALL-{today:%Y%m}"
        last = (
            InspectionCall.objects.filter(call_ref__startswith=prefix)
            .order_by("call_ref")
            .last()
        )
        seq = int(last.call_ref.split("-")[-1]) + 1 if last else 1
        return f"{prefix}-{seq:04d}"

    # --- Computed operational metrics -------------------------------------
    @property
    def lead_time_days(self):
        """Requirement 5s: days available between receipt and required date."""
        if self.date_call_received and self.date_inspection_required:
            return (self.date_inspection_required - self.date_call_received).days
        return None

    @property
    def scheduling_delay_days(self):
        """Requirement 5t: required date minus the date allocated to inspector.

        Positive => the call was allocated late (after it was required)."""
        if self.date_call_allocated and self.date_inspection_required:
            allocated = timezone.localtime(self.date_call_allocated).date()
            return (allocated - self.date_inspection_required).days
        return None

    @property
    def is_scheduled(self):
        return self.status not in {
            CallStatus.NEW,
            CallStatus.PENDING_SCHEDULE,
            CallStatus.REJECTED,
            CallStatus.CANCELLED,
        }

    @property
    def colour_status(self):
        """Requirement 5gg: card colour on the coordinator dashboard."""
        if self.is_scheduled or self.status in {
            CallStatus.REJECTED,
            CallStatus.CANCELLED,
        }:
            return ColourStatus.GREEN
        today = timezone.localdate()
        if self.date_inspection_required < today:
            return ColourStatus.RED
        if (self.date_inspection_required - today).days <= 1:
            return ColourStatus.AMBER
        return ColourStatus.GREEN

    @property
    def days_unallocated(self):
        """How many days past the required date without allocation."""
        if self.is_scheduled:
            return 0
        return max(0, (timezone.localdate() - self.date_inspection_required).days)

    @property
    def escalation_level(self):
        """Requirement 5ii: escalate to Branch Manager after N days, SBU Head after M."""
        d = self.days_unallocated
        if d >= settings.ESCALATION_TO_SBU_HEAD_DAYS:
            return "SBU_HEAD"
        if d >= settings.ESCALATION_TO_BRANCH_MANAGER_DAYS:
            return "BRANCH_MANAGER"
        return None

    @property
    def is_network_call(self):
        """Executed by a different office than the contracting office."""
        return self.contracting_office_id != self.executing_office_id


class InspectorTypeChoice(models.TextChoices):
    SGS_ASSET = "SGS_ASSET", "SGS Asset (Employee)"
    EXPERT = "EXPERT", "Expert (External)"


# ---------------------------------------------------------------------------
# Schedule Register (requirement 6c)
# ---------------------------------------------------------------------------
class ScheduleAssignment(models.Model):
    call = models.ForeignKey(
        InspectionCall, on_delete=models.CASCADE, related_name="assignments"
    )
    inspector = models.ForeignKey(
        "masters.Inspector", on_delete=models.PROTECT, related_name="assignments"
    )
    inspector_kind = models.CharField(
        max_length=16, choices=InspectorTypeChoice.choices,
        default=InspectorTypeChoice.SGS_ASSET,
    )
    scheduled_date = models.DateField()
    is_tentative = models.BooleanField(
        default=False,
        help_text="Tentative allocation — highlighted until confirmed (requirement 6.2/6.3).",
    )
    notes = models.TextField(blank=True)

    allocated_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True
    )
    created_at = models.DateTimeField(auto_now_add=True)
    is_active = models.BooleanField(
        default=True, help_text="Set false when the inspector is reshuffled off this call."
    )

    class Meta:
        ordering = ["-scheduled_date"]
        indexes = [models.Index(fields=["inspector", "scheduled_date"])]

    def __str__(self):
        return f"{self.call.call_ref} → {self.inspector} on {self.scheduled_date}"


# ---------------------------------------------------------------------------
# Reporting / deliverables & TAT (requirement 5w, Reporting section)
# ---------------------------------------------------------------------------
class ReportDeliverable(models.Model):
    call = models.ForeignKey(
        InspectionCall, on_delete=models.CASCADE, related_name="deliverables"
    )
    report_format = models.ForeignKey("masters.ReportFormat", on_delete=models.PROTECT)
    due_date = models.DateField(null=True, blank=True, help_text="TAT due date.")
    submitted_at = models.DateTimeField(null=True, blank=True)
    document = models.FileField(upload_to="deliverables/%Y/%m/", blank=True)
    uploaded_to_sharepoint = models.BooleanField(default=False)
    notes = models.TextField(blank=True)

    class Meta:
        ordering = ["due_date"]

    def __str__(self):
        return f"{self.call.call_ref} — {self.report_format}"

    @property
    def is_overdue(self):
        if self.submitted_at or not self.due_date:
            return False
        return timezone.localdate() > self.due_date


# ---------------------------------------------------------------------------
# Audit trail: rejections, reassignments, escalations (requirements 5ee-5jj)
# ---------------------------------------------------------------------------
class CallEvent(models.Model):
    class Kind(models.TextChoices):
        REGISTERED = "REGISTERED", "Registered"
        SCHEDULED = "SCHEDULED", "Scheduled"
        RESHUFFLED = "RESHUFFLED", "Inspector reshuffled"
        REJECTED = "REJECTED", "Rejected by executing office"
        CANCELLED = "CANCELLED", "Cancelled by client"
        ESCALATED = "ESCALATED", "Escalated"
        COMPLETED = "COMPLETED", "Completed"
        INVOICE_FLAGGED = "INVOICE_FLAGGED", "Invoice flagged"
        NOTE = "NOTE", "Note"

    call = models.ForeignKey(
        InspectionCall, on_delete=models.CASCADE, related_name="events"
    )
    kind = models.CharField(max_length=20, choices=Kind.choices)
    note = models.TextField(blank=True)
    actor = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-created_at"]

    def __str__(self):
        return f"{self.call.call_ref} — {self.get_kind_display()}"


# ---------------------------------------------------------------------------
# Inter-office credit / revenue sharing (requirement 5cc; Network_Credits sheet)
# ---------------------------------------------------------------------------
class NetworkCredit(models.Model):
    call = models.OneToOneField(
        InspectionCall, on_delete=models.CASCADE, related_name="network_credit"
    )
    contracting_office = models.ForeignKey(
        "masters.Office", on_delete=models.PROTECT, related_name="credits_given"
    )
    executing_office = models.ForeignKey(
        "masters.Office", on_delete=models.PROTECT, related_name="credits_received"
    )
    amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    month = models.DateField(help_text="Month the credit belongs to (1st of month).")
    is_settled = models.BooleanField(default=False)
    remarks = models.CharField(max_length=255, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-month"]

    def __str__(self):
        return (
            f"{self.contracting_office} → {self.executing_office}: {self.amount}"
        )


def default_report_due(days=2):
    return timezone.localdate() + timedelta(days=days)
