from django.contrib.auth.models import AbstractUser
from django.db import models


class Role(models.TextChoices):
    """Positions across Sales/Operations. Kept as choices so permissions and
    dashboards can branch on role; Django Groups remain available for finer
    permission control from the admin."""

    DIRECTOR = "DIRECTOR", "Director"
    SBU_HEAD = "SBU_HEAD", "SBU Head"
    BRANCH_MANAGER = "BRANCH_MANAGER", "Branch Manager"
    OPERATION_MANAGER = "OPERATION_MANAGER", "Operation Manager"
    ASST_OPERATION_MANAGER = "ASST_OPERATION_MANAGER", "Assistant Operation Manager"
    PROJECT_COORDINATOR = "PROJECT_COORDINATOR", "Project Coordinator"
    INSPECTION_COORDINATOR = "INSPECTION_COORDINATOR", "Inspection Coordinator"
    INSPECTOR = "INSPECTOR", "Inspector / Engineer"
    # Sales side (future Quotation module)
    BUSINESS_DEV_MANAGER = "BUSINESS_DEV_MANAGER", "Business Development Manager"
    KEY_ACCOUNTS_MANAGER = "KEY_ACCOUNTS_MANAGER", "Key Accounts Manager"
    MARKETING_MANAGER = "MARKETING_MANAGER", "Marketing Manager"
    MARKETING_EXECUTIVE = "MARKETING_EXECUTIVE", "Marketing Executive"
    ACCOUNTS = "ACCOUNTS", "Accounts / Back Office"
    ADMIN = "ADMIN", "System Admin"


class User(AbstractUser):
    """Custom user tied to an office (IBO) and a role.

    Coordinators/managers see their own office's work by default; SBU heads and
    directors see across offices (enforced in views/querysets)."""

    role = models.CharField(
        max_length=32, choices=Role.choices, default=Role.INSPECTION_COORDINATOR
    )
    home_office = models.ForeignKey(
        "masters.Office",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="staff",
        help_text="Primary branch office (IBO) this user belongs to.",
    )
    sbu = models.ForeignKey(
        "masters.SBU",
        on_delete=models.SET_NULL,
        null=True,
        blank=True,
        related_name="staff",
        help_text="SBU for SBU Heads / relevant roles.",
    )
    phone = models.CharField(max_length=32, blank=True)

    class Meta:
        ordering = ["username"]

    def __str__(self):
        full = self.get_full_name()
        return f"{full or self.username} ({self.get_role_display()})"

    # --- Role helpers used by views / dashboards ---------------------------
    @property
    def is_coordinator(self):
        return self.role in {
            Role.INSPECTION_COORDINATOR,
            Role.PROJECT_COORDINATOR,
        }

    @property
    def is_manager(self):
        return self.role in {
            Role.OPERATION_MANAGER,
            Role.ASST_OPERATION_MANAGER,
            Role.BRANCH_MANAGER,
        }

    @property
    def sees_all_offices(self):
        return (
            self.role in {Role.DIRECTOR, Role.SBU_HEAD, Role.ADMIN}
            or self.is_superuser
        )
