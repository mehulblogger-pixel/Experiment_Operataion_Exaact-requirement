from datetime import timedelta

from django.conf import settings
from django.contrib import messages
from django.contrib.auth.decorators import login_required
from django.db.models import Q
from django.shortcuts import get_object_or_404, redirect, render
from django.utils import timezone

from .forms import (
    AllocateInspectorForm,
    DeliverableSubmitForm,
    InspectionCallForm,
    RejectCallForm,
)
from .models import (
    CallEvent,
    CallStatus,
    ColourStatus,
    InspectionCall,
    ReportDeliverable,
    ScheduleAssignment,
)
from .notifications import notify_inspector_allocated


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
            assignment = ScheduleAssignment.objects.create(
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
                note=f"Allocated to {assignment.inspector} "
                f"on {assignment.scheduled_date}"
                + (" (tentative)" if assignment.is_tentative else ""),
            )

            # Start the TAT clock: create a deliverable per selected report format.
            if call.reporting_required and not call.deliverables.exists():
                due = assignment.scheduled_date + timedelta(
                    days=settings.REPORT_TAT_DAYS
                )
                for fmt in call.report_formats.all():
                    ReportDeliverable.objects.create(
                        call=call, report_format=fmt, due_date=due
                    )

            # Notify the inspector (requirement 6d).
            emailed = notify_inspector_allocated(call, assignment)
            messages.success(
                request,
                "Inspector allocated."
                + (" Notification emailed." if emailed else
                   " (No inspector email on file — add one to notify by email.)"),
            )
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


@login_required
def reports_pending(request):
    """Deliverables not yet submitted, TAT-overdue highlighted (Reporting a-d)."""
    calls = _visible_calls(request.user)
    qs = (
        ReportDeliverable.objects.filter(call__in=calls, submitted_at__isnull=True)
        .select_related("call", "call__client", "report_format", "call__executing_office")
        .order_by("due_date")
    )
    today = timezone.localdate()
    rows = list(qs)
    overdue = sum(1 for d in rows if d.due_date and d.due_date < today)
    return render(
        request,
        "operations/reports_pending.html",
        {"deliverables": rows, "overdue": overdue, "today": today},
    )


@login_required
def deliverable_submit(request, pk):
    """Mark a deliverable submitted (Reporting c/e). Fetches call via visibility."""
    deliverable = get_object_or_404(
        ReportDeliverable.objects.filter(call__in=_visible_calls(request.user)), pk=pk
    )
    if request.method == "POST":
        form = DeliverableSubmitForm(request.POST, request.FILES)
        if form.is_valid():
            deliverable.submitted_at = timezone.now()
            if form.cleaned_data.get("document"):
                deliverable.document = form.cleaned_data["document"]
            deliverable.uploaded_to_sharepoint = form.cleaned_data[
                "uploaded_to_sharepoint"
            ]
            if form.cleaned_data.get("notes"):
                deliverable.notes = form.cleaned_data["notes"]
            deliverable.save()
            # If all deliverables are in, move the call out of report-pending.
            call = deliverable.call
            if not call.deliverables.filter(submitted_at__isnull=True).exists():
                if call.status == CallStatus.REPORT_PENDING:
                    call.status = CallStatus.IN_PROGRESS
                    call.save(update_fields=["status", "updated_at"])
            messages.success(request, f"“{deliverable.report_format}” submitted.")
            return redirect("operations:call_detail", pk=deliverable.call.pk)
    else:
        form = DeliverableSubmitForm()
    return render(
        request,
        "operations/deliverable_submit.html",
        {"form": form, "deliverable": deliverable},
    )


@login_required
def invoice_pending(request):
    """Calls flagged for invoicing (requirement 5h)."""
    calls = _visible_calls(request.user).filter(status=CallStatus.INVOICE_PENDING)
    return render(
        request,
        "operations/invoice_pending.html",
        {"calls": calls.order_by("-updated_at")},
    )


@login_required
def my_work(request):
    """Inspector self-service: their assignments and deliverables to submit."""
    profile = request.user.inspector_profile
    if not profile:
        return render(request, "operations/my_work.html", {"no_profile": True})
    assignments = (
        ScheduleAssignment.objects.filter(inspector=profile, is_active=True)
        .select_related("call", "call__client", "call__vendor")
        .order_by("-scheduled_date")
    )
    pending = (
        ReportDeliverable.objects.filter(
            call__assignments__inspector=profile,
            call__assignments__is_active=True,
            submitted_at__isnull=True,
        )
        .select_related("call", "report_format")
        .distinct()
        .order_by("due_date")
    )
    return render(
        request,
        "operations/my_work.html",
        {"assignments": assignments, "pending": pending, "today": timezone.localdate()},
    )
