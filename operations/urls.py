from django.urls import path

from . import views

app_name = "operations"

urlpatterns = [
    path("board/", views.schedule_board, name="schedule_board"),
    path("calls/", views.call_list, name="call_list"),
    path("calls/new/", views.call_create, name="call_create"),
    path("calls/<int:pk>/", views.call_detail, name="call_detail"),
    path("calls/<int:pk>/allocate/", views.allocate_inspector, name="allocate"),
    path("calls/<int:pk>/reject/", views.reject_call, name="reject"),
    path("calls/<int:pk>/complete/", views.complete_call, name="complete"),
]
