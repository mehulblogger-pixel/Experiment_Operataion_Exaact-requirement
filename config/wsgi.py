"""
WSGI config for config project.

It exposes the WSGI callable as a module-level variable named ``application``.

For more information on this file, see
https://docs.djangoproject.com/en/5.1/howto/deployment/wsgi/
"""

import os

from django.core.wsgi import get_wsgi_application

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "config.settings")

application = get_wsgi_application()


def _bootstrap_admin():
    """First-run safety net: make sure a usable admin login exists so a fresh
    deployment is accessible without shell access.

    Only acts when NO superuser exists yet, so it never overrides a password you
    later change in the app. The password comes from DJANGO_SUPERUSER_PASSWORD if
    set, otherwise a documented default you should change on first login.
    """
    try:
        from django.contrib.auth import get_user_model

        from accounts.models import Role

        User = get_user_model()
        if User.objects.filter(is_superuser=True).exists():
            return

        username = os.environ.get("DJANGO_SUPERUSER_USERNAME", "admin")
        email = os.environ.get("DJANGO_SUPERUSER_EMAIL", "admin@mghaiapps.com")
        password = os.environ.get("DJANGO_SUPERUSER_PASSWORD", "Mghai@2026")

        user, _ = User.objects.get_or_create(
            username=username,
            defaults={
                "email": email,
                "role": Role.ADMIN,
                "is_staff": True,
                "is_superuser": True,
            },
        )
        user.is_staff = True
        user.is_superuser = True
        user.is_active = True
        if not user.role:
            user.role = Role.ADMIN
        user.set_password(password)
        user.save()
    except Exception:
        # Never let bootstrap break app startup (e.g. before migrations run).
        pass


_bootstrap_admin()
