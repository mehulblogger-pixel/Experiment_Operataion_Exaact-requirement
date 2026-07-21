from django.urls import path

from . import views

app_name = "accounts"

urlpatterns = [
    path("users/", views.user_list, name="user_list"),
    path("users/new/", views.user_create, name="user_create"),
    path("users/<int:pk>/edit/", views.user_edit, name="user_edit"),
    path("users/<int:pk>/toggle/", views.user_toggle_active, name="user_toggle"),
    path("users/<int:pk>/password/", views.user_set_password, name="user_password"),
]
