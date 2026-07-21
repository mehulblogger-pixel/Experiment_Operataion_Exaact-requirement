"""End-of-day digest & escalation (requirements 5gg, 5ii).

Run daily (cron / Celery beat / Task Scheduler):

    python manage.py daily_digest

For each office it emails the Operation/Branch manager a summary of calls that
are delayed, still unscheduled past their required date, or due tomorrow, and
escalates long-unallocated calls to the Branch Manager (>N days) / SBU Head
(>M days). Uses the console email backend unless SMTP is configured, so it is
safe to run immediately.
"""

from django.conf import settings
from django.core.mail import send_mail
from django.core.management.base import BaseCommand
from django.utils import timezone

from accounts.models import Role, User
from masters.models import Office
from operations.models import CallEvent, CallStatus, InspectionCall


class Command(BaseCommand):
    help = "Send end-of-day delayed/unscheduled digest and escalate stale calls."

    def handle(self, *args, **options):
        today = timezone.localdate()
        tomorrow = today + timezone.timedelta(days=1)
        sent = 0

        for office in Office.objects.filter(is_active=True):
            pending = InspectionCall.objects.filter(
                executing_office=office,
                status__in=[CallStatus.NEW, CallStatus.PENDING_SCHEDULE],
            )
            overdue = [c for c in pending if c.date_inspection_required < today]
            due_tomorrow = [
                c for c in pending if c.date_inspection_required == tomorrow
            ]
            if not (overdue or due_tomorrow):
                continue

            recipients = list(
                User.objects.filter(
                    home_office=office,
                    role__in=[
                        Role.OPERATION_MANAGER,
                        Role.BRANCH_MANAGER,
                        Role.ASST_OPERATION_MANAGER,
                    ],
                    is_active=True,
                ).exclude(email="").values_list("email", flat=True)
            )

            lines = [f"Daily operations digest — {office.code} — {today:%d %b %Y}", ""]
            if overdue:
                lines.append(f"OVERDUE / NOT SCHEDULED ({len(overdue)}):")
                for c in overdue:
                    lines.append(
                        f"  - {c.call_ref} {c.client} — required {c.date_inspection_required} "
                        f"({c.days_unallocated} day(s) late)"
                    )
                lines.append("")
            if due_tomorrow:
                lines.append(f"DUE TOMORROW ({len(due_tomorrow)}):")
                for c in due_tomorrow:
                    lines.append(f"  - {c.call_ref} {c.client}")
                lines.append("")

            body = "\n".join(lines)
            if recipients:
                send_mail(
                    subject=f"[Ops] {office.code}: {len(overdue)} overdue, "
                    f"{len(due_tomorrow)} due tomorrow",
                    message=body,
                    from_email=settings.DEFAULT_FROM_EMAIL,
                    recipient_list=recipients,
                    fail_silently=True,
                )
                sent += 1
            else:
                # No recipients configured: still surface the digest in the log.
                self.stdout.write(body)

            # Escalation events
            for c in overdue:
                level = c.escalation_level
                if level and not c.events.filter(
                    kind=CallEvent.Kind.ESCALATED,
                    note__icontains=level,
                    created_at__date=today,
                ).exists():
                    CallEvent.objects.create(
                        call=c,
                        kind=CallEvent.Kind.ESCALATED,
                        note=f"Escalated to {level} — unallocated {c.days_unallocated} days.",
                    )

        self.stdout.write(
            self.style.SUCCESS(f"Daily digest complete. {sent} office email(s) sent.")
        )
