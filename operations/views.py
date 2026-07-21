from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone

from .forms import AllocateInspectorForm, InspectionCallForm, RejectCallForm
from .models import (
    CallEvent,
    CallStatus,
    ColourStatus,
    InspectionCall,
    ScheduleAssignment,
)


def _visible_calls(user):
    """Coordinators/managers see their office's executing queue; SBU heads and
    directors see across all offices."""
    qs = InspectionCall.objects.select_related(
        "client", "job_type", "executing_office", "contracting_office", "sbu"
    )
    if user.sees_all_offices:
        return qs
    if user.home_office_id:
        return qs.filter(
            Q(executing_office_id=user.home_office_id)
            | Q(contracting_office_id=user.home_office_id)
        )
    return qs.none()


@login_required
def schedule_board(request):
    """Coordinator scheduling dashboard: pending calls as colour-coded cards
    (requirement 4 & 5gg). Red = required date passed & unscheduled."""
    calls = _visible_calls(request.user).filter(
        status__in=[CallStatus.NEW, CallStatus.PENDING_SCHEDULE]
    )

    # Optional date / colour filters
    colour = request.GET.get("colour")
    cards = list(calls.order_by("date_inspection_required"))
    if colour:
        cards = [c for c in cards if c.colour_status == colour]

    buckets = {"RED": [], "AMBER": [], "GREEN": []}
    for c in cards:
        buckets[c.colour_status].append(c)

    context = {
        "buckets": buckets,
        "counts": {k: len(v) for k, v in buckets.items()},
        "colour_filter": colour,
        "today": timezone.localdate(),
    }
    return render(request, "operations/schedule_board.html", context)


@login_required
def call_list(request):
    """Open / pending / closed views (requirement 14)."""
    calls = _visible_calls(request.user)
    status = request.GET.get("status")
    if status:
        calls = calls.filter(status=status)
    return render(
        request,
        "operations/call_list.html",
        {
            "calls": calls.order_by("-date_inspection_required")[:500],
            "status": status,
            "statuses": CallStatus.choices,
        },
    )


@login_required
def call_detail(request, pk):
    call = get_object_or_404(_visible_calls(request.user), pk=pk)
    return render(
        request,
        "operations/call_detail.html",
        {
            "call": call,
            "assignments": call.assignments.select_related("inspector").filter(
                is_active=True
            ),
            "deliverables": call.deliverables.select_related("report_format"),
            "events": call.events.select_related("actor"),
        },
    )


@login_required
def call_create(request):
    if request.method == "POST":
        form = InspectionCallForm(request.POST)
        if form.is_valid():
            call = form.save(commit=False)
            call.created_by = request.user
            call.status = CallStatus.PENDING_SCHEDULE
            call.save()
            form.save_m2m()
            CallEvent.objects.create(
                call=call, kind=CallEvent.Kind.REGISTERED, actor=request.user,
                note="Call registered.",
            )
            messages.success(request, f"Call {call.call_ref} registered.")
            return redirect("operations:call_detail", pk=call.pk)
    else:
        initial = {}
        if request.user.home_office_id:
            initial["contracting_office"] = request.user.home_office_id
            initial["executing_office"] = request.user.home_office_id
        form = InspectionCallForm(initial=initial)
    return render(request, "operations/call_form.html", {"form": form})


@login_required
def allocate_inspector(request, pk):
    """Schedule Register: allocate / reshuffle an inspector (requirement 6)."""
    call = get_object_or_404(_visible_calls(request.user), pk=pk)
    if request.method == "POST":
        form = AllocateInspectorForm(request.POST)
        if form.is_valid():
            # Reshuffle: deactivate any current active assignment.
            call.assignments.filter(is_active=True).update(is_active=False)
            ScheduleAssignment.objects.create(
                call=call,
                inspector=form.cleaned_data["inspector"],
                inspector_kind=form.cleaned_data["inspector_kind"],
                scheduled_date=form.cleaned_data["scheduled_date"],
                is_tentative=form.cleaned_data["is_tentative"],
                notes=form.cleaned_data["notes"],
                allocated_by=request.user,
            )
            call.date_call_allocated = timezone.now()
            call.status = CallStatus.SCHEDULED
            call.save(update_fields=["date_call_allocated", "status", "updated_at"])
            CallEvent.objects.create(
                call=call, kind=CallEvent.Kind.SCHEDULED, actor=request.user,
                note=f"Allocated to {form.cleaned_data['inspector']} "
                f"on {form.cleaned_data['scheduled_date']}"
                + (" (tentative)" if form.cleaned_data["is_tentative"] else ""),
            )
            messages.success(request, "Inspector allocated.")
            return redirect("operations:call_detail", pk=call.pk)
    else:
        form = AllocateInspectorForm()
    return render(
        request, "operations/allocate.html", {"form": form, "call": call}
    )


@login_required
def reject_call(request, pk):
    """Executing office rejects a call with a recorded reason (requirement 5ee)."""
    call = get_object_or_404(_visible_calls(request.user), pk=pk)
    if request.method == "POST":
        form = RejectCallForm(request.POST)
        if form.is_valid():
            call.status = CallStatus.REJECTED
            call.rejection_reason = form.cleaned_data["reason"]
            call.save(update_fields=["status", "rejection_reason", "updated_at"])
            CallEvent.objects.create(
                call=call, kind=CallEvent.Kind.REJECTED, actor=request.user,
                note=form.cleaned_data["reason"],
            )
            messages.warning(request, f"Call {call.call_ref} rejected and recorded.")
            return redirect("operations:call_detail", pk=call.pk)
    else:
        form = RejectCallForm()
    return render(
        request, "operations/reject.html", {"form": form, "call": call}
    )


@login_required
def complete_call(request, pk):
    """Mark job complete and ask whether an invoice is to be raised (requirement 5g/5h)."""
    call = get_object_or_404(_visible_calls(request.user), pk=pk)
    if request.method == "POST":
        raise_invoice = request.POST.get("invoice_required") == "yes"
        call.invoice_required = raise_invoice
        call.status = (
            CallStatus.INVOICE_PENDING if raise_invoice else CallStatus.COMPLETED
        )
        call.save(update_fields=["invoice_required", "status", "updated_at"])
        CallEvent.objects.create(
            call=call, kind=CallEvent.Kind.COMPLETED, actor=request.user,
            note="Job marked complete."
            + (" Invoice to be raised." if raise_invoice else ""),
        )
        if raise_invoice:
            CallEvent.objects.create(
                call=call, kind=CallEvent.Kind.INVOICE_FLAGGED, actor=request.user,
                note="Invoice generation pending — visible to coordinator/managers.",
            )
        messages.success(request, "Job marked complete.")
        return redirect("operations:call_detail", pk=call.pk)
    return render(request, "operations/complete.html", {"call": call})
