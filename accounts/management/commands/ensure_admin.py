"""Create or update the admin superuser from environment variables.

Unlike `createsuperuser --noinput`, this is fully idempotent and uses
`set_password`, so it always applies the password in DJANGO_SUPERUSER_PASSWORD
(even on redeploys, and even if the password would fail the interactive
strength validators). This is what the deployment runs so the admin login
'just works' with no shell steps.

    python manage.py ensure_admin
"""

import os

from django.core.management.base import BaseCommand

from accounts.models import Role, User


class Command(BaseCommand):
    help = "Create or update the admin superuser from DJANGO_SUPERUSER_* env vars."

    def handle(self, *args, **options):
        username = os.environ.get("DJANGO_SUPERUSER_USERNAME", "admin")
        email = os.environ.get("DJANGO_SUPERUSER_EMAIL", "")
        password = os.environ.get("DJANGO_SUPERUSER_PASSWORD")

        if not password:
            self.stdout.write(
                "DJANGO_SUPERUSER_PASSWORD not set — skipping admin setup."
            )
            return

        user, created = User.objects.get_or_create(
            username=username,
            defaults={
                "email": email,
                "role": Role.ADMIN,
                "is_staff": True,
                "is_superuser": True,
            },
        )
        if email:
            user.email = email
        user.is_staff = True
        user.is_superuser = True
        user.is_active = True
        if not user.role:
            user.role = Role.ADMIN
        user.set_password(password)
        user.save()

        self.stdout.write(
            self.style.SUCCESS(
                f"Admin user '{username}' {'created' if created else 'updated'} "
                "with the configured password."
            )
        )
