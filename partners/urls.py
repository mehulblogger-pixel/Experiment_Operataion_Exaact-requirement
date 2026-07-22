from django.urls import path

from . import views

app_name = "partners"

urlpatterns = [
    path("clients/", views.client_list, name="client_list"),
    path("vendors/", views.vendor_list, name="vendor_list"),
    path("new/", views.partner_create, name="create"),
    path("<int:pk>/", views.partner_detail, name="detail"),
    path("<int:pk>/edit/", views.partner_edit, name="edit"),
    path("<int:pk>/address/add/", views.add_address, name="add_address"),
    path("<int:pk>/contact/add/", views.add_contact, name="add_contact"),
    path("<int:pk>/registration/add/", views.add_registration, name="add_registration"),
    path("<int:pk>/note/add/", views.add_note, name="add_note"),
]
