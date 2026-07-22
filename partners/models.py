"""Unified Business Partner master (clients, vendors, sub-contractors).

One record per company, tagged with role(s). Feeds the whole app: a call picks a
client partner and pulls its contacts, site addresses, contracts and POs.
"""

from django.conf import settings
from django.db import models

from .utils import clean_gstin, pan_from_gstin, short_token, state_from_gstin


class ClientType(models.TextChoices):
    OWNER = "OWNER", "Owner / End Client"
    EPC = "EPC", "EPC Contractor"
    PMC = "PMC", "PMC / Project Management"
    CONSULTANT = "CONSULTANT", "Consultant"
    MANUFACTURER = "MANUFACTURER", "Manufacturer / Supplier"
    TRADER = "TRADER", "Trader / Distributor"
    SUBVENDOR = "SUBVENDOR", "Sub-vendor"
    OTHER = "OTHER", "Other"


class Industry(models.TextChoices):
    POWER = "POWER", "Power / Transmission"
    RENEWABLE = "RENEWABLE", "Renewable (Solar / Wind)"
    OIL_GAS = "OIL_GAS", "Oil & Gas"
    WATER = "WATER", "Water & Infrastructure"
    MINING = "MINING", "Mining & Metals"
    STEEL = "STEEL", "Steel"
    CEMENT = "CEMENT", "Cement"
    MANUFACTURING = "MANUFACTURING", "Manufacturing"
    INFRA = "INFRA", "Infrastructure / Construction"
    RAIL = "RAIL", "Railways"
    CHEMICAL = "CHEMICAL", "Chemical / Fertiliser"
    OTHER = "OTHER", "Other"


class Ownership(models.TextChoices):
    PVT_LTD = "PVT_LTD", "Private Limited"
    PUB_LTD = "PUB_LTD", "Public Limited"
    LLP = "LLP", "LLP"
    PARTNERSHIP = "PARTNERSHIP", "Partnership"
    PROPRIETOR = "PROPRIETOR", "Proprietorship"
    PSU = "PSU", "Government / PSU"
    TRUST = "TRUST", "Trust / Society"
    MNC = "MNC", "MNC / Foreign"
    OTHER = "OTHER", "Other"


class PartnerStatus(models.TextChoices):
    ACTIVE = "ACTIVE", "Active"
    INACTIVE = "INACTIVE", "Inactive"
    ON_HOLD = "ON_HOLD", "On hold"
    BLACKLISTED = "BLACKLISTED", "Blacklisted"
    PROSPECT = "PROSPECT", "Prospect"


class BusinessPartner(models.Model):
    code = models.CharField(max_length=40, unique=True, editable=False, db_index=True)
    registering_branch = models.ForeignKey(
        "masters.Office", on_delete=models.SET_NULL, null=True, blank=True,
        related_name="partners", help_text="Branch that owns this partner; prefixes the code.",
    )
    legal_name = models.CharField(max_length=255)
    display_name = models.CharField(
        max_length=255, blank=True, help_text="Short name shown on screens."
    )

    is_client = models.BooleanField("Is a Client", default=False)
    is_vendor = models.BooleanField("Is a Vendor / Manufacturer", default=False)
    is_subcontractor = models.BooleanField("Is a Sub-contractor", default=False)

    client_type = models.CharField(max_length=20, choices=ClientType.choices, blank=True)
    industry = models.CharField(max_length=20, choices=Industry.choices, blank=True)
    ownership_type = models.CharField(max_length=20, choices=Ownership.choices, blank=True)
    status = models.CharField(
        max_length=16, choices=PartnerStatus.choices, default=PartnerStatus.ACTIVE
    )
    default_sbu = models.ForeignKey(
        "masters.SBU", on_delete=models.SET_NULL, null=True, blank=True
    )
    parent = models.ForeignKey(
        "self", on_delete=models.SET_NULL, null=True, blank=True,
        related_name="subsidiaries",
        help_text="Parent group company, if this is a subsidiary / branch entity.",
    )

    gstin = models.CharField("GSTIN", max_length=15, blank=True, db_index=True)
    pan = models.CharField("PAN", max_length=10, blank=True, db_index=True)
    cin = models.CharField("CIN", max_length=21, blank=True)
    tan = models.CharField("TAN", max_length=10, blank=True)
    msme_udyam = models.CharField("MSME / Udyam No.", max_length=30, blank=True)
    state = models.CharField(max_length=60, blank=True, help_text="Auto from GSTIN.")

    website = models.URLField(blank=True)
    description = models.TextField(blank=True)

    is_active = models.BooleanField(default=True)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True
    )
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ["legal_name"]
        verbose_name = "Business Partner"

    def __str__(self):
        return self.display_name or self.legal_name

    @property
    def name(self):
        return self.display_name or self.legal_name

    @property
    def roles_label(self):
        parts = []
        if self.is_client:
            parts.append("Client")
        if self.is_vendor:
            parts.append("Vendor")
        if self.is_subcontractor:
            parts.append("Sub-contractor")
        return " / ".join(parts) or "—"

    def save(self, *args, **kwargs):
        self.gstin = clean_gstin(self.gstin)
        if self.gstin:
            if not self.pan:
                self.pan = pan_from_gstin(self.gstin)
            derived = state_from_gstin(self.gstin)
            if derived and not self.state:
                self.state = derived
        self.pan = (self.pan or "").strip().upper()
        if not self.code:
            self.code = self._generate_code()
        super().save(*args, **kwargs)

    def _generate_code(self):
        branch = (self.registering_branch.code[:3].upper()
                  if self.registering_branch else "GEN")
        prefix = f"{branch}-{short_token(self.display_name or self.legal_name)}-"
        last = (BusinessPartner.objects.filter(code__startswith=prefix)
                .order_by("code").last())
        seq = int(last.code.split("-")[-1]) + 1 if last else 1
        return f"{prefix}{seq:04d}"

    @property
    def primary_contact(self):
        return self.contacts.filter(is_primary=True).first() or self.contacts.first()

    @property
    def primary_address(self):
        return self.addresses.filter(is_primary=True).first() or self.addresses.first()


