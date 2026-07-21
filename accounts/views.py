from django.contrib import messages
from django.contrib.auth.decorators import login_required, user_passes_test
from django.shortcuts import get_object_or_404, redirect, render

from .forms import SetPasswordForm, UserCreateForm, UserEditForm
from .models import LicenseConfig, User


def _can_manage(user):
    return user.is_authenticated and user.can_manage_users


manage_required = user_passes_test(_can_manage)


@login_required
@manage_required
def user_list(request):
    lic = LicenseConfig.get()
    users = User.objects.order_by("-is_active", "username")
    return render(
        request,
        "accounts/user_list.html",
        {
            "users": users,
            "lic": lic,
            "seats_used": lic.active_seats_used(),
            "seats_available": lic.seats_available(),
        },
    )


@login_required
@manage_required
def user_create(request):
    lic = LicenseConfig.get()
    if not lic.can_add_seat():
        messages.error(
            request,
            f"You have used all {lic.licensed_seats} licensed user seats. "
            "Deactivate a user to free a seat, or increase your licence.",
        )
        return redirect("accounts:user_list")

    form = UserCreateForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        # Re-check seats at save time to avoid races.
        if not lic.can_add_seat():
            messages.error(request, "No licensed seats available.")
            return redirect("accounts:user_list")
        user = form.save()
        messages.success(request, f"User “{user.username}” created and active.")
        return redirect("accounts:user_list")
    return render(
        request,
        "accounts/user_form.html",
        {"form": form, "title": "Add User", "seats_available": lic.seats_available()},
    )


@login_required
@manage_required
def user_edit(request, pk):
    user_obj = get_object_or_404(User, pk=pk)
    form = UserEditForm(request.POST or None, instance=user_obj)
    if request.method == "POST" and form.is_valid():
        form.save()
        messages.success(request, f"User “{user_obj.username}” updated.")
        return redirect("accounts:user_list")
    return render(
        request,
        "accounts/user_form.html",
        {"form": form, "title": f"Edit {user_obj.username}", "user_obj": user_obj},
    )


@login_required
@manage_required
def user_toggle_active(request, pk):
    """Deactivate (frees a seat, keeps all their data) or reactivate a user."""
    user_obj = get_object_or_404(User, pk=pk)
    if request.method != "POST":
        return redirect("accounts:user_list")
    if user_obj == request.user:
        messages.error(request, "You cannot deactivate your own account.")
        return redirect("accounts:user_list")

    if user_obj.is_active:
        user_obj.is_active = False
        user_obj.save(update_fields=["is_active"])
        messages.success(
            request,
            f"“{user_obj.username}” deactivated. Their seat is now free and all "
            "their past records are preserved.",
        )
    else:
        lic = LicenseConfig.get()
        if not lic.can_add_seat():
            messages.error(
                request,
                f"Cannot reactivate — all {lic.licensed_seats} seats are in use.",
            )
            return redirect("accounts:user_list")
        user_obj.is_active = True
        user_obj.save(update_fields=["is_active"])
        messages.success(request, f"“{user_obj.username}” reactivated.")
    return redirect("accounts:user_list")


@login_required
@manage_required
def user_set_password(request, pk):
    user_obj = get_object_or_404(User, pk=pk)
    form = SetPasswordForm(request.POST or None)
    if request.method == "POST" and form.is_valid():
        user_obj.set_password(form.cleaned_data["password"])
        user_obj.save()
        messages.success(request, f"Password reset for “{user_obj.username}”.")
        return redirect("accounts:user_list")
    return render(
        request,
        "accounts/user_form.html",
        {"form": form, "title": f"Reset password — {user_obj.username}"},
    )
