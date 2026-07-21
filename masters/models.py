"""Master / reference data.

Every list a user picks from in the Operations module lives here as a table so
a non-technical admin can add / edit / retire entries from the Django admin with
no code change (requirements 5, 6, 8, 18, 25 -- configurable fields & dropdowns).
"""

from django.db import models


class TimeStamped(models.Model):
    is_active = models.BooleanField(
        default=True, help_text="Uncheck to hide from dropdowns without deleting."
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True


class Office(TimeStamped):
    """Branch office / IBO (e.g. AHMEDABAD, MUMBAI, PUNE)."""

    code = models.CharField(max_length=32, unique=True)
    name = models.CharField(max_length=120, blank=True)
    city = models.CharField(max_length=120, blank=True)
    is_head_office = models.BooleanField(default=False)

    class Meta:
        ordering = ["code"]

    def __str__(self):
        return self.code


class SBU(TimeStamped):
    """Strategic Business Unit (e.g. I03 Mining Energy & Power)."""

    code = models.CharField(max_length=16, unique=True)
    name = models.CharField(max_length=160)

    class Meta:
        ordering = ["code"]
        verbose_name = "SBU"
        verbose_name_plural = "SBUs"

    def __str__(self):
        return f"{self.code} - {self.name}"


class ActivityCode(TimeStamped):
    code = models.CharField(max_length=32)
    description = models.CharField(max_length=200, blank=True)
    sbu = models.ForeignKey(
        SBU, on_delete=models.PROTECT, null=True, blank=True, related_name="activities"
    )

    class Meta:
        ordering = ["code"]
        unique_together = [("code", "sbu")]

    def __str__(self):
        return self.code


class SubActivityCode(TimeStamped):
    activity = models.ForeignKey(
        ActivityCode, on_delete=models.CASCADE, related_name="sub_activities"
    )
    code = models.CharField(max_length=32)
    description = models.CharField(max_length=200, blank=True)

    class Meta:
        ordering = ["code"]

    def __str__(self):
        return f"{self.activity.code} / {self.code}"


class ProductCategory(TimeStamped):
    name = models.CharField(max_length=200, unique=True)

    class Meta:
        ordering = ["name"]
        verbose_name_plural = "Product categories"

    def __str__(self):
        return self.name


class VendorLocation(TimeStamped):
    """Nearby city used as a dropdown for the vendor/inspection location."""

    name = models.CharField(max_length=120, unique=True)
    state = models.CharField(max_length=120, blank=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class JobType(TimeStamped):
    """Configurable job types + sub-categories (requirements 18 & 25).

    Examples (parent): Inspection, Project Deputation, Supply Chain Deputation,
    Site Supervision, Commissioning & Installation, Technical Audit, Type Test,
    Vendor Assessment, Vendor Audit, Document Review, Tender Review.
    Sub-categories of Inspection: Product Inspection, Expediting Visit,
    Tender Document Review, Vendor Assessment, Vendor Audit ...
    Sub-categories of Project Deputation: Site QA/QC, Commissioning, O&M,
    Erection ...
    """

    name = models.CharField(max_length=120)
    parent = models.ForeignKey(
        "self",
        on_delete=models.CASCADE,
        null=True,
        blank=True,
        related_name="sub_categories",
        help_text="Leave blank for a top-level job type.",
    )
    allow_multiple_selection = models.BooleanField(
        default=False,
        help_text="If set, multiple sub-categories may be chosen for a call.",
    )

    class Meta:
        ordering = ["parent__name", "name"]
        unique_together = [("name", "parent")]

    def __str__(self):
        return f"{self.parent.name} / {self.name}" if self.parent else self.name


class ReportFormat(TimeStamped):
    """Deliverable report types, multi-selectable on a call (requirement 5w)."""

    name = models.CharField(max_length=120, unique=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class PaymentTerm(TimeStamped):
    name = models.CharField(max_length=120, unique=True)
    is_advance = models.BooleanField(
        default=False,
        help_text="Payment received before inspection is scheduled (requirement 21).",
    )

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class Client(TimeStamped):
    """The contracting client (SGS Client in the dashboard)."""

    name = models.CharField(max_length=255, unique=True)
    default_sbu = models.ForeignKey(
        SBU, on_delete=models.SET_NULL, null=True, blank=True
    )
    contact_person = models.CharField(max_length=120, blank=True)
    contact_email = models.EmailField(blank=True)
    contact_mobile = models.CharField(max_length=32, blank=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class Vendor(TimeStamped):
    """Manufacturer / Supplier / Vendor where inspection is performed."""

    name = models.CharField(max_length=255)
    location = models.ForeignKey(
        VendorLocation, on_delete=models.SET_NULL, null=True, blank=True
    )
    contact_person = models.CharField(max_length=120, blank=True)
    contact_email = models.EmailField(blank=True)
    contact_mobile = models.CharField(max_length=32, blank=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name


class InspectorType(models.TextChoices):
    EMPLOYEE = "EMPLOYEE", "Employee (SGS Asset)"
    SUBCON = "SUBCON", "Subcontractor"
    EXPERT = "EXPERT", "Expert (External)"


class Inspector(TimeStamped):
    """Inspection engineer / asset who executes calls."""

    name = models.CharField(max_length=160, unique=True)
    inspector_type = models.CharField(
        max_length=16, choices=InspectorType.choices, default=InspectorType.EMPLOYEE
    )
    discipline = models.CharField(
        max_length=80, blank=True, help_text="e.g. Mechanical, Civil, Electrical"
    )
    email = models.EmailField(blank=True, help_text="Used to notify on allocation.")
    phone = models.CharField(max_length=32, blank=True)
    sgs_asset_default = models.CharField(max_length=80, blank=True)
    home_office = models.ForeignKey(
        Office, on_delete=models.SET_NULL, null=True, blank=True, related_name="inspectors"
    )
    competencies = models.ManyToManyField(
        JobType,
        blank=True,
        related_name="competent_inspectors",
        help_text="Job types this inspector is competent to perform.",
    )
    subcon_suffix = models.CharField(
        max_length=16, blank=True, help_text="Rate suffix for subcontractors (e.g. ACS)."
    )
    subcon_rate = models.DecimalField(
        max_digits=12, decimal_places=2, null=True, blank=True
    )

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return self.name