class AddressType(models.TextChoices):
    REGISTERED = "REGISTERED", "Registered Office"
    CORPORATE = "CORPORATE", "Corporate Office"
    BRANCH = "BRANCH", "Branch Office"
    PURCHASE = "PURCHASE", "Purchase Office"
    BILLING = "BILLING", "Billing Address"
    PLANT = "PLANT", "Plant"
    FACTORY = "FACTORY", "Factory"
    WAREHOUSE = "WAREHOUSE", "Warehouse"
    PROJECT_SITE = "PROJECT_SITE", "Project Site"
    SITE_OFFICE = "SITE_OFFICE", "Site Office"


class PartnerAddress(models.Model):
    partner = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="addresses"
    )
    address_type = models.CharField(max_length=20, choices=AddressType.choices)
    label = models.CharField(max_length=120, blank=True, help_text="e.g. Mundra Plant")
    line1 = models.CharField("Address line", max_length=255, blank=True)
    line2 = models.CharField("Address line 2", max_length=255, blank=True)
    city = models.CharField(max_length=120, blank=True)
    state = models.CharField(max_length=120, blank=True)
    pincode = models.CharField(max_length=12, blank=True)
    country = models.CharField(max_length=80, default="India")
    is_primary = models.BooleanField(default=False)

    class Meta:
        ordering = ["address_type", "label"]

    def __str__(self):
        return f"{self.get_address_type_display()} — {self.label or self.city}"

    @property
    def one_line(self):
        bits = [self.line1, self.line2, self.city, self.state, self.pincode]
        return ", ".join(b for b in bits if b)


class PartnerContact(models.Model):
    partner = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="contacts"
    )
    address = models.ForeignKey(
        PartnerAddress, on_delete=models.SET_NULL, null=True, blank=True,
        related_name="contacts",
    )
    name = models.CharField(max_length=120)
    designation = models.CharField(max_length=120, blank=True)
    department = models.CharField(max_length=120, blank=True)
    email = models.EmailField(blank=True)
    mobile = models.CharField(max_length=32, blank=True)
    phone = models.CharField(max_length=32, blank=True)
    is_primary = models.BooleanField(default=False)

    class Meta:
        ordering = ["-is_primary", "name"]

    def __str__(self):
        return f"{self.name} ({self.designation})" if self.designation else self.name


class RegistrationType(models.TextChoices):
    GSTIN = "GSTIN", "GSTIN"
    PAN = "PAN", "PAN"
    TAN = "TAN", "TAN"
    CIN = "CIN", "CIN"
    MSME = "MSME", "MSME / Udyam"
    ISO = "ISO", "ISO Certificate"
    PQ = "PQ", "Pre-Qualification"
    OTHER = "OTHER", "Other"


class PartnerRegistration(models.Model):
    partner = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="registrations"
    )
    doc_type = models.CharField(max_length=12, choices=RegistrationType.choices)
    number = models.CharField(max_length=60, blank=True)
    valid_from = models.DateField(null=True, blank=True)
    valid_to = models.DateField(null=True, blank=True)
    document = models.FileField(upload_to="partner_docs/%Y/%m/", blank=True)
    notes = models.CharField(max_length=200, blank=True)

    class Meta:
        ordering = ["doc_type"]

    def __str__(self):
        return f"{self.get_doc_type_display()} {self.number}"


class PartnerNote(models.Model):
    partner = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="notes"
    )
    note = models.TextField()
    author = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.SET_NULL, null=True, blank=True
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-created_at"]

    def __str__(self):
        return self.note[:60]


class RelationType(models.TextChoices):
    SUBSIDIARY = "SUBSIDIARY", "Subsidiary of"
    JV = "JV", "Joint Venture with"
    CONSORTIUM = "CONSORTIUM", "Consortium with"
    EPC_FOR = "EPC_FOR", "EPC Contractor for"
    CONSULTANT_FOR = "CONSULTANT_FOR", "Consultant for"
    SUPPLIER_TO = "SUPPLIER_TO", "Supplier to"
    OTHER = "OTHER", "Other"


class PartnerRelationship(models.Model):
    partner = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="relationships"
    )
    related = models.ForeignKey(
        BusinessPartner, on_delete=models.CASCADE, related_name="related_to"
    )
    relation_type = models.CharField(max_length=20, choices=RelationType.choices)
    notes = models.CharField(max_length=200, blank=True)

    class Meta:
        ordering = ["relation_type"]

    def __str__(self):
        return f"{self.partner} — {self.get_relation_type_display()} — {self.related}"
