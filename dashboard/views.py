from django.contrib.auth.decorators import login_required
from django.db.models import Count, Sum
from django.shortcuts import render
from django.utils import timezone

from operations.models import CallStatus, InspectionCall
from operations.views import _visible_calls


@login_required
def home(request):
    calls = _visible_calls(request.user)
    today = timezone.localdate()

    open_statuses = [
        CallStatus.NEW,
        CallStatus.PENDING_SCHEDULE,
        CallStatus.SCHEDULED,
        CallStatus.IN_PROGRESS,
        CallStatus.REPORT_PENDING,
    ]
    pending_schedule = calls.filter(
        status__in=[CallStatus.NEW, CallStatus.PENDING_SCHEDULE]
    )
    overdue = [c for c in pending_schedule if c.date_inspection_required < today]

    by_status = {
        row["status"]: row["n"]
        for row in calls.values("status").annotate(n=Count("id"))
    }
    status_rows = [
        {"label": label, "count": by_status.get(code, 0)}
        for code, label in CallStatus.choices
    ]

    stats = {
        "open": calls.filter(status__in=open_statuses).count(),
        "pending_schedule": pending_schedule.count(),
        "overdue": len(overdue),
        "invoice_pending": calls.filter(status=CallStatus.INVOICE_PENDING).count(),
        "completed": calls.filter(status=CallStatus.COMPLETED).count(),
        "rejected": calls.filter(status=CallStatus.REJECTED).count(),
        "revenue_month": calls.filter(
            date_inspection_required__year=today.year,
            date_inspection_required__month=today.month,
        ).aggregate(s=Sum("revenue"))["s"]
        or 0,
    }

    recent = calls.order_by("-created_at")[:10]
    return render(
        request,
        "dashboard/home.html",
        {
            "stats": stats,
            "status_rows": status_rows,
            "recent": recent,
        },
    )
