from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect, render
from django.urls import reverse

from .forms import (
    BusinessPartnerForm,
    PartnerAddressForm,
    PartnerContactForm,
    PartnerContractForm,
    PartnerNoteForm,
    PartnerPurchaseOrderForm,
    PartnerRegistrationForm,
    POLineItemForm,
)
from .models import BusinessPartner, PartnerPurchaseOrder, PartnerStatus


def _partner_list(request, role, title, subtitle):
    q = request.GET.get("q", "").strip()
    status = request.GET.get("status", "").strip()
    qs = BusinessPartner.objects.filter(**{role: True})
    if q:
        qs = qs.filter(
            Q(legal_name__icontains=q) | Q(display_name__icontains=q)
            | Q(code__icontains=q) | Q(gstin__icontains=q) | Q(pan__icontains=q)
        )
    if status:
        qs = qs.filter(status=status)
    page = Paginator(qs.order_by("legal_name"), 40).get_page(request.GET.get("page"))
    return render(request, "partners/partner_list.html", {
        "page": page, "q": q, "status": status, "total": qs.count(),
        "title": title, "subtitle": subtitle, "role": role,
        "statuses": PartnerStatus.choices,
    })


@login_required
def client_list(request):
    return _partner_list(request, "is_client", "Client Master",
                         "Customers, contacts, contracts and billing references")


@login_required
def vendor_list(request):
    return _partner_list(request, "is_vendor", "Vendor Master",
                         "Manufacturers, suppliers, plants and their contacts")


@login_required
def partner_detail(request, pk):
    p = get_object_or_404(BusinessPartner, pk=pk)
    return render(request, "partners/partner_detail.html", {
        "p": p,
        "tab": request.GET.get("tab", "overview"),
        "addresses": p.addresses.all(),
        "contacts": p.contacts.select_related("address"),
        "registrations": p.registrations.all(),
        "notes": p.notes.select_related("author"),
        "subsidiaries": p.subsidiaries.all(),
        "contracts": p.contracts.all(),
        "purchase_orders": p.purchase_orders.select_related("contract").prefetch_related("line_items"),
        "contract_form": PartnerContractForm(),
        "po_form": PartnerPurchaseOrderForm(partner=p),
        # client_calls / vendor_calls related managers appear once the operations
        # module is re-pointed to BusinessPartner (Pass 2). Guard until then.
        "calls": (p.client_calls.select_related("executing_office")[:50]
                  if p.is_client and hasattr(p, "client_calls") else []),
        "vendor_calls": (p.vendor_calls.select_related("client")[:50]
                         if p.is_vendor and hasattr(p, "vendor_calls") else []),
        "address_form": PartnerAddressForm(),
        "contact_form": PartnerContactForm(partner=p),
        "registration_form": PartnerRegistrationForm(),
        "note_form": PartnerNoteForm(),
    })


@login_required
def partner_create(request):
    default_role = request.GET.get("role", "is_client")
    if request.method == "POST":
        form = BusinessPartnerForm(request.POST)
        if form.is_valid():
            partner = form.save(commit=False)
            partner.created_by = request.user
            partner.save()
            messages.success(request, f"{partner.name} created ({partner.code}).")
            return redirect("partners:detail", pk=partner.pk)
    else:
        form = BusinessPartnerForm(initial={default_role: True})
    return render(request, "partners/partner_form.html",
                  {"form": form, "title": "New Business Partner"})


@login_required
def partner_edit(request, pk):
    partner = get_object_or_404(BusinessPartner, pk=pk)
    form = BusinessPartnerForm(request.POST or None, instance=partner)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, f"{partner.name} updated.")
        return redirect("partners:detail", pk=partner.pk)
    return render(request, "partners/partner_form.html",
                  {"form": form, "title": f"Edit {partner.name}", "partner": partner})


def _detail_url(partner, tab):
    return f"{reverse('partners:detail', args=[partner.pk])}?tab={tab}"


def _add_child(request, pk, form_class, tab, success, contact=False):
    partner = get_object_or_404(BusinessPartner, pk=pk)
    kwargs = {"partner": partner} if contact else {}
    form = form_class(request.POST or None, request.FILES or None, **kwargs)
    if request.method == "POST" and form.is_valid():
        obj = form.save(commit=False)
        obj.partner = partner
        if hasattr(obj, "author_id"):
            obj.author = request.user
        obj.save()
        messages.success(request, success)
    else:
        for errs in form.errors.values():
            for e in errs:
                messages.error(request, e)
    return redirect(_detail_url(partner, tab))


@login_required
def add_address(request, pk):
    return _add_child(request, pk, PartnerAddressForm, "addresses", "Address added.")


@login_required
def add_contact(request, pk):
    return _add_child(request, pk, PartnerContactForm, "contacts", "Contact added.", contact=True)


@login_required
def add_registration(request, pk):
    return _add_child(request, pk, PartnerRegistrationForm, "registration", "Registration added.")


@login_required
def add_note(request, pk):
    return _add_child(request, pk, PartnerNoteForm, "notes", "Note added.")


@login_required
def add_contract(request, pk):
    return _add_child(request, pk, PartnerContractForm, "contracts", "Contract added.")


@login_required
def add_purchase_order(request, pk):
    partner = get_object_or_404(BusinessPartner, pk=pk)
    form = PartnerPurchaseOrderForm(request.POST or None, partner=partner)
    if request.method == "POST" and form.is_valid():
        po = form.save(commit=False)
        po.partner = partner
        po.save()
        messages.success(request, "Purchase order added.")
        return redirect(f"{reverse('partners:po_detail', args=[po.pk])}")
    for errs in form.errors.values():
        for e in errs:
            messages.error(request, e)
    return redirect(_detail_url(partner, "purchase_orders"))


@login_required
def po_detail(request, pk):
    po = get_object_or_404(
        PartnerPurchaseOrder.objects.select_related("partner", "contract"), pk=pk
    )
    if request.method == "POST":
        form = POLineItemForm(request.POST)
        if form.is_valid():
            item = form.save(commit=False)
            item.purchase_order = po
            item.save()
            messages.success(request, "Line item added.")
            return redirect("partners:po_detail", pk=po.pk)
    else:
        form = POLineItemForm()
    return render(request, "partners/po_detail.html", {
        "po": po, "line_items": po.line_items.all(), "form": form,
    })
