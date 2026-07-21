from django.urls import path

from . import views

app_name = "masters"

urlpatterns = [
    path("clients/", views.client_list, name="client_list"),
    path("clients/new/", views.client_create, name="client_create"),
    path("clients/<int:pk>/edit/", views.client_edit, name="client_edit"),
    path("vendors/", views.vendor_list, name="vendor_list"),
    path("vendors/new/", views.vendor_create, name="vendor_create"),
    path("vendors/<int:pk>/edit/", views.vendor_edit, name="vendor_edit"),
]
