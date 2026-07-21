from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect, render

from .forms import ClientForm, VendorForm
from .models import Client, Vendor


@login_required
def client_list(request):
    q = request.GET.get("q", "").strip()
    qs = Client.objects.all()
    if q:
        qs = qs.filter(
            Q(name__icontains=q)
            | Q(contact_person__icontains=q)
            | Q(contact_email__icontains=q)
        )
    page = Paginator(qs.order_by("name"), 50).get_page(request.GET.get("page"))
    return render(
        request,
        "masters/client_list.html",
        {"page": page, "q": q, "total": qs.count()},
    )


@login_required
def client_create(request):
    form = ClientForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        client = form.save()
        messages.success(request, f"Client “{client.name}” added.")
        return redirect("masters:client_list")
    return render(
        request,
        "masters/master_form.html",
        {"form": form, "title": "Add Client", "back": "masters:client_list"},
    )


@login_required
def client_edit(request, pk):
    client = get_object_or_404(Client, pk=pk)
    form = ClientForm(request.POST or None, instance=client)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, f"Client “{client.name}” updated.")
        return redirect("masters:client_list")
    return render(
        request,
        "masters/master_form.html",
        {"form": form, "title": f"Edit {client.name}", "back": "masters:client_list"},
    )


@login_required
def vendor_list(request):
    q = request.GET.get("q", "").strip()
    qs = Vendor.objects.select_related("location")
    if q:
        qs = qs.filter(
            Q(name__icontains=q)
            | Q(contact_person__icontains=q)
            | Q(location__name__icontains=q)
        )
    page = Paginator(qs.order_by("name"), 50).get_page(request.GET.get("page"))
    return render(
        request,
        "masters/vendor_list.html",
        {"page": page, "q": q, "total": qs.count()},
    )


@login_required
def vendor_create(request):
    form = VendorForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        vendor = form.save()
        messages.success(request, f"Vendor “{vendor.name}” added.")
        return redirect("masters:vendor_list")
    return render(
        request,
        "masters/master_form.html",
        {"form": form, "title": "Add Vendor", "back": "masters:vendor_list"},
    )


@login_required
def vendor_edit(request, pk):
    vendor = get_object_or_404(Vendor, pk=pk)
    form = VendorForm(request.POST or None, instance=vendor)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, f"Vendor “{vendor.name}” updated.")
        return redirect("masters:vendor_list")
    return render(
        request,
        "masters/master_form.html",
        {"form": form, "title": f"Edit {vendor.name}", "back": "masters:vendor_list"},
    )
