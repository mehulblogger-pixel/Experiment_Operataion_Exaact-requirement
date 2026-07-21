from django.test import TestCase

from .models import LicenseConfig, Role, User


class SeatLicenceTests(TestCase):
    def setUp(self):
        self.admin = User.objects.create_superuser(
            username="admin", password="pw", email="a@a.com"
        )
        self.lic = LicenseConfig.get()
        self.lic.licensed_seats = 2
        self.lic.save()
        self.client.login(username="admin", password="pw")

    def _add(self, username):
        return self.client.post(
            "/team/users/new/",
            {
                "username": username,
                "password": "pass123",
                "role": Role.INSPECTION_COORDINATOR,
                "email": "",
            },
        )

    def test_superuser_does_not_consume_a_seat(self):
        self.assertEqual(LicenseConfig.active_seats_used(), 0)
        self.assertEqual(self.lic.seats_available(), 2)

    def test_seats_enforced(self):
        self._add("u1")
        self._add("u2")
        self.assertEqual(LicenseConfig.active_seats_used(), 2)
        # Third add is blocked (still 2 active non-super users).
        self._add("u3")
        self.assertEqual(LicenseConfig.active_seats_used(), 2)
        self.assertFalse(User.objects.filter(username="u3").exists())

    def test_deactivate_frees_seat_and_keeps_user(self):
        self._add("u1")
        self._add("u2")
        u1 = User.objects.get(username="u1")
        self.client.post(f"/team/users/{u1.pk}/toggle/")
        u1.refresh_from_db()
        self.assertFalse(u1.is_active)                       # kept, not deleted
        self.assertEqual(LicenseConfig.active_seats_used(), 1)
        # Seat freed → can add a replacement.
        self._add("u3")
        self.assertTrue(User.objects.filter(username="u3", is_active=True).exists())

    def test_non_manager_cannot_access(self):
        self.client.logout()
        User.objects.create_user(
            username="coord", password="pw", role=Role.INSPECTION_COORDINATOR
        )
        self.client.login(username="coord", password="pw")
        self.assertNotEqual(self.client.get("/team/users/").status_code, 200)
