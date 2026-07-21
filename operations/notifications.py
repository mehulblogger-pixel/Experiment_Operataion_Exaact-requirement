"""Email notifications for the operations workflow."""

from django.conf import settings
from django.core.mail import send_mail


def notify_inspector_allocated(call, assignment):
    """Requirement 6d: email the inspector the full call details on allocation."""
    inspector = assignment.inspector
    to = inspector.email
    if not to:
        return False

    lines = [
        f"You have been {'tentatively ' if assignment.is_tentative else ''}"
        f"allocated an inspection.",
        "",
        f"Call reference : {call.call_ref}",
        f"Client         : {call.client}",
        f"Quotation No.  : {call.quotation_number or '-'}",
        f"Contract No.   : {call.contract_number or '-'}",
        f"Job type       : {call.job_type} ({call.get_engagement_type_display()})",
        f"Executing office: {call.executing_office}",
        f"Inspection date: {assignment.scheduled_date}",
        f"Vendor         : {call.vendor or '-'}"
        + (f", {call.vendor_location}" if call.vendor_location else ""),
        f"Address        : {call.inspection_address or '-'}",
        f"Client contact : {call.client_contact_person} {call.client_contact_mobile} "
        f"{call.client_contact_email}",
        f"Vendor contact : {call.vendor_contact_person} {call.vendor_contact_mobile} "
        f"{call.vendor_contact_email}",
        f"Payment terms  : {call.payment_term or '-'}"
        + ("  [ADVANCE — collect before inspection]" if call.is_advance_payment else ""),
        f"Product        : {call.product_category or '-'}",
        f"SharePoint     : {call.sharepoint_link or '-'}",
    ]
    if assignment.is_tentative:
        lines += ["", "NOTE: This allocation is TENTATIVE and may change."]

    send_mail(
        subject=f"[Inspection] {call.call_ref} — {call.client} on "
        f"{assignment.scheduled_date}",
        message="\n".join(lines),
        from_email=settings.DEFAULT_FROM_EMAIL,
        recipient_list=[to],
        fail_silently=True,
    )
    return True
